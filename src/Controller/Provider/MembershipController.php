<?php

namespace App\Controller\Provider;

use App\Entity\Package;
use App\Repository\PackageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/provider')]
class MembershipController extends AbstractController
{
    #[Route('/membership', name: 'app_provider_membership')]
    public function index(PackageRepository $packageRepository, Request $request): Response
    {
        // Check for payment status in URL
        $paymentStatus = $request->query->get('payment');
        $sessionId = $request->query->get('session_id');
        $packageId = $request->query->get('package_id');
        
        // Show success message if payment was successful
        if ($paymentStatus === 'success') {
            $this->addFlash('success', '🎉 Payment successful! Your membership has been activated.');
            
            // Optional: Verify with Stripe and update user's membership
            if ($sessionId && $sessionId !== '{CHECKOUT_SESSION_ID}') {
                // You could verify the payment here
                // Update user's membership status in database
            }
        }
        
        if ($paymentStatus === 'cancelled') {
            $this->addFlash('warning', 'Payment was cancelled. No charges were made.');
        }
        
        // Get only ACTIVE packages for PROVIDERS from database
        $packages = $packageRepository->findBy([
            'isActive' => true,
            'target' => 'provider' // Only show provider packages
        ], [
            'price' => 'ASC' // Sort by price (cheapest first)
        ]);

        // Transform database packages to template format
        $plans = array_map(function($package) {
            return [
                'name' => $package->getName(),
                'price' => '$' . number_format($package->getPrice(), 2),
                'tagline' => $package->getDescription() ?: 'Perfect for your locum career',
                'features' => $package->getFeatures() ?: [
                    'Create & manage your locum profile',
                    'Apply to locum jobs',
                    'Document storage',
                    'Basic analytics',
                ],
                'cta' => 'Choose Plan',
                'popular' => $package->isDefault(),
                'id' => $package->getId()->toString(),
                'duration' => $package->getDurationDays() . ' days',
                'type' => $package->getType(),
                'database_object' => $package,
            ];
        }, $packages);

        // If no packages found, use fallback
        if (empty($plans)) {
            $plans = [
                [
                    'name' => 'Essential',
                    'price' => '$0.00',
                    'tagline' => 'Perfect for getting started',
                    'features' => [
                        'Create & manage your locum profile',
                        'Apply to unlimited locum jobs',
                        'Document locker with 1 GB storage',
                        'Basic analytics on applications',
                    ],
                    'cta' => 'Current Plan',
                    'popular' => false,
                    'id' => 'fallback_essential',
                    'duration' => '30 days',
                    'type' => 'silver',
                ],
            ];
        }

        $faqs = [
            [
                'question' => 'Can I change plans at any time?',
                'answer' => 'Absolutely. You can upgrade or downgrade whenever you need. Changes take effect immediately.',
            ],
            [
                'question' => 'Do you offer refunds?',
                'answer' => 'If you cancel within the first 14 days of your billing cycle we issue a pro-rated refund automatically.',
            ],
            [
                'question' => 'Is there a free plan?',
                'answer' => 'Yes. Essential plan is free forever and includes unlimited job applications plus secure document storage.',
            ],
        ];

        return $this->render('provider/membership/index.html.twig', [
            'plans' => $plans,
            'faqs' => $faqs,
            'stripe_public_key' => $_ENV['STRIPE_PUBLIC_KEY'],
            'payment_status' => $paymentStatus, // Pass payment status to template
            'session_id' => $sessionId,
        ]);
    }
}
