<?php

namespace App\EventListener;

use App\Entity\WorkflowLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Workflow\Event\Event;

class WorkflowLoggerListener
{
    public function __construct(private EntityManagerInterface $em, private Security $security)
    {}

    public function onEntered(Event $event)
    {
        $subject = $event->getSubject();
        $transition = $event->getTransition();
        $froms = implode(', ', $transition->getFroms());
        $to = $transition->getTos()[0];

        $log = new WorkflowLog();
        $log->setSubjectClass($this->em->getClassMetadata(get_class($subject))->getName());
        $log->setSubjectId($subject->getId());
        $log->setTransition($transition->getName());
        $log->setFromState($froms);
        $log->setToState($to);
        $log->setTransitionedAt(new \DateTime());
        $log->setTransitionedBy($this->security->getUser());

        $this->em->persist($log);
//        $this->em->flush();
    }
}