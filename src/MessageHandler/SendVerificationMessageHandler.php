<?php

namespace App\MessageHandler;

use App\Entity\User;
use App\Security\EmailVerifier;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mime\Address;
use App\Message\SendVerificationMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendVerificationMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EmailVerifier          $emailVerifier,
        private readonly ParameterBagInterface  $params,
    )
    {
    }

    public function __invoke(SendVerificationMessage $message)
    {
        $user = $this->em->find(User::class, $message->userId);

        $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
            (new TemplatedEmail())
                ->from(new Address($this->params->get('from_email'), $this->params->get('from_name')))
                ->to($user->getEmail())
                ->subject('Please Confirm your Email')
                ->htmlTemplate("emails/confirmation_email.html.twig")
                ->context([
                    'name' => $user->getName()
                ])
        );
    }
}
