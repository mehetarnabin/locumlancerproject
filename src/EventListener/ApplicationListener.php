<?php

namespace App\EventListener;

use App\Entity\Notification;
use App\Entity\User;
use App\Event\ApplicationEvent;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApplicationListener
{
    public function __construct(
        private NotificationService $notificationService,
        private EntityManagerInterface $em
    )
    {}

    #[AsEventListener(event: ApplicationEvent::APPLICATION_CREATED)]
    public function onApplicationCreated(ApplicationEvent $event): void
    {
        $application = $event->getApplication();
        $adminUser = $this->em->getRepository(User::class)->findOneBy(['userType' => User::TYPE_ADMIN], ['createdAt' => 'ASC']);

        // Notify the admin about the job being applied by provider
        $this->notificationService->sendNotification(
            $adminUser,
            Notification::JOB_APPLIED,
            $application->getProvider().' applied for job '.$application->getJob().' from employer '.$application->getEmployer(),
            false,
            [
                'application' => $application->getId(),
                'job' => $application->getJob()->getId(),
                'provider' => $application->getProvider()->getId(),
                'employer' => $application->getEmployer()->getId(),
            ]
        );

        // Notify the employer about the job being applied by provider
        $employerUser = $this->em->getRepository(User::class)->findOneBy(['employer' => $application->getEmployer()]);
        $this->notificationService->sendNotification(
            $employerUser,
            Notification::JOB_APPLIED,
            $application->getProvider().' applied for job '.$application->getJob(),
            false,
            [
                'application' => $application->getId(),
                'job' => $application->getJob()->getId(),
                'provider' => $application->getProvider()->getId(),
                'employer' => $application->getEmployer()->getId(),
            ]
        );
    }

    #[AsEventListener(event: ApplicationEvent::APPLICATION_DOCUMENT_REQUESTED)]
    public function onApplicationDocumentRequested(ApplicationEvent $event): void
    {
        $application = $event->getApplication();

        // Notify the provider about the document being requested
        $this->notificationService->sendNotification(
            $application->getProvider()->getUser(),
            Notification::DOCUMENT_REQUESTED,
            $application->getEmployer().' asks for documents for your job application '.$application->getJob(),
            true,
            [
                'application' => $application->getId(),
                'job' => $application->getJob()->getId(),
                'provider' => $application->getProvider()->getId(),
                'employer' => $application->getEmployer()->getId(),
            ]
        );
    }

    #[AsEventListener(event: ApplicationEvent::APPLICATION_DOCUMENT_PROVIDED)]
    public function onApplicationDocumentProvided(ApplicationEvent $event): void
    {
        $application = $event->getApplication();

        // Notify the employer about the document being provided
        $employerUser = $this->em->getRepository(User::class)->findOneBy(['employer' => $application->getEmployer()]);
        $this->notificationService->sendNotification(
            $employerUser,
            Notification::DOCUMENT_PROVIDED,
            $application->getProvider().' provides documents for job application '.$application->getJob(),
            false,
            [
                'application' => $application->getId(),
                'job' => $application->getJob()->getId(),
                'provider' => $application->getProvider()->getId(),
                'employer' => $application->getEmployer()->getId(),
            ]
        );
    }

    #[AsEventListener(event: ApplicationEvent::APPLICATION_ONE_FILE_REQUESTED)]
    public function onApplicationOneFileRequested(ApplicationEvent $event): void
    {
        $application = $event->getApplication();

        // Notify the provider about the one file being requested
        $this->notificationService->sendNotification(
            $application->getProvider()->getUser(),
            Notification::ONE_FILE_REQUESTED,
            $application->getEmployer().' asks for one file for your job application '.$application->getJob(),
            true,
            [
                'application' => $application->getId(),
                'job' => $application->getJob()->getId(),
                'provider' => $application->getProvider()->getId(),
                'employer' => $application->getEmployer()->getId(),
            ]
        );
    }

    #[AsEventListener(event: ApplicationEvent::APPLICATION_ONE_FILE_PROVIDED)]
    public function onApplicationOneFileProvided(ApplicationEvent $event): void
    {
        $application = $event->getApplication();

        // Notify the employer about the one file being provided
        $employerUser = $this->em->getRepository(User::class)->findOneBy(['employer' => $application->getEmployer()]);
        $this->notificationService->sendNotification(
            $employerUser,
            Notification::ONE_FILE_PROVIDED,
            $application->getProvider().' provides one file for job application '.$application->getJob(),
            false,
            [
                'application' => $application->getId(),
                'job' => $application->getJob()->getId(),
                'provider' => $application->getProvider()->getId(),
                'employer' => $application->getEmployer()->getId(),
            ]
        );
    }

    #[AsEventListener(event: ApplicationEvent::APPLICATION_CONTRACT_SENT)]
    public function onApplicationContractSent(ApplicationEvent $event): void
    {
        $application = $event->getApplication();

        // Notify the provider about the document being requested
        $this->notificationService->sendNotification(
            $application->getProvider()->getUser(),
            Notification::CONTRACT_SENT,
            $application->getEmployer().' sent contract for your job application '.$application->getJob(),
            true,
            [
                'application' => $application->getId(),
                'job' => $application->getJob()->getId(),
                'provider' => $application->getProvider()->getId(),
                'employer' => $application->getEmployer()->getId(),
            ]
        );
    }

    #[AsEventListener(event: ApplicationEvent::APPLICATION_CONTRACT_SIGNED_SENT)]
    public function onApplicationContractSignedSent(ApplicationEvent $event): void
    {
        $application = $event->getApplication();

        // Notify the employer about the document being provided
        $employerUser = $this->em->getRepository(User::class)->findOneBy(['employer' => $application->getEmployer()]);
        $this->notificationService->sendNotification(
            $employerUser,
            Notification::CONTRACT_SIGNED_SENT,
            $application->getProvider().' sends signed contract for job application '.$application->getJob(),
            false,
            [
                'application' => $application->getId(),
                'job' => $application->getJob()->getId(),
                'provider' => $application->getProvider()->getId(),
                'employer' => $application->getEmployer()->getId(),
            ]
        );
    }

    #[AsEventListener(event: ApplicationEvent::APPLICATION_INTERVIEW_SCHEDULED)]
    public function onApplicationInterviewScheduled(ApplicationEvent $event): void
    {
        $application = $event->getApplication();

        // Notify the provider about the document being requested
        $this->notificationService->sendNotification(
            $application->getProvider()->getUser(),
            Notification::INTERVIEW_SCHEDULED,
            $application->getEmployer().' sent interview schedule for your job application '.$application->getJob(),
            true,
            [
                'application' => $application->getId(),
                'interview' => $application->getInterview()->getId(),
                'job' => $application->getJob()->getId(),
                'provider' => $application->getProvider()->getId(),
                'employer' => $application->getEmployer()->getId(),
            ]
        );
    }
}
