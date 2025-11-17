<?php

namespace App\Command;

use App\Entity\Application;
use App\Entity\DocumentRequest;
use App\Entity\Job;
use App\Entity\Provider;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-dummy-document-request',
    description: 'Creates a dummy document request for testing purposes.'
)]
class CreateDummyDocumentRequestCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Find provider user
        $userRepository = $this->em->getRepository(User::class);
        $providerUser = $userRepository->findOneBy(['email' => 'testprovider@flexzob.com']);

        if (!$providerUser) {
            $io->error('Provider user with email testprovider@flexzob.com not found.');
            return Command::FAILURE;
        }

        $provider = $providerUser->getProvider();
        if (!$provider) {
            $io->error('Provider entity not found for user.');
            return Command::FAILURE;
        }

        // Find or create a job
        $jobRepository = $this->em->getRepository(Job::class);
        $job = $jobRepository->findOneBy(['verified' => true, 'blocked' => false]);

        if (!$job) {
            $io->warning('No verified job found. Creating a test job...');
            
            // Find employer user
            $employerUser = $userRepository->findOneBy(['email' => 'testemployer@flexzob.com']);
            if (!$employerUser || !$employerUser->getEmployer()) {
                $io->error('Employer user not found. Please create one first.');
                return Command::FAILURE;
            }

            // Create a simple job
            $job = new Job();
            $job->setTitle('Test Job for Document Request');
            $job->setDescription('This is a test job for document request testing.');
            $job->setStatus(Job::JOB_STATUS_PUBLISHED);
            $job->setUser($employerUser);
            $job->setEmployer($employerUser->getEmployer());
            $job->setCountry('USA');
            $job->setState('California');
            $job->setCity('Los Angeles');
            $job->setVerified(true);
            $job->setBlocked(false);
            $job->setJobId('TEST-' . strtoupper(uniqid()));
            // Set expiration date to future to ensure it passes the query filter
            $job->setExpirationDate(new \DateTime('+6 months'));
            
            $this->em->persist($job);
            $this->em->flush();
        } else {
            // Ensure existing job has a future expiration date
            if (!$job->getExpirationDate() || $job->getExpirationDate() < new \DateTime()) {
                $job->setExpirationDate(new \DateTime('+6 months'));
                $this->em->flush();
            }
        }

        // Find or create an application
        $applicationRepository = $this->em->getRepository(Application::class);
        $application = $applicationRepository->findOneBy([
            'provider' => $provider,
            'job' => $job
        ]);

        if (!$application) {
            $io->info('Creating a new application...');
            $application = new Application();
            $application->setProvider($provider);
            $application->setJob($job);
            $application->setEmployer($job->getEmployer());
            $application->setStatus('applied');
            
            $this->em->persist($application);
            $this->em->flush();
        } else {
            // Ensure application status is valid for document requests
            $validStatuses = ['applied', 'in_review', 'interview', 'offered', 'hired'];
            if (!in_array($application->getStatus(), $validStatuses)) {
                $io->info('Updating application status to "applied"...');
                $application->setStatus('applied');
                $this->em->flush();
            }
        }

        // Check if document request already exists
        $documentRequestRepository = $this->em->getRepository(DocumentRequest::class);
        $existingRequest = $documentRequestRepository->findOneBy([
            'provider' => $provider,
            'application' => $application,
            'name' => 'Driver\'s license'
        ]);

        if ($existingRequest) {
            $io->warning('Document request already exists. Skipping creation.');
            $io->info(sprintf(
                'Existing document request ID: %s for provider %s on job "%s"',
                $existingRequest->getId(),
                $providerUser->getEmail(),
                $job->getTitle()
            ));
            return Command::SUCCESS;
        }

        // Create document request
        $documentRequest = new DocumentRequest();
        $documentRequest->setName('Driver\'s license');
        $documentRequest->setProvider($provider);
        $documentRequest->setApplication($application);
        // Don't set document - it should be null initially

        $this->em->persist($documentRequest);
        $this->em->flush();

        $io->success(sprintf(
            'Successfully created dummy document request: "%s" for provider %s on job "%s"',
            $documentRequest->getName(),
            $providerUser->getEmail(),
            $job->getTitle()
        ));

        return Command::SUCCESS;
    }
}

