<?php

namespace App\EventListener;

use App\Entity\Notification;
use App\Event\MessageEvent;
use App\Service\NotificationService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class MessageListener
{
    public function __construct(
        private NotificationService $notificationService
    )
    {}

    #[AsEventListener(event: MessageEvent::MESSAGE_CREATED)]
    public function onMessageCreated(MessageEvent $event): void
    {
        $message = $event->getMessage();

        // Notify the receiver about new message
        $this->notificationService->sendNotification(
            $message->getReceiver(),
            Notification::MESSAGE_RECEIVED,
            $message->getSender().' sends new message',
            true,
            [
                'message' => $message->getId(),
                'sender' => $message->getSender()->getId(),
                'receiver' => $message->getReceiver()->getId(),
                'employer' => $message->getEmployer()?->getId(),
            ]
        );
    }
}
