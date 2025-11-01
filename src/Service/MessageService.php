<?php

namespace App\Service;

use App\Entity\Employer;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;

class MessageService
{
    public function __construct(
        private EntityManagerInterface $em
    ){}

    public function sendMessage(
        $senderId,
        $receiverId,
        $employerId,
        $messageText
    )
    {
        $message = new Message();

        $message->setSender($this->em->getRepository(User::class)->find($senderId));
        $message->setReceiver($this->em->getRepository(User::class)->find($receiverId));
        $message->setEmployer($this->em->getRepository(Employer::class)->find($employerId));
        $message->setText($messageText);

        $this->em->persist($message);
        $this->em->flush();
    }
}