<?php

namespace App\MessageHandler;

use App\Entity\Job;
use App\Entity\Provider;
use App\Entity\Notification;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use App\Message\MatchingJobNotificationMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class MatchingJobNotificationHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotificationService $notificationService,
    ){}

    public function __invoke(MatchingJobNotificationMessage $message): void
    {
        $job = $this->em->find(Job::class, $message->jobId);

        $providers = $this->em->getRepository(Provider::class)->findBy(['profession' => $job->getProfession()]);

        foreach ($providers as $provider) {
            $this->notificationService->sendNotification(
                $provider->getUser(),
                Notification::JOB_MATCHING,
                "New matching job alert '{$job->getTitle()}'",
                true,
                [
                    'job' => $job->getId(),
                    'employer' => $job->getEmployer()->getId(),
                    'provider' => $provider->getId(),
                ]
            );
        }
    }
}