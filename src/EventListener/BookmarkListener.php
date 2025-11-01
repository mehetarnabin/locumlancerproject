<?php

namespace App\EventListener;

use App\Entity\Notification;
use App\Event\BookmarkEvent;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class BookmarkListener
{
    public function __construct(
        private NotificationService $notificationService,
        private EntityManagerInterface $em
    )
    {}

    #[AsEventListener(event: BookmarkEvent::BOOKMARK_CREATED)]
    public function onProviderBookmarked(BookmarkEvent $event): void
    {
        $bookmark = $event->getBookmark();

        // Notify the provider to apply saved job
        $this->notificationService->sendNotification(
            $bookmark->getUser(),
            Notification::BOOKMARK_CREATED,
            'Apply to your saved job '.$bookmark->getJob(),
            false,
            [
                'bookmark' => $bookmark->getId(),
                'job' => $bookmark->getJob()->getId(),
                'user' => $bookmark->getUser()->getId(),
            ]
        );
    }
}
