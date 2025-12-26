<?php

namespace App\Controller\Recruiter;

use App\Entity\Notification;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/recruiter')]
class NotificationController extends AbstractController
{
    #[Route('/notifications', name: 'app_recruiter_notifications')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $offset = $request->query->get('page', 1);
        $perPage = $request->get('per_page', 25);
        $filters = [
            'userType' => User::TYPE_EMPLOYER,
            'user' => $user->getId()
        ];

        $notificationRepository = $em->getRepository(Notification::class);
        $notifications = $notificationRepository->getAll($offset, $perPage, $filters);
        $currentNotifications = iterator_to_array($notifications->getCurrentPageResults(), false);

        foreach ($currentNotifications as $notification) {
            if (!$notification->isSeen()) {
                $notification->setSeen(true);
                $em->persist($notification);
            }
        }
        $em->flush();

        $notificationOptions = Notification::getAllEmployerNotificationTypes();

        $preferenceSections = [
            'job_activity' => [
                'title' => 'Job Activity',
                'description' => 'All updates tied to job applications, interviews, and hiring decisions.',
                'types' => [
                    Notification::JOB_APPLIED,
                    Notification::INTERVIEW_SCHEDULED,
                    Notification::PROVIDER_SHORTLIST,
                    Notification::PROVIDER_OFFERED,
                    Notification::PROVIDER_HIRED,
                ],
            ],
            'compliance' => [
                'title' => 'Compliance & Documents',
                'description' => 'Document submissions, credentialing status, and compliance updates.',
                'types' => [
                    Notification::ONE_FILE_PROVIDED,
                    Notification::DOCUMENT_PROVIDED,
                    Notification::PROVIDER_IN_REVIEW,
                ],
            ],
            'communications' => [
                'title' => 'Messages & Reviews',
                'description' => 'Direct messages, review prompts, and feedback reminders.',
                'types' => [
                    Notification::MESSAGE_RECEIVED,
                    Notification::REVIEW_REQUEST,
                    Notification::EMPLOYER_REVIEWED,
                ],
            ],
            'platform_updates' => [
                'title' => 'Platform Updates',
                'description' => 'General updates, invoices, payments, and product news.',
                'types' => [
                    Notification::INVOICE_CREATED,
                    Notification::INVOICE_PAID,
                    Notification::INVOICE_OVERDUE,
                    Notification::JOB_POSTED,
                ],
            ],
        ];

        $typeToSection = [];
        foreach ($preferenceSections as $sectionKey => $section) {
            foreach ($section['types'] as $type) {
                $typeToSection[$type] = $sectionKey;
            }
        }

        foreach (array_keys($notificationOptions) as $typeKey) {
            if (!isset($typeToSection[$typeKey])) {
                $typeToSection[$typeKey] = 'platform_updates';
                $preferenceSections['platform_updates']['types'][] = $typeKey;
            }
        }

        foreach ($preferenceSections as $sectionKey => &$section) {
            $sectionTypeKeys = $section['types'];
            $section['options'] = array_intersect_key(
                $notificationOptions,
                array_flip($sectionTypeKeys)
            );
        }
        unset($section);

        $notificationsBySection = [];
        foreach (array_keys($preferenceSections) as $sectionKey) {
            $notificationsBySection[$sectionKey] = [];
        }

        foreach ($currentNotifications as $notification) {
            $type = $notification->getNotificationType();
            $sectionKey = $typeToSection[$type] ?? 'platform_updates';
            $notificationsBySection[$sectionKey][] = $notification;
        }

        $totalNotifications = $notificationRepository->count([
            'user' => $user,
            'userType' => User::TYPE_EMPLOYER,
        ]);
        $unreadNotifications = $notificationRepository->count([
            'user' => $user,
            'userType' => User::TYPE_EMPLOYER,
            'seen' => false,
        ]);

        $weeklyNotifications = (int) $notificationRepository->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.user = :user')
            ->setParameter('user', $user->getId(), UuidType::NAME)
            ->andWhere('n.userType = :userType')
            ->setParameter('userType', User::TYPE_EMPLOYER)
            ->andWhere('n.createdAt >= :since')
            ->setParameter('since', new DateTimeImmutable('-7 days'))
            ->getQuery()
            ->getSingleScalarResult();

        $latestNotificationAt = null;
        if (count($currentNotifications) > 0) {
            $latestNotificationAt = $currentNotifications[0]->getCreatedAt();
        }

        $notificationStats = [
            'total' => $totalNotifications,
            'unread' => $unreadNotifications,
            'weekly' => $weeklyNotifications,
            'lastActivity' => $latestNotificationAt,
        ];

        return $this->render('recruiter/notification/list.html.twig', [
            'employer' => $user->getEmployer(),
            'notificationsPager' => $notifications,
            'currentNotifications' => $currentNotifications,
            'notificationOptions' => $notificationOptions,
            'preferenceSections' => $preferenceSections,
            'notificationsBySection' => $notificationsBySection,
            'notificationStats' => $notificationStats,
        ]);
    }
}
