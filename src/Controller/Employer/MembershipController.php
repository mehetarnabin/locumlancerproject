<?php

namespace App\Controller\Employer;

use App\Repository\PackageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/employer')]
class MembershipController extends AbstractController
{
    #[Route('/membership', name: 'app_employer_membership')]
    public function index(PackageRepository $packageRepository, Request $request): Response
    {
        $paymentStatus = $request->query->get('payment');
        $sessionId = $request->query->get('session_id');
        $packageId = $request->query->get('package_id');

        if ($paymentStatus === 'success') {
            $this->addFlash('success', 'Payment successful. Your employer membership has been activated.');
        }

        if ($paymentStatus === 'cancelled') {
            $this->addFlash('warning', 'Payment was cancelled. No charges were made.');
        }

        $packages = $packageRepository->findBy([
            'isActive' => true,
            'target' => 'employer'
        ], [
            'price' => 'ASC'
        ]);

        $plans = array_map(function($package) {
            return [
                'name' => $package->getName(),
                'price' => '$' . number_format($package->getPrice(), 2),
                'tagline' => $package->getDescription() ?: 'Tools to hire efficiently',
                'features' => $package->getFeatures() ?: [
                    'Post and manage jobs',
                    'Review applications',
                    'Messaging and document sharing',
                    'Basic analytics',
                ],
                'cta' => 'Choose Plan',
                'popular' => $package->isDefault(),
                'id' => $package->getId()->toString(),
                'duration' => $package->getDurationDays() . ' days',
                'type' => $package->getType(),
            ];
        }, $packages);

        if (empty($plans)) {
            $plans = [
                [
                    'name' => 'Essential',
                    'price' => '$0.00',
                    'tagline' => 'Start posting jobs',
                    'features' => [
                        'Post basic jobs',
                        'Manage applicants',
                        'Document locker',
                        'Basic analytics',
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
                'answer' => 'You can upgrade or downgrade whenever needed. Changes take effect immediately.',
            ],
            [
                'question' => 'Do you offer refunds?',
                'answer' => 'If you cancel within the first 14 days of your billing cycle we issue a pro-rated refund automatically.',
            ],
            [
                'question' => 'Is there a free plan?',
                'answer' => 'Yes. Essential plan is free and includes basic job posting and application management.',
            ],
        ];

        return $this->render('employer/membership/index.html.twig', [
            'plans' => $plans,
            'faqs' => $faqs,
            'stripe_public_key' => $_ENV['STRIPE_PUBLIC_KEY'],
            'payment_status' => $paymentStatus,
            'session_id' => $sessionId,
            'package_id' => $packageId,
        ]);
    }
}

