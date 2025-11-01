<?php

namespace App\Controller\Employer;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/employer')]
class NotificationController extends AbstractController
{
    #[Route('/notifications', name: 'app_employer_notifications')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $offset = $request->query->get('page', 1);
        $perPage = $request->get('per_page', 25);
        $filters = [
            'userType' => User::TYPE_EMPLOYER,
            'user' => $user->getId()
        ];

        $notifications = $em->getRepository(Notification::class)->getAll($offset, $perPage, $filters);

        foreach ($notifications as $notification) {
            $notification->setSeen(true);
            $em->persist($notification);
        }

        $em->flush();

        return $this->render('employer/notification/list.html.twig', [
            'notifications' => $notifications,
        ]);
    }
}