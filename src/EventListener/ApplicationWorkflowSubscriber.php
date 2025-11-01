<?php

namespace App\EventListener;

use App\Entity\Employer;
use App\Entity\User;
use App\Entity\Invoice;
use App\Entity\Application;
use App\Entity\Notification;
use App\Service\ConfigManager;
use App\Service\InvoiceNumberGenerator;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ApplicationWorkflowSubscriber implements EventSubscriberInterface
{
    public function __construct(
        readonly private EntityManagerInterface $em,
        private NotificationService $notificationService,
        private ConfigManager $configManager,
        private InvoiceNumberGenerator $invoiceNumberGenerator
    ){}

    public static function getSubscribedEvents()
    {
        return [
            'workflow.job_application_workflow.entered' => 'onEnter'
        ];
    }

    public function onEnter(Event $event): void
    {
        /** @var Application $application */
        $application = $event->getSubject();

        $transition = $event->getTransition()->getName();

        $adminUser = $this->em->getRepository(User::class)->findOneBy(['userType' => User::TYPE_ADMIN], ['createdAt' => 'ASC']);
        $employerUser = $this->em->getRepository(User::class)->findOneBy(['employer' => $application->getEmployer()]);
        $providerUser = $application->getProvider()->getUser();

        if ($transition === 'review') {
            // Notify the employer being provider under review
            $this->notificationService->sendNotification(
                $employerUser,
                Notification::PROVIDER_IN_REVIEW,
                $application->getProvider().' under review for job '.$application->getJob(),
                false,
                [
                    'application' => $application->getId(),
                    'job' => $application->getJob()->getId(),
                    'provider' => $application->getProvider()->getId(),
                    'employer' => $application->getEmployer()->getId(),
                ]
            );

            // Notify the provider being under review
            $this->notificationService->sendNotification(
                $providerUser,
                Notification::PROVIDER_IN_REVIEW,
                'Your application for job '.$application->getJob().' under review',
                true,
                [
                    'application' => $application->getId(),
                    'job' => $application->getJob()->getId(),
                    'provider' => $application->getProvider()->getId(),
                    'employer' => $application->getEmployer()->getId(),
                ]
            );
        }

        if ($transition === 'interview') {
            // Notify the employer being provider shortlisted
            $this->notificationService->sendNotification(
                $employerUser,
                Notification::PROVIDER_SHORTLIST,
                $application->getProvider().' shortlisted for job '.$application->getJob(),
                false,
                [
                    'application' => $application->getId(),
                    'job' => $application->getJob()->getId(),
                    'provider' => $application->getProvider()->getId(),
                    'employer' => $application->getEmployer()->getId(),
                ]
            );

            // Notify the provider being shortlisted
            $this->notificationService->sendNotification(
                $providerUser,
                Notification::PROVIDER_SHORTLIST,
                'Your application for job '.$application->getJob().' has been shortlisted',
                true,
                [
                    'application' => $application->getId(),
                    'job' => $application->getJob()->getId(),
                    'provider' => $application->getProvider()->getId(),
                    'employer' => $application->getEmployer()->getId(),
                ]
            );
        }

        if ($transition === 'offer') {
            // Notify the employer being provider offered
            $this->notificationService->sendNotification(
                $employerUser,
                Notification::PROVIDER_OFFERED,
                $application->getProvider().' offered for job '.$application->getJob(),
                false,
                [
                    'application' => $application->getId(),
                    'job' => $application->getJob()->getId(),
                    'provider' => $application->getProvider()->getId(),
                    'employer' => $application->getEmployer()->getId(),
                ]
            );

            // Notify the provider being offered
            $this->notificationService->sendNotification(
                $providerUser,
                Notification::PROVIDER_OFFERED,
                'Your are offered for job '.$application->getJob(),
                true,
                [
                    'application' => $application->getId(),
                    'job' => $application->getJob()->getId(),
                    'provider' => $application->getProvider()->getId(),
                    'employer' => $application->getEmployer()->getId(),
                ]
            );
        }

        if ($transition === 'hire') {
            if($this->configManager->get('flat_fee_amount') && $this->configManager->get('flat_fee_amount') > 0) {
                $invoice = new Invoice();

                $invoice->setJob($application->getJob());
                $invoice->setEmployer($application->getEmployer());
                $invoice->setProvider($application->getProvider());
                $invoice->setAmount($this->configManager->get('flat_fee_amount'));
                $invoice->setParticular("Being provider hired");
                $invoice->setInvoiceNumber($this->invoiceNumberGenerator->generate());

                $this->em->persist($invoice);

                // Notify the employer about the new invoice
                $this->notificationService->sendNotification(
                    $employerUser,
                    Notification::INVOICE_CREATED,
                    'New invoice '.$invoice->getParticular(),
                    true,
                    [
                        'application' => $application->getId(),
                        'job' => $application->getJob()->getId(),
                        'provider' => $application->getProvider()->getId(),
                        'employer' => $application->getEmployer()->getId(),
                    ]
                );
            }

            // Notify the admin about the provider being hired
            $this->notificationService->sendNotification(
                $adminUser,
                Notification::PROVIDER_HIRED,
                $application->getProvider().' hired by '.$application->getEmployer().' for job '.$application->getJob(),
                false,
                [
                    'application' => $application->getId(),
                    'job' => $application->getJob()->getId(),
                    'provider' => $application->getProvider()->getId(),
                    'employer' => $application->getEmployer()->getId(),
                ]
            );

            // Notify the employer to fill out a review
            $this->notificationService->sendNotification(
                $employerUser,
                Notification::REVIEW_REQUEST,
                'You could write a review for your provider, '.$invoice->getProvider(),
                true,
                [
                    'application' => $application->getId(),
                    'job' => $application->getJob()->getId(),
                    'provider' => $application->getProvider()->getId(),
                    'employer' => $application->getEmployer()->getId(),
                ]
            );

            // Notify the employer being provider hired
            $this->notificationService->sendNotification(
                $employerUser,
                Notification::PROVIDER_HIRED,
                $application->getProvider().' hired for job '.$application->getJob(),
                false,
                [
                    'application' => $application->getId(),
                    'job' => $application->getJob()->getId(),
                    'provider' => $application->getProvider()->getId(),
                    'employer' => $application->getEmployer()->getId(),
                ]
            );

            // Notify the provider being hired
            $this->notificationService->sendNotification(
                $providerUser,
                Notification::PROVIDER_HIRED,
                'Your are hired for job '.$application->getJob(),
                true,
                [
                    'application' => $application->getId(),
                    'job' => $application->getJob()->getId(),
                    'provider' => $application->getProvider()->getId(),
                    'employer' => $application->getEmployer()->getId(),
                ]
            );

            // Notify the provider to fill out a review
            $this->notificationService->sendNotification(
                $providerUser,
                Notification::REVIEW_REQUEST,
                'You could write a review for your employer, '.$invoice->getEmployer(),
                true,
                [
                    'application' => $application->getId(),
                    'job' => $application->getJob()->getId(),
                    'provider' => $application->getProvider()->getId(),
                    'employer' => $application->getEmployer()->getId(),
                ]
            );

            // Reject other applications fo the job
            $applications = $this->em->getRepository(Application::class)->findBy(['job' => $application->getJob()]);

            foreach ($applications as $applicationObj) {
                if($applicationObj == $application){
                    continue;
                }
                $applicationObj->setStatus('rejected');
                $this->em->persist($applicationObj);
                $this->em->flush();
            }
        }
    }
}