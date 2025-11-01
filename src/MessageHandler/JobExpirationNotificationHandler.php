<?php

namespace App\MessageHandler;

use App\Entity\Job;
use App\Entity\User;
use App\Entity\Notification;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use App\Message\JobExpirationNotificationMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class JobExpirationNotificationHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotificationService $notificationService,
    ){}

    public function __invoke(JobExpirationNotificationMessage $message): void
    {
        $threeDaysLater = (new \DateTime())->modify('+3 days');
        $expiringJobs = $this->em->getRepository(Job::class)->findExpiringJobs($threeDaysLater);

        foreach ($expiringJobs as $job) {
            $employerUser = $this->em->getRepository(User::class)->findOneBy(['employer' => $job->getEmployer()]);
            $this->notificationService->sendNotification(
                $employerUser,
                Notification::JOB_EXPIRING,
                "Your job posting '{$job->getTitle()}' is set to expire on {$job->getExpirationDate()->format('Y-m-d')}.",
                true,
                [
                    'job' => $job->getId(),
                    'employer' => $job->getEmployer()->getId(),
                ]
            );
        }
    }
}