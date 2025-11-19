<?php

namespace App\MessageHandler;

use App\Message\SendEmailMessage;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendEmailMessageHandler
{
    public function __construct(
        private MailerInterface $mailer,
        private readonly ParameterBagInterface $params,
    ) {}

    public function __invoke(SendEmailMessage $message)
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->params->get('from_email'), $this->params->get('from_name')))
            ->to($message->getTo())
            ->subject($message->getSubject())
            ->htmlTemplate('emails/notification_email.html.twig')
            ->context([
                'subject' => $message->getSubject(),
                'message' => $message->getMessage(),
            ]);

        $this->mailer->send($email);
    }
}