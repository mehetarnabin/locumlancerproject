<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Message\SendEmailMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
    ){}

    public function sendNotification(User $user, string $type, string $message, bool $sendEmail = false, $extraData = [])
    {
        if($user->getUserType() == User::TYPE_PROVIDER and $user->getProvider()){
            if(empty($user->getProvider()->getNotificationPreferences())){
                return;
            }
            if(!array_key_exists($type, $user->getProvider()->getNotificationPreferences())){
                return;
            }
            if(!$user->getProvider()->getNotificationPreferences()[$type]){
                return;
            }
        }

        $notification = new Notification();
        $notification->setUser($user);
        $notification->setUserType($user->getUserType());
        $notification->setNotificationType($type);
        $notification->setMessage($message);
        $notification->setExtraData($extraData);

        $this->em->persist($notification);
        $this->em->flush();

        if($sendEmail){
            $emailMessage = new SendEmailMessage(
                $user->getEmail(),
                ucwords(str_replace('_', ' ', $type)),
                $message
            );

            $this->bus->dispatch($emailMessage);

//            $subject = ucwords(str_replace('_', ' ', $type));
//            $email = (new TemplatedEmail())
//                ->from(new Address($this->params->get('from_email'), $this->params->get('from_name')))
//                ->to($user->getEmail())
//                ->subject($subject)
//                ->htmlTemplate('emails/notification_email.html.twig')
//                ->context([
//                    'subject' => $subject,
//                    'message' => $message,
//                ])
//            ;
//
//            try{
//                $this->mailer->send($email);
//            }catch (\Exception $e){
//
//            }
        }
    }
}
