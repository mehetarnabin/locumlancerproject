<?php

namespace App\Command;

use App\Entity\Payment;
use App\Entity\PackageSubscription;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-payments',
    description: 'Check payment and subscription records in database',
)]
class CheckPaymentsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Payment Database Check');

        // Check payments
        $payments = $this->entityManager->getRepository(Payment::class)
            ->findBy([], ['createdAt' => 'DESC'], 10);

        $io->section('Recent Payments (Last 10)');
        
        if (empty($payments)) {
            $io->warning('No payments found in database!');
        } else {
            $paymentData = [];
            foreach ($payments as $payment) {
                $paymentData[] = [
                    $payment->getId(),
                    $payment->getUser()->getEmail(),
                    $payment->getPackage()->getName(),
                    '$' . $payment->getAmount(),
                    $payment->getStatus(),
                    $payment->getStripeSessionId() ?? 'N/A',
                    $payment->getCreatedAt()->format('Y-m-d H:i:s'),
                ];
            }
            
            $io->table(
                ['ID', 'User', 'Package', 'Amount', 'Status', 'Session ID', 'Created'],
                $paymentData
            );
        }

        // Check subscriptions
        $subscriptions = $this->entityManager->getRepository(PackageSubscription::class)
            ->findBy([], ['startDate' => 'DESC'], 10);

        $io->section('Recent Subscriptions (Last 10)');
        
        if (empty($subscriptions)) {
            $io->warning('No subscriptions found in database!');
        } else {
            $subscriptionData = [];
            foreach ($subscriptions as $sub) {
                $subscriptionData[] = [
                    $sub->getId(),
                    $sub->getUser()->getEmail(),
                    $sub->getPackage()->getName(),
                    $sub->getStatus(),
                    $sub->getTransactionId() ?? 'N/A',
                    $sub->getStartDate()->format('Y-m-d H:i:s'),
                ];
            }
            
            $io->table(
                ['ID', 'User', 'Package', 'Status', 'Transaction ID', 'Start Date'],
                $subscriptionData
            );
        }

        // Summary
        $io->section('Summary');
        $io->text([
            sprintf('Total Payments: %d', count($payments)),
            sprintf('Total Subscriptions: %d', count($subscriptions)),
        ]);

        if (empty($payments) && empty($subscriptions)) {
            $io->error('No payment data found! Webhooks may not be processing correctly.');
            $io->text([
                'Possible causes:',
                '1. Webhook metadata is missing (test events)',
                '2. Package or User not found in database',
                '3. Database transaction failed',
                '',
                'Check your logs for more details.',
            ]);
            
            return Command::FAILURE;
        }

        $io->success('Payment check completed!');
        return Command::SUCCESS;
    }
}
