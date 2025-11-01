<?php

namespace App\MessageHandler;

use App\Entity\User;
use App\Entity\Invoice;
use App\Entity\Notification;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use App\Message\InvoiceOverdueNotificationMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class InvoiceOverdueNotificationHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotificationService $notificationService,
    ){}

    public function __invoke(InvoiceOverdueNotificationMessage $message): void
    {
        $sevenDaysBefore = (new \DateTime())->modify('-7 days');
        $overdueInvoices = $this->em->getRepository(Invoice::class)->findOverdueInvoices($sevenDaysBefore);

        foreach ($overdueInvoices as $invoice) {
            $employerUser = $this->em->getRepository(User::class)->findOneBy(['employer' => $invoice->getEmployer()]);
            $this->notificationService->sendNotification(
                $employerUser,
                Notification::INVOICE_OVERDUE,
                "Your invoice for hiring '{$invoice->getProvider()}' is pending. Please make payment on time for uninterrupted service.",
                true,
                [
                    'invoice' => $invoice->getId(),
                    'employer' => $invoice->getEmployer()?->getId(),
                    'provider' => $invoice->getProvider()?->getId(),
                ]
            );
        }
    }
}