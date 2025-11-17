<?php

namespace App\Controller\Provider;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/provider')]
class MembershipController extends AbstractController
{
    #[Route('/membership', name: 'app_provider_membership')]
    public function index(): Response
    {
        $plans = [
            [
                'name' => 'Essential',
                'price' => '$0',
                'tagline' => 'Perfect for getting started',
                'features' => [
                    'Create & manage your locum profile',
                    'Apply to unlimited locum jobs',
                    'Document locker with 1 GB storage',
                    'Basic analytics on applications',
                ],
                'cta' => 'Current Plan',
                'popular' => false,
            ],
            [
                'name' => 'Professional',
                'price' => '$49',
                'tagline' => 'For actively booking assignments',
                'features' => [
                    'Everything in Essential',
                    'Highlighted profile & faster reviews',
                    'Automated credential reminders',
                    'Dedicated membership lounge access',
                    'Weekly earnings snapshot',
                ],
                'cta' => 'Start 7-day trial',
                'popular' => true,
            ],
            [
                'name' => 'Elite',
                'price' => '$99',
                'tagline' => 'Concierge support for top locums',
                'features' => [
                    'Everything in Professional',
                    'Personal success manager',
                    'Priority credentialing assistance',
                    'On-demand tax & legal office hours',
                    '24/7 assignment hotline',
                ],
                'cta' => 'Talk to our team',
                'popular' => false,
            ],
        ];

        $perks = [
            [
                'title' => 'Faster Credentialing',
                'description' => 'Automated reminders, document templates, and concierge review to keep you credential-ready.',
                'icon' => 'document',
            ],
            [
                'title' => 'Premium Opportunities',
                'description' => 'Early access to curated locum assignments and private requests from partnered facilities.',
                'icon' => 'sparkle',
            ],
            [
                'title' => 'Financial Wellness',
                'description' => 'Discounted coverage, tax prep webinars, and priority support from compliance experts.',
                'icon' => 'shield',
            ],
        ];

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
                'question' => 'Is the Essential plan really free?',
                'answer' => 'Yes. Essential is free forever and includes unlimited job applications plus secure document storage.',
            ],
        ];

        return $this->render('provider/membership/index.html.twig', [
            'plans' => $plans,
            'perks' => $perks,
            'faqs' => $faqs,
        ]);
    }
}

