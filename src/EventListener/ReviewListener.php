<?php

namespace App\EventListener;

use App\Entity\Notification;
use App\Entity\User;
use App\Event\ReviewEvent;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class ReviewListener
{
    public function __construct(
        private NotificationService $notificationService,
        private EntityManagerInterface $em
    )
    {}

    #[AsEventListener(event: ReviewEvent::PROVIDER_REVIEWED)]
    public function onProviderReviewed(ReviewEvent $event): void
    {
        $review = $event->getReview();

        // Notify the provider about being reviewed by employer
        $this->notificationService->sendNotification(
            $review->getProvider()->getUser(),
            Notification::PROVIDER_REVIEWED,
            $review->getEmployer().' writes review for you',
            true,
            [
                'review' => $review->getId(),
                'employer' => $review->getEmployer()->getId(),
                'provider' => $review->getProvider()->getId(),
                'application' => $review->getApplication()->getId(),
            ]
        );
    }

    #[AsEventListener(event: ReviewEvent::EMPLOYER_REVIEWED)]
    public function onEmployerReviewed(ReviewEvent $event): void
    {
        $review = $event->getReview();

        // Notify the employer about being reviewed by provider
        $employerUser = $this->em->getRepository(User::class)->findOneBy(['employer' => $review->getEmployer()]);
        $this->notificationService->sendNotification(
            $employerUser,
            Notification::EMPLOYER_REVIEWED,
            $review->getProvider().' writes review for you',
            true,
            [
                'review' => $review->getId(),
                'employer' => $review->getEmployer()->getId(),
                'provider' => $review->getProvider()->getId(),
                'application' => $review->getApplication()->getId(),
            ]
        );
    }
}
