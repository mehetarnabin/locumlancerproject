<?php

namespace App\Service;

use App\Entity\Job;
use App\Entity\User;
use App\Entity\Notification;
use App\Entity\Application;
use App\Event\ApplicationEvent;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ApplicationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private EventDispatcherInterface $dispatcher,
        private UserRepository $userRepository
    ){}

    public function createApplication(Job $job, User $user)
    {
        $application = new Application();

        $application->setJob($job);
        $application->setProvider($user->getProvider());
        $application->setEmployer($job->getEmployer());

        $this->em->persist($application);
        $this->em->flush();

        $this->dispatcher->dispatch(new ApplicationEvent($application), ApplicationEvent::APPLICATION_CREATED);
    }

    public function markAsHired(Application $application): void
    {
        $application->setStatus('accepted');
        $application->setHiredAt(new \DateTime());
        
        $this->em->persist($application);
        $this->em->flush();

        // Create notifications for admin
        $this->createHireNotifications($application);
    }

    private function createHireNotifications(Application $application): void
    {
        $job = $application->getJob();
        $provider = $application->getProvider();
        $employer = $application->getEmployer();

        // Get all admin users - use the correct query for roles array
        $adminUsers = $this->userRepository->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->getQuery()
            ->getResult();

        foreach ($adminUsers as $adminUser) {
            $notification = new Notification();
            $notification->setUser($adminUser);
            $notification->setNotificationType(Notification::PROVIDER_HIRED);
            $notification->setUserType('admin');
            
            // Use getName() instead of getCompanyName() for Provider
            $providerName = $provider->getName() ?? 'Unknown Provider';
            
            // For employer, check if getCompanyName() exists, otherwise use getName()
            $employerName = method_exists($employer, 'getCompanyName') 
                ? ($employer->getCompanyName() ?? 'Unknown Employer')
                : ($employer->getName() ?? 'Unknown Employer');
            
            $notification->setMessage(
                sprintf(
                    'Provider %s has been hired for job "%s" by employer %s',
                    $providerName,
                    $job->getTitle(),
                    $employerName
                )
            );
            $notification->setSeen(false);

            $this->em->persist($notification);
        }

        $this->em->flush();
    }
}