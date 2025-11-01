<?php

namespace App\Controller\Provider;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/provider')]
class NotificationController extends AbstractController
{
    #[Route('/notifications', name: 'app_provider_notifications')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $offset = $request->query->get('page', 1);
        $perPage = $request->get('per_page', 25);
        $filters = [
            'userType' => User::TYPE_PROVIDER,
            'user' => $user->getId()
        ];

        $notifications = $em->getRepository(Notification::class)->getAll($offset, $perPage, $filters);

        foreach ($notifications->getCurrentPageResults() as $notification) {
            $notification->setSeen(true);
            $em->persist($notification);
            $em->flush();
        }

        $notificationOptions = Notification::getAllProviderNotificationTypes();

        return $this->render('provider/notification/list.html.twig', [
            'provider' => $user->getProvider(),
            'notifications' => $notifications,
            'notificationOptions' => $notificationOptions,
        ]);
    }
}