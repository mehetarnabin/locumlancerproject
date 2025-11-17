<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\DocumentRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug-document-requests',
    description: 'Debug document requests to see why they might not be showing.'
)]
class DebugDocumentRequestsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DocumentRequestRepository $documentRequestRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Find provider user
        $userRepository = $this->em->getRepository(User::class);
        $providerUser = $userRepository->findOneBy(['email' => 'testprovider@flexzob.com']);

        if (!$providerUser) {
            $io->error('Provider user not found.');
            return Command::FAILURE;
        }

        $provider = $providerUser->getProvider();
        if (!$provider) {
            $io->error('Provider entity not found.');
            return Command::FAILURE;
        }

        $io->title('Debugging Document Requests');

        // Get all document requests for this provider (without filters)
        $allRequests = $this->em->getRepository(\App\Entity\DocumentRequest::class)
            ->createQueryBuilder('dr')
            ->where('dr.provider = :provider')
            ->setParameter('provider', $provider)
            ->getQuery()
            ->getResult();

        $io->section('All Document Requests (unfiltered)');
        $io->writeln(sprintf('Total: %d', count($allRequests)));

        foreach ($allRequests as $request) {
            $io->writeln(sprintf(
                '- ID: %s | Name: %s | Application: %s | Job: %s',
                $request->getId(),
                $request->getName(),
                $request->getApplication() ? $request->getApplication()->getId() : 'NULL',
                $request->getApplication() && $request->getApplication()->getJob() 
                    ? $request->getApplication()->getJob()->getTitle() 
                    : 'NULL'
            ));

            if ($request->getApplication()) {
                $app = $request->getApplication();
                $job = $app->getJob();
                
                $io->writeln(sprintf(
                    '  Application Status: %s',
                    $app->getStatus()
                ));

                if ($job) {
                    $io->writeln(sprintf(
                        '  Job Verified: %s | Blocked: %s | Expiration: %s',
                        $job->getVerified() ? 'YES' : 'NO',
                        $job->getBlocked() ? 'YES' : 'NO',
                        $job->getExpirationDate() 
                            ? $job->getExpirationDate()->format('Y-m-d') 
                            : 'NULL'
                    ));
                }
            }
        }

        // Get filtered document requests (using the repository method)
        $io->section('Filtered Document Requests (using repository method)');
        $filteredRequests = $this->documentRequestRepository->getDocumentRequests($provider->getId());
        $io->writeln(sprintf('Total: %d', count($filteredRequests)));

        foreach ($filteredRequests as $request) {
            $io->writeln(sprintf(
                '- ID: %s | Name: %s',
                $request->getId(),
                $request->getName()
            ));
        }

        return Command::SUCCESS;
    }
}

