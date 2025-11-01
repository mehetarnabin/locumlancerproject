<?php

namespace App\EventListener;

use App\Entity\Notification;
use App\Entity\User;
use App\Event\JobEvent;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class JobListener
{
    public function __construct(
        private NotificationService $notificationService,
        private EntityManagerInterface $em
    )
    {}

    #[AsEventListener(event: JobEvent::JOB_CREATED)]
    public function onJobCreated(JobEvent $event): void
    {
        $job = $event->getJob();
        $adminUser = $this->em->getRepository(User::class)->findOneBy(['userType' => User::TYPE_ADMIN], ['createdAt' => 'ASC']);
        $employer = $job->getEmployer();

        // Notify the admin about the job being posted
        $this->notificationService->sendNotification(
            $adminUser,
            Notification::JOB_POSTED,
            'New job has been posted by '.$employer->getName(),
            false,
            [
                'job' => $job->getId(),
                'employer' => $job->getEmployer()->getId(),
            ]
        );

        // Notify the employer about the job draft to be posted
        $employerUser = $this->em->getRepository(User::class)->findOneBy(['employer' => $job->getEmployer()]);
        $this->notificationService->sendNotification(
            $employerUser,
            Notification::JOB_POSTED,
            'Your new job '.$job->getTitle().' is in draft and ready to be published.',
            false,
            [
                'job' => $job->getId(),
                'employer' => $job->getEmployer()->getId(),
            ]
        );
    }
}
