<?php

namespace App\Controller;

use App\Entity\Package;
use App\Entity\Payment;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/payment')]
class PaymentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/package/{id}/checkout', name: 'payment_checkout', methods: ['POST'])]
    public function checkout(Package $package, StripeService $stripeService): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        if (!$package->isActive()) {
            return $this->json([
                'success' => false,
                'error' => 'This package is not available for purchase'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $user = $this->getUser();
        
        try {
            $checkoutSession = $stripeService->createCheckoutSession(
                $package, 
                $user
            );
            
            return $this->json([
                'success' => true,
                'sessionId' => $checkoutSession->id,
                'publicKey' => $stripeService->getPublicKey()
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    #[Route('/success', name: 'payment_success', methods: ['GET'])]
    public function success(Request $request, StripeService $stripeService): Response
    {
        $sessionId = $request->query->get('session_id');
        $packageId = $request->query->get('package_id');
        
        if ($sessionId) {
            try {
                $session = $stripeService->getCheckoutSession($sessionId);
                
                if ($session->payment_status === 'paid') {
                    $this->addFlash('success', 'Payment successful! Thank you for your purchase.');
                    
                    // Finalize session and persist records if webhook missed
                    $result = $stripeService->finalizeCheckoutSession($sessionId);
                    if (($result['status'] ?? '') !== 'success') {
                        $this->addFlash('warning', 'Payment recorded via webhook may have been skipped: ' . ($result['message'] ?? '')); 
                    }
                } else {
                    $this->addFlash('warning', 'Payment is still processing.');
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Unable to verify payment status: ' . $e->getMessage());
            }
        }
        
        return $this->render('payment/success.html.twig', [
            'session_id' => $sessionId,
            'package_id' => $packageId,
        ]);
    }
    
    #[Route('/cancel', name: 'payment_cancel', methods: ['GET'])]
    public function cancel(): Response
    {
        $this->addFlash('warning', 'Payment was cancelled. No charges were made.');
        
        return $this->render('payment/cancel.html.twig');
    }
    
    #[Route('/webhook', name: 'payment_webhook', methods: ['POST'])]
    public function webhook(Request $request, StripeService $stripeService): Response
    {
        try {
            $result = $stripeService->handleWebhook($request);
            
            return new JsonResponse($result, Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
    
    #[Route('/history', name: 'payment_history', methods: ['GET'])]
    public function history(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $user = $this->getUser();
        $payments = $entityManager->getRepository(Payment::class)
            ->findByUser($user->getId());
        
        return $this->render('payment/history.html.twig', [
            'payments' => $payments,
        ]);
    }

    #[Route('/test-service', name: 'payment_test_service', methods: ['GET'])]
    public function testService(StripeService $stripeService): JsonResponse
    {
        return $this->json([
            'success' => true,
            'message' => 'StripeService is working!',
            'public_key' => $stripeService->getPublicKey(),
            'key_length' => strlen($stripeService->getPublicKey())
        ]);
    }

    #[Route('/debug/{id}', name: 'payment_debug', methods: ['GET'])]
    public function debug(Package $package, StripeService $stripeService): JsonResponse
    {
        $user = $this->getUser();
        
        try {
            $session = $stripeService->createCheckoutSession($package, $user);
            
            return $this->json([
                'success' => true,
                'checkout_url' => $session->url,
                'session_id' => $session->id,
                'package' => [
                    'id' => $package->getId()->toString(),
                    'name' => $package->getName(),
                    'price' => $package->getPrice()
                ],
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    // Add this to your PaymentController.php
#[Route('/test-setup', name: 'payment_test_setup', methods: ['GET'])]
public function testSetup(StripeService $stripeService): JsonResponse
{
    try {
        $session = $stripeService->getCheckoutSession('cs_test_...'); // Use a real session ID
        
        return $this->json([
            'success' => true,
            'session' => [
                'id' => $session->id,
                'payment_status' => $session->payment_status,
                'metadata' => $session->metadata,
                'amount_total' => $session->amount_total,
                'currency' => $session->currency
            ],
            'webhook_secret_set' => !empty($_ENV['STRIPE_WEBHOOK_SECRET'])
        ]);
    } catch (\Exception $e) {
        return $this->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}
}
