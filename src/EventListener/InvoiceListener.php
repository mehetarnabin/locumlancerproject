<?php

namespace App\EventListener;

use App\Entity\Notification;
use App\Entity\User;
use App\Event\InvoiceEvent;
use App\Event\JobEvent;
use App\Service\ConfigManager;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class InvoiceListener
{
    public function __construct(
        private NotificationService $notificationService,
        private EntityManagerInterface $em,
        private ConfigManager $configManager
    )
    {}

    #[AsEventListener(event: InvoiceEvent::INVOICE_PAID)]
    public function onInvoicePaid(InvoiceEvent $event): void
    {
        $invoice = $event->getInvoice();
        $adminUser = $this->em->getRepository(User::class)->findOneBy(['userType' => User::TYPE_ADMIN], ['createdAt' => 'ASC']);
        $employer = $invoice->getEmployer();

        // Notify the admin about the invoice being paid
        $this->notificationService->sendNotification(
            $adminUser,
            Notification::INVOICE_PAID,
            'Invoice for $'.$invoice->getAmount().' paid by '.$employer->getName(),
            true,
            [
                'invoice' => $invoice->getId(),
                'employer' => $employer->getId(),
            ]
        );

        // Notify the employer about the invoice being paid
        $employerUser = $this->em->getRepository(User::class)->findOneBy(['employer' => $employer]);
        $this->notificationService->sendNotification(
            $employerUser,
            Notification::INVOICE_PAID,
            'Invoice for $'.$invoice->getAmount().' has been paid',
            true,
            [
                'invoice' => $invoice->getId(),
                'employer' => $employer->getId(),
            ]
        );

        // Notify the provider about the cashback being paid
        if($this->configManager->get('cashback_percent') && $this->configManager->get('cashback_percent') > 0) {
            $providerUser = $invoice->getProvider()->getUser();
            $this->notificationService->sendNotification(
                $providerUser,
                Notification::CASHBACK_CREATED,
                'You got cashback $' . $invoice->getAmount() * $this->configManager->get('cashback_percent') / 100,
                true,
                [
                    'invoice' => $invoice->getId(),
                    'employer' => $employer->getId(),
                ]
            );
        }
    }
}
