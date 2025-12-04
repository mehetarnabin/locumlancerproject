<?php
// src/Service/StripeService.php

namespace App\Service;

use App\Entity\Package;
use App\Entity\User;
use App\Entity\Payment;
use App\Entity\PackageSubscription;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Transfer;
use Stripe\Refund;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Uid\Uuid;

class StripeService
{
    private string $secretKey;
    private string $publicKey;
    private string $clientId;
    private string $webhookSecret;
    private UrlGeneratorInterface $router;
    private EntityManagerInterface $entityManager;
    private ParameterBagInterface $params;

    public function __construct(
        string $secretKey,
        string $publicKey,
        string $clientId,
        string $webhookSecret,
        UrlGeneratorInterface $router,
        EntityManagerInterface $entityManager,
        ParameterBagInterface $params
    ) {
        $this->secretKey = $secretKey;
        $this->publicKey = $publicKey;
        $this->clientId = $clientId;
        $this->webhookSecret = $webhookSecret;
        $this->router = $router;
        $this->entityManager = $entityManager;
        $this->params = $params;
        
        Stripe::setApiKey($this->secretKey);
        Stripe::setApiVersion('2023-10-16');
    }

    /**
     * Create a Stripe Checkout Session for package purchase
     */
    public function createCheckoutSession(Package $package, User $buyer, array $metadata = []): Session
    {
        // Prepare line items
        $lineItems = [[
            'price_data' => [
                'currency' => 'usd',
                'product_data' => [
                    'name' => $package->getName(),
                    'description' => $package->getDescription() ?? '',
                    'metadata' => [
                        'package_id' => $package->getId()->toString(),
                    ],
                ],
                'unit_amount' => (int) ($package->getPrice() * 100),
            ],
            'quantity' => 1,
        ]];

        // Prepare session parameters
        $sessionParams = [
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $this->router->generate('payment_success', [], UrlGeneratorInterface::ABSOLUTE_URL) 
                . '?session_id={CHECKOUT_SESSION_ID}&package_id=' . $package->getId()->toString(),
            'cancel_url' => $this->router->generate('payment_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'customer_email' => $buyer->getEmail(),
            'client_reference_id' => $buyer->getId()->toString(),
            'metadata' => array_merge($metadata, [
                'package_id' => $package->getId()->toString(),
                'buyer_id' => $buyer->getId()->toString(),
                'buyer_email' => $buyer->getEmail(),
                'package_name' => $package->getName(),
                'package_price' => $package->getPrice(),
            ]),
        ];

        return Session::create($sessionParams);
    }

    /**
     * Process Stripe webhook
     */
    public function handleWebhook(Request $request): array
    {
        try {
            $payload = $request->getContent();
            $sigHeader = $request->headers->get('stripe-signature');
            
            if (empty($this->webhookSecret)) {
                return [
                    'status' => 'error',
                    'message' => 'Webhook secret not configured'
                ];
            }
            
            $event = Webhook::constructEvent(
                $payload, 
                $sigHeader, 
                $this->webhookSecret
            );
            
            return $this->processEvent($event);
            
        } catch(\UnexpectedValueException $e) {
            return [
                'status' => 'error',
                'message' => 'Invalid payload: ' . $e->getMessage()
            ];
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            return [
                'status' => 'error',
                'message' => 'Invalid signature: ' . $e->getMessage()
            ];
        } catch(\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Webhook error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process Stripe events
     */
    private function processEvent($event): array
    {
        try {
            $data = [
                'status' => 'processed',
                'message' => 'Event processed successfully',
                'event_type' => $event->type,
                'event_id' => $event->id
            ];
            
            switch ($event->type) {
                case 'checkout.session.completed':
                    $result = $this->handleCheckoutSessionCompleted($event->data->object);
                    $data = array_merge($data, $result);
                    break;
                    
                case 'account.updated':
                    $result = $this->handleAccountUpdated($event->data->object);
                    $data = array_merge($data, $result);
                    break;
                    
                case 'payment_intent.succeeded':
                    $result = $this->handlePaymentIntentSucceeded($event->data->object);
                    $data = array_merge($data, $result);
                    break;
                    
                case 'transfer.created':
                    $result = $this->handleTransferCreated($event->data->object);
                    $data = array_merge($data, $result);
                    break;
                    
                default:
                    $data['status'] = 'skipped';
                    $data['message'] = 'Unhandled event type: ' . $event->type;
            }
            
            return $data;
            
        } catch (\Exception $e) {
            error_log('Webhook processEvent error: ' . $e->getMessage() . ' - Event: ' . ($event->type ?? 'unknown'));
            
            return [
                'status' => 'error',
                'message' => 'Failed to process event: ' . $e->getMessage(),
                'event_type' => $event->type ?? 'unknown',
                'event_id' => $event->id ?? 'unknown'
            ];
        }
    }
private function handleCheckoutSessionCompleted($session): array
{
    try {
        // Add detailed logging
        error_log("\n" . str_repeat("=", 80));
        error_log("WEBHOOK STARTED: " . date('Y-m-d H:i:s'));
        error_log("Session ID: " . ($session->id ?? 'NO ID'));
        error_log("Payment Status: " . ($session->payment_status ?? 'NO STATUS'));
        
        // FIRST: Try to fetch COMPLETE session from Stripe API
        error_log("Fetching complete session from Stripe API...");
        try {
            $fullSession = $this->getCheckoutSession($session->id);
            error_log("Full session retrieved from API");
            
            // Check metadata in the full session
            if (isset($fullSession->metadata) && !empty($fullSession->metadata)) {
                if (is_object($fullSession->metadata)) {
                    $metadata = (array)$fullSession->metadata;
                } elseif (is_array($fullSession->metadata)) {
                    $metadata = $fullSession->metadata;
                }
                error_log("Metadata from API: " . json_encode($metadata));
            } else {
                error_log("No metadata in API response either!");
                // Check if metadata might be on line items
                if (isset($fullSession->line_items->data[0]->price->product->metadata)) {
                    $productMetadata = (array)$fullSession->line_items->data[0]->price->product->metadata;
                    error_log("Product metadata: " . json_encode($productMetadata));
                    if (isset($productMetadata['package_id'])) {
                        $metadata['package_id'] = $productMetadata['package_id'];
                    }
                }
            }
            
            // Use the full session for processing
            $session = $fullSession;
        } catch (\Exception $e) {
            error_log("Error fetching session from API: " . $e->getMessage());
            // Continue with original session object
        }
        
        // Get metadata from the session (after potential API refresh)
        $metadata = [];
        if (isset($session->metadata) && !empty($session->metadata)) {
            if (is_object($session->metadata)) {
                $metadata = (array)$session->metadata;
            } elseif (is_array($session->metadata)) {
                $metadata = $session->metadata;
            }
        }
        
        error_log("Final metadata array: " . json_encode($metadata));
        
        // Try multiple sources for package_id and buyer_id
        $packageIdStr = $metadata['package_id'] ?? null;
        $buyerIdStr = $metadata['buyer_id'] ?? $session->client_reference_id ?? null;
        
        // If package_id still missing, try from success_url
        if (!$packageIdStr && isset($session->success_url)) {
            $urlParts = parse_url($session->success_url);
            if (isset($urlParts['query'])) {
                parse_str($urlParts['query'], $queryParams);
                $packageIdStr = $queryParams['package_id'] ?? null;
                if ($packageIdStr) {
                    error_log("Found package_id in success_url: " . $packageIdStr);
                }
            }
        }
        
        error_log("Final Package ID: " . ($packageIdStr ?? 'NOT FOUND'));
        error_log("Final Buyer ID: " . ($buyerIdStr ?? 'NOT FOUND'));
        
        // CRITICAL FIX: Don't create test payment - log error and require manual intervention
        if (!$packageIdStr || !$buyerIdStr) {
            error_log("❌ CRITICAL ERROR: Missing essential metadata!");
            error_log("Session Details:");
            error_log("  - Session ID: " . ($session->id ?? 'N/A'));
            error_log("  - Amount: $" . ($session->amount_total ? $session->amount_total / 100 : 'N/A'));
            error_log("  - Customer Email: " . ($session->customer_email ?? 'N/A'));
            error_log("  - Payment Intent: " . ($session->payment_intent ?? 'N/A'));
            
            // Create an error payment record instead of test payment
            return $this->createErrorPaymentRecord($session, $packageIdStr, $buyerIdStr);
        }
        
        // Now process the REAL payment
        error_log("Processing real payment...");
        
        // Find the package
        try {
            $package = $this->entityManager->getRepository(Package::class)
                ->findOneBy(['id' => $packageIdStr]);
            
            if (!$package) {
                error_log("Package not found for ID: " . $packageIdStr);
                // Try to find by UUID if string is UUID
                if (Uuid::isValid($packageIdStr)) {
                    $package = $this->entityManager->getRepository(Package::class)
                        ->findOneBy(['id' => Uuid::fromString($packageIdStr)]);
                }
                
                if (!$package) {
                    throw new \Exception("Package not found with ID: " . $packageIdStr);
                }
            }
            error_log("Package found: " . $package->getName());
        } catch (\Exception $e) {
            error_log("Error finding package: " . $e->getMessage());
            throw $e;
        }
        
        // Find the buyer/user
        try {
            $buyer = $this->entityManager->getRepository(User::class)
                ->findOneBy(['id' => $buyerIdStr]);
            
            if (!$buyer) {
                error_log("User not found for ID: " . $buyerIdStr);
                // Try to find by UUID if string is UUID
                if (Uuid::isValid($buyerIdStr)) {
                    $buyer = $this->entityManager->getRepository(User::class)
                        ->findOneBy(['id' => Uuid::fromString($buyerIdStr)]);
                }
                
                if (!$buyer) {
                    // Try by email as last resort
                    $buyerEmail = $metadata['buyer_email'] ?? $session->customer_email ?? null;
                    if ($buyerEmail) {
                        $buyer = $this->entityManager->getRepository(User::class)
                            ->findOneBy(['email' => $buyerEmail]);
                    }
                }
                
                if (!$buyer) {
                    throw new \Exception("User not found with ID: " . $buyerIdStr);
                }
            }
            error_log("User found: " . $buyer->getEmail() . " (ID: " . $buyer->getId()->toString() . ")");
        } catch (\Exception $e) {
            error_log("Error finding user: " . $e->getMessage());
            throw $e;
        }
        
        // Check if payment already exists (prevent duplicates)
        $existingPayment = $this->entityManager->getRepository(Payment::class)
            ->findOneBy(['stripeSessionId' => $session->id]);
        
        if ($existingPayment) {
            error_log("Payment already exists with ID: " . $existingPayment->getId()->toString());
            return [
                'status' => 'duplicate',
                'message' => 'Payment already recorded',
                'payment_id' => $existingPayment->getId()->toString()
            ];
        }
        
        // Create Payment object
        error_log("Creating Payment object...");
        $payment = new Payment();
        $payment->setPackage($package);
        $payment->setUser($buyer);
        $payment->setForUser($buyer);
        $payment->setAmount($session->amount_total / 100);
        $payment->setCurrency(strtoupper($session->currency));
        $payment->setStripeSessionId($session->id);
        $payment->setStripePaymentIntentId($session->payment_intent);
        $payment->setStatus(Payment::STATUS_COMPLETED);
        
        error_log("Payment object created with amount: $" . $payment->getAmount());
        
        // Create PackageSubscription
        error_log("Creating PackageSubscription...");
        $subscription = new PackageSubscription();
        $subscription->setUser($buyer);
        $subscription->setPackage($package);
        $subscription->setStatus(PackageSubscription::STATUS_ACTIVE);
        $subscription->setStartDate(new \DateTime());
        $subscription->setEndDate((new \DateTime())->modify('+' . $package->getDurationDays() . ' days'));
        $subscription->setPaidAmount($package->getPrice());
        $subscription->setTransactionId($session->payment_intent ?? 'tx_' . uniqid());
        $subscription->setUsedJobPosts(0);
        $subscription->setUsedApplications(0);
        $subscription->setRemainingJobPosts($package->getMaxJobPosts() ?? 0);
        $subscription->setRemainingApplications($package->getMaxApplications() ?? 0);
        
        error_log("Persisting payment and subscription...");
        $this->entityManager->persist($payment);
        $this->entityManager->persist($subscription);
        $this->entityManager->flush();
        
        error_log("✅ PAYMENT SAVED SUCCESSFULLY!");
        error_log("Payment ID: " . $payment->getId()->toString());
        error_log("Subscription ID: " . $subscription->getId()->toString());
        error_log("User ID: " . $buyer->getId()->toString());
        error_log("Package ID: " . $package->getId()->toString());
        error_log(str_repeat("=", 80) . "\n");
        
        return [
            'status' => 'success',
            'message' => 'Payment and subscription recorded successfully',
            'payment_id' => $payment->getId()->toString(),
            'subscription_id' => $subscription->getId()->toString()
        ];
        
    } catch (\Exception $e) {
        error_log("❌ WEBHOOK ERROR!");
        error_log("Error: " . $e->getMessage());
        error_log("File: " . $e->getFile());
        error_log("Line: " . $e->getLine());
        error_log("Trace: " . $e->getTraceAsString());
        error_log(str_repeat("=", 80) . "\n");
        
        return [
            'status' => 'error',
            'message' => 'Error processing webhook: ' . $e->getMessage(),
            'session_id' => isset($session->id) ? $session->id : 'unknown'
        ];
    }
}

private function createErrorPaymentRecord($session, $packageIdStr, $buyerIdStr): array
{
    try {
        error_log("Creating error payment record for manual recovery...");
        
        // Create a payment record with ERROR status
        $payment = new Payment();
        $payment->setStripeSessionId($session->id);
        $payment->setStripePaymentIntentId($session->payment_intent ?? 'error_pi_' . uniqid());
        $payment->setAmount($session->amount_total ? $session->amount_total / 100 : 0);
        $payment->setCurrency(strtoupper($session->currency ?? 'USD'));
        $payment->setStatus(Payment::STATUS_ERROR);
        
        // Add error metadata
        $payment->setNotes(json_encode([
            'error' => 'Missing metadata in webhook',
            'missing_package_id' => empty($packageIdStr),
            'missing_buyer_id' => empty($buyerIdStr),
            'customer_email' => $session->customer_email ?? null,
            'amount' => $session->amount_total ? $session->amount_total / 100 : 0,
            'currency' => $session->currency ?? 'USD',
            'recovery_needed' => true,
            'timestamp' => date('Y-m-d H:i:s')
        ]));
        
        $this->entityManager->persist($payment);
        $this->entityManager->flush();
        
        error_log("⚠️ ERROR PAYMENT RECORD CREATED: " . $payment->getId()->toString());
        error_log("This payment requires manual recovery!");
        
        return [
            'status' => 'error',
            'message' => 'Payment saved with ERROR status - missing metadata',
            'payment_id' => $payment->getId()->toString(),
            'requires_manual_recovery' => true,
            'session_id' => $session->id,
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency()
        ];
        
    } catch (\Exception $e) {
        error_log("Failed to create error payment record: " . $e->getMessage());
        return [
            'status' => 'critical_error',
            'message' => 'Failed to save payment record: ' . $e->getMessage(),
            'session_id' => $session->id ?? 'unknown'
        ];
    }
}



    /**
     * Create payment manually (for different payer and for_user scenarios)
     */
    public function createPayment(Package $package, User $payer, User $forUser, float $amount, string $currency = 'USD'): Payment
    {
        $payment = new Payment();
        $payment->setPackage($package);
        $payment->setUser($payer);
        $payment->setForUser($forUser);
        $payment->setAmount((string)$amount);
        $payment->setCurrency($currency);
        $payment->setStatus(Payment::STATUS_COMPLETED);
        $payment->setStripeSessionId('manual_' . uniqid());
        
        $this->entityManager->persist($payment);
        $this->entityManager->flush();
        
        return $payment;
    }

    /**
     * Finalize checkout session manually (for testing or when webhook is missed)
     */
    public function finalizeCheckoutSession(string $sessionId): array
    {
        try {
            $session = $this->getCheckoutSession($sessionId);
            
            if ($session->payment_status === 'paid') {
                return $this->handleCheckoutSessionCompleted($session);
            }
            
            return [
                'status' => 'pending',
                'message' => 'Payment not completed',
                'session_id' => $sessionId
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error finalizing checkout: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create Stripe Connect Express account for a user
     */
    public function createConnectedAccount(User $user): Account
    {
        return Account::create([
            'type' => 'express',
            'country' => 'US',
            'email' => $user->getEmail(),
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
            'business_type' => 'individual',
            'individual' => [
                'email' => $user->getEmail(),
                'first_name' => $user->getFirstName() ?? '',
                'last_name' => $user->getLastName() ?? '',
                'phone' => $user->getPhone1() ?? '',
            ],
            'business_profile' => [
                'url' => 'https://yourplatform.com',
                'mcc' => '7399',
            ],
            'settings' => [
                'payouts' => [
                    'schedule' => [
                        'interval' => 'manual',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Create account link for onboarding
     */
    public function createAccountLink(string $accountId, string $returnUrl, string $refreshUrl): AccountLink
    {
        return AccountLink::create([
            'account' => $accountId,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);
    }

    /**
     * Create login link for Stripe Express dashboard
     */
    public function createLoginLink(string $accountId): string
    {
        $loginLink = Account::createLoginLink($accountId);
        return $loginLink->url;
    }

    private function handleAccountUpdated($account): array
    {
        try {
            // Update user's Stripe Connect account status
            $user = $this->entityManager->getRepository(User::class)
                ->findOneBy(['stripeAccountId' => $account->id]);
                
            if ($user) {
                $user->setStripeAccountStatus($account->details_submitted ? 'verified' : 'pending');
                $this->entityManager->flush();
                
                return [
                    'status' => 'success',
                    'message' => 'User account updated',
                    'user_id' => $user->getId()->toString(),
                    'account_status' => $user->getStripeAccountStatus()
                ];
            }
            
            return [
                'status' => 'warning',
                'message' => 'User not found for account: ' . $account->id
            ];
            
        } catch (\Exception $e) {
            error_log('Error in handleAccountUpdated: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Error updating account: ' . $e->getMessage()
            ];
        }
    }

    private function handlePaymentIntentSucceeded($paymentIntent): array
    {
        try {
            // You can add additional payment intent handling here
            return [
                'status' => 'success',
                'message' => 'Payment intent processed',
                'payment_intent_id' => $paymentIntent->id
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error processing payment intent: ' . $e->getMessage()
            ];
        }
    }

    private function handleTransferCreated($transfer): array
    {
        try {
            // Log transfers to connected accounts
            return [
                'status' => 'success',
                'message' => 'Transfer recorded',
                'transfer_id' => $transfer->id,
                'amount' => $transfer->amount / 100,
                'destination' => $transfer->destination
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error processing transfer: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Refund a payment
     */
    public function refundPayment(string $paymentIntentId, ?int $amount = null): Refund
    {
        $params = ['payment_intent' => $paymentIntentId];
        
        if ($amount) {
            $params['amount'] = $amount * 100;
        }
        
        return Refund::create($params);
    }

    /**
     * Get payment intent details
     */
    public function getPaymentIntent(string $paymentIntentId): PaymentIntent
    {
        return PaymentIntent::retrieve($paymentIntentId);
    }

    /**
     * Get checkout session details
     */
    public function getCheckoutSession(string $sessionId): Session
    {
        return Session::retrieve($sessionId, ['expand' => ['line_items']]);
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * Test method to manually save payment (for debugging)
     */
    public function saveTestPayment(Package $package, User $user): array
    {
        try {
            // 1. Create payment record with new structure
            $payment = new Payment();
            $payment->setPackage($package);
            $payment->setUser($user);
            $payment->setForUser($user);  // Same user for testing
            $payment->setAmount($package->getPrice());
            $payment->setCurrency('USD');
            $payment->setStripeSessionId('test_session_' . uniqid());
            $payment->setStripePaymentIntentId('test_pi_' . uniqid());
            $payment->setStatus(Payment::STATUS_COMPLETED);
            
            $this->entityManager->persist($payment);
            
            // 2. Create package subscription record
            $subscription = new PackageSubscription();
            $subscription->setUser($user);
            $subscription->setPackage($package);
            $subscription->setStatus(PackageSubscription::STATUS_ACTIVE);
            $subscription->setStartDate(new \DateTime());
            $subscription->setEndDate((new \DateTime())->modify('+' . $package->getDurationDays() . ' days'));
            $subscription->setPaidAmount($package->getPrice());
            $subscription->setTransactionId('test_tx_' . uniqid());
            $subscription->setUsedJobPosts(0);
            $subscription->setUsedApplications(0);
            $subscription->setRemainingJobPosts($package->getMaxJobPosts() ?? 0);
            $subscription->setRemainingApplications($package->getMaxApplications() ?? 0);
            
            $this->entityManager->persist($subscription);
            $this->entityManager->flush();
            
            return [
                'status' => 'success',
                'message' => 'Test payment saved successfully',
                'payment_id' => $payment->getId()->toString(),
                'subscription_id' => $subscription->getId()->toString()
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error saving test payment: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Debug function to check Stripe configuration
     */
    public function getConfig(): array
    {
        return [
            'has_secret_key' => !empty($this->secretKey),
            'has_public_key' => !empty($this->publicKey),
            'has_client_id' => !empty($this->clientId),
            'has_webhook_secret' => !empty($this->webhookSecret),
            'public_key_prefix' => substr($this->publicKey, 0, 10) . '...',
            'secret_key_prefix' => substr($this->secretKey, 0, 10) . '...',
            'webhook_secret_prefix' => substr($this->webhookSecret, 0, 10) . '...',
        ];
    }

    /**
     * Verify if webhook signature is valid
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        try {
            $payload = $request->getContent();
            $sigHeader = $request->headers->get('stripe-signature');
            
            Webhook::constructEvent(
                $payload, 
                $sigHeader, 
                $this->webhookSecret
            );
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all payments for a user (as payer or beneficiary)
     */
    public function getUserPayments(User $user): array
    {
        $payments = $this->entityManager->getRepository(Payment::class)
            ->createQueryBuilder('p')
            ->where('p.user = :user OR p.forUser = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
        
        return $payments;
    }
}