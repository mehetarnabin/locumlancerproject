<?php

namespace App\MessageHandler;

use App\Entity\Document;
use App\Entity\Notification;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use App\Message\DocumentExpirationNotificationMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DocumentExpirationNotificationHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotificationService $notificationService,
    ){}

    public function __invoke(DocumentExpirationNotificationMessage $message): void
    {
        $sevenDaysLater = (new \DateTime())->modify('+7 days');
        $expiringDocuments = $this->em->getRepository(Document::class)->findExpiringDocuments($sevenDaysLater);

        foreach ($expiringDocuments as $document) {
            $this->notificationService->sendNotification(
                $document->getUser(),
                Notification::DOCUMENT_EXPIRING,
                "Your document '{$document->getName()}' is expiring on {$document->getExpirationDate()->format('Y-m-d')}.",
                true,
                [
                    'document' => $document->getId(),
                    'user' => $document->getUser()->getId(),
                ]
            );
        }
    }
}