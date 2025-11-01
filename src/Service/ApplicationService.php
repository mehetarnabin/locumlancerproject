<?php

namespace App\Service;

use App\Entity\Job;
use App\Entity\User;
use App\Entity\Application;
use App\Event\ApplicationEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ApplicationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private EventDispatcherInterface $dispatcher
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
}