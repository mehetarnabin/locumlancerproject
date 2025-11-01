<?php

namespace App\EventListener;

use App\Entity\Job;
use Symfony\Component\Workflow\Event\Event;
use App\Message\MatchingJobNotificationMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class JobWorkflowSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $bus
    ){}

    public static function getSubscribedEvents()
    {
        return [
            'workflow.job_workflow.entered' => 'onEnter'
        ];
    }

    public function onEnter(Event $event): void
    {
        /** @var Job $job */
        $job = $event->getSubject();

        $transition = $event->getTransition()->getName();

        if ($transition === 'publish') {
            // Notify the providers for job match
            $this->bus->dispatch(new MatchingJobNotificationMessage($job->getId()));
        }
    }
}