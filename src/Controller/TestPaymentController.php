<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/test-payment')]
class TestPaymentController extends AbstractController
{
    #[Route('/simple', name: 'test_payment_simple', methods: ['GET'])]
    public function simple(): JsonResponse
    {
        return $this->json([
            'success' => true,
            'message' => 'Test endpoint works',
            'user' => $this->getUser() ? $this->getUser()->getEmail() : 'Not logged in'
        ]);
    }
    
    #[Route('/stripe-setup', name: 'test_stripe_setup', methods: ['GET'])]
    public function stripeSetup(): JsonResponse
    {
        try {
            $stripeKey = $_ENV['STRIPE_API_KEY'] ?? null;
            $publicKey = $_ENV['STRIPE_PUBLIC_KEY'] ?? null;
            
            return $this->json([
                'success' => true,
                'stripe_key_exists' => !empty($stripeKey),
                'public_key_exists' => !empty($publicKey),
                'public_key' => $publicKey ? substr($publicKey, 0, 20) . '...' : 'Missing',
                'env_keys' => array_keys($_ENV)
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

