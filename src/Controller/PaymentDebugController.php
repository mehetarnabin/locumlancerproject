<?php

namespace App\Controller;

use App\Entity\Package;
use App\Entity\Payment;
use App\Entity\PackageSubscription;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/payment-debug')]
class PaymentDebugController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Check database for payment records
     */
    #[Route('/check-payments', name: 'payment_debug_check', methods: ['GET'])]
    public function checkPayments(): JsonResponse
    {
        $payments = $this->entityManager->getRepository(Payment::class)
            ->findBy([], ['createdAt' => 'DESC'], 10);
        
        $subscriptions = $this->entityManager->getRepository(PackageSubscription::class)
            ->findBy([], ['startDate' => 'DESC'], 10);
        
        $paymentData = array_map(function($payment) {
            return [
                'id' => $payment->getId(),
                'amount' => $payment->getAmount(),
                'currency' => $payment->getCurrency(),
                'status' => $payment->getStatus(),
                'stripe_session_id' => $payment->getStripeSessionId(),
                'created_at' => $payment->getCreatedAt()->format('Y-m-d H:i:s'),
                'user_email' => $payment->getUser()->getEmail(),
                'package_name' => $payment->getPackage()->getName(),
            ];
        }, $payments);
        
        $subscriptionData = array_map(function($sub) {
            return [
                'id' => $sub->getId(),
                'status' => $sub->getStatus(),
                'start_date' => $sub->getStartDate()->format('Y-m-d H:i:s'),
                'end_date' => $sub->getEndDate() ? $sub->getEndDate()->format('Y-m-d H:i:s') : null,
                'transaction_id' => $sub->getTransactionId(),
                'user_email' => $sub->getUser()->getEmail(),
                'package_name' => $sub->getPackage()->getName(),
            ];
        }, $subscriptions);
        
        return $this->json([
            'success' => true,
            'total_payments' => count($payments),
            'total_subscriptions' => count($subscriptions),
            'recent_payments' => $paymentData,
            'recent_subscriptions' => $subscriptionData,
        ]);
    }

    /**
     * Check if a specific session ID exists in database
     */
    #[Route('/check-session/{sessionId}', name: 'payment_debug_session', methods: ['GET'])]
    public function checkSession(string $sessionId, StripeService $stripeService): JsonResponse
    {
        // Check database
        $payment = $this->entityManager->getRepository(Payment::class)
            ->findOneBy(['stripeSessionId' => $sessionId]);
        
        // Check Stripe
        try {
            $session = $stripeService->getCheckoutSession($sessionId);
            
            return $this->json([
                'success' => true,
                'session_id' => $sessionId,
                'in_database' => $payment !== null,
                'payment_id' => $payment ? $payment->getId() : null,
                'stripe_status' => $session->payment_status,
                'stripe_amount' => $session->amount_total / 100,
                'stripe_metadata' => (array) $session->metadata,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
                'in_database' => $payment !== null,
            ]);
        }
    }

    /**
     * Manually process a session (bypass webhook)
     */
    #[Route('/process-session/{sessionId}', name: 'payment_debug_process', methods: ['POST'])]
    public function processSession(string $sessionId, StripeService $stripeService): JsonResponse
    {
        try {
            $result = $stripeService->finalizeCheckoutSession($sessionId);
            
            return $this->json([
                'success' => true,
                'message' => 'Session processed',
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    /**
     * Test webhook endpoint (simulate Stripe webhook)
     */
    #[Route('/test-webhook', name: 'payment_debug_test_webhook', methods: ['GET'])]
    public function testWebhook(): JsonResponse
    {
        $webhookUrl = $this->generateUrl('payment_webhook', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);
        
        return $this->json([
            'success' => true,
            'webhook_url' => $webhookUrl,
            'instructions' => [
                'stripe_cli' => 'stripe listen --forward-to ' . $webhookUrl,
                'ngrok' => [
                    '1. Run: ngrok http 8000',
                    '2. Copy the https URL',
                    '3. Add webhook in Stripe Dashboard: https://dashboard.stripe.com/test/webhooks',
                    '4. Use URL: {ngrok_url}/payment/webhook',
                ],
            ],
            'webhook_secret' => 'Check .env file for STRIPE_WEBHOOK_SECRET',
        ]);
    }

    /**
     * Get recent error logs related to webhooks
     */
    #[Route('/webhook-logs', name: 'payment_debug_logs', methods: ['GET'])]
    public function webhookLogs(): JsonResponse
    {
        $logFile = $this->getParameter('kernel.logs_dir') . '/dev.log';
        
        if (!file_exists($logFile)) {
            return $this->json([
                'success' => false,
                'error' => 'Log file not found: ' . $logFile,
            ]);
        }
        
        // Read last 100 lines
        $lines = [];
        $file = new \SplFileObject($logFile);
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        $startLine = max(0, $lastLine - 100);
        
        $file->seek($startLine);
        while (!$file->eof()) {
            $line = $file->current();
            if (stripos($line, 'webhook') !== false || 
                stripos($line, 'stripe') !== false || 
                stripos($line, 'payment') !== false) {
                $lines[] = trim($line);
            }
            $file->next();
        }
        
        return $this->json([
            'success' => true,
            'log_file' => $logFile,
            'relevant_lines' => $lines,
            'total_lines' => count($lines),
        ]);
    }

    /**
     * Create a test payment manually
     */
    #[Route('/create-test-payment/{packageId}', name: 'payment_debug_create_test', methods: ['POST'])]
    public function createTestPayment(string $packageId, StripeService $stripeService): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $package = $this->entityManager->getRepository(Package::class)->find($packageId);
        $user = $this->getUser();
        
        if (!$package) {
            return $this->json([
                'success' => false,
                'error' => 'Package not found',
            ], 404);
        }
        
        try {
            $result = $stripeService->saveTestPayment($package, $user);
            
            return $this->json([
                'success' => true,
                'message' => 'Test payment created',
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
