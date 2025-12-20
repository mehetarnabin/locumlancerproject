<?php

namespace App\Controller\Employer;

use App\Entity\Application;
use App\Entity\Job;
use App\Entity\Message;
use App\Entity\Notification;
use App\Entity\ToDo;
use App\Service\ProfileAnalyticsService;
use App\Repository\InterviewRepository;
use App\Repository\JobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/employer')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_employer_dashboard')]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        JobRepository $jobRepository,
        InterviewRepository $interviewRepository,
        ProfileAnalyticsService $analyticsService,
    ): Response
    {
        $user = $this->getUser();

        $totalJobs = $em->createQuery("SELECT count(j.id) as total_jobs FROM App\Entity\Job j WHERE j.employer = :employer")
            ->setParameter('employer', $this->getUser()->getEmployer()->getId(), UuidType::NAME)
            ->getSingleScalarResult();

        $totalApplications = $em->createQuery("SELECT count(a.id) as total_applications FROM App\Entity\Application a JOIN a.job j WHERE j.employer = :employer")
            ->setParameter('employer', $this->getUser()->getEmployer()->getId(), UuidType::NAME)
            ->getSingleScalarResult();

        $totalInterviewedApplications = $em->createQuery("SELECT count(a.id) as total_applications FROM App\Entity\Application a JOIN a.job j WHERE j.employer = :employer AND a.status = :status")
            ->setParameter('employer', $this->getUser()->getEmployer()->getId(), UuidType::NAME)
            ->setParameter('status', 'interview')
            ->getSingleScalarResult();

        $totalHiredApplications = $em->createQuery("SELECT count(a.id) as total_applications FROM App\Entity\Application a JOIN a.job j WHERE j.employer = :employer AND a.status = :status")
            ->setParameter('employer', $this->getUser()->getEmployer()->getId(), UuidType::NAME)
            ->setParameter('status', 'hired')
            ->getSingleScalarResult();

        $statusCounts = $em->getRepository(Application::class)->getEmployerApplicationStatusCounts($this->getUser()->getEmployer()->getId());;
        $statusCountsArray = [
            'applied' => 0,
            'interview' => 0,
        ];
        foreach ($statusCounts as $row) {
            $status = strtolower($row['status'] ?? '');
            if (isset($statusCountsArray[$status])) {
                $statusCountsArray[$status] = (int) ($row['count'] ?? 0);
            }
        }
        $appliedCount = $statusCountsArray['applied'];
        $interviewCount = $statusCountsArray['interview'];

        $messages = $em->getRepository(Message::class)->findBy(['receiver' => $user], ['id' => 'DESC'], 10);
        $messagesView = array_map(function(Message $m) {
            $sender = $m->getSender();
            $provider = $sender ? $sender->getProvider() : null;
            $senderName = null;
            if ($provider && $provider->getName()) {
                $senderName = $provider->getName();
            } elseif ($sender && $sender->getName()) {
                $senderName = $sender->getName();
            }
            $providerAvatar = null;
            if ($sender && method_exists($sender, 'getProfilePictureFilename') && $sender->getProfilePictureFilename()) {
                $providerAvatar = '/uploads/' . (string) $sender->getId() . '/' . $sender->getProfilePictureFilename();
            }
            $subjectCandidate = $m->getOriginalSubject() ?: ($m->getSubject() ?: '');
            if (stripos($subjectCandidate, 'Fwd:') === 0) {
                $subjectCandidate = trim(substr($subjectCandidate, 4));
            }
            $subjectText = $subjectCandidate ?: trim(strip_tags($m->getText() ?? ''));
            return [
                'id' => (string) $m->getId(),
                'senderName' => $senderName,
                'subject' => $subjectText,
                'text' => $m->getText(),
                'isRead' => (bool) $m->isSeen(),
                'viewed' => (bool) $m->isSeen(),
                'seen' => (bool) $m->isSeen(),
                'createdAt' => $m->getCreatedAt(),
                'providerAvatar' => $providerAvatar,
            ];
        }, $messages);
        $notifications = $em->getRepository(Notification::class)->findBy(['user' => $user], ['id' => 'DESC'], 5);

        $employer = $this->getUser()->getEmployer();
        $pastJobs = $jobRepository->getEmployerPastJobs($employer->getId());
        $currentJobs = $jobRepository->getEmployerCurrentJobs($employer->getId());
        
        $skills = $analyticsService->getEmployerTopSkillsInDemand($employer);
        $resume = $analyticsService->getEmployerProfileInsights($employer, $user);

        $employerTodosRaw = $em->getRepository(ToDo::class)->findBy(
            ['employer' => $employer, 'isCompleted' => false],
            ['createdAt' => 'DESC'],
            5
        );
        $employerTodos = array_map(function(ToDo $t) {
            return [
                'id' => (string) $t->getId(),
                'title' => $t->getTitle() ?? 'Task',
                'description' => $t->getDescription() ?? '—',
                'isCompleted' => $t->isCompleted(),
                'createdAt' => $t->getCreatedAt(),
            ];
        }, $employerTodosRaw);

        $rawInterviews = $interviewRepository->getEmployerInterviews($employer->getId());
        $rawInterviews = array_filter($rawInterviews, function($iv) {
            return $iv && $iv->getDate();
        });
        $now = new \DateTimeImmutable();
        $future = [];
        $past = [];
        foreach ($rawInterviews as $iv) {
            $d = $iv->getDate();
            if (\DateTimeImmutable::createFromMutable($d) >= $now) {
                $future[] = $iv;
            } else {
                $past[] = $iv;
            }
        }
        usort($future, function($a, $b) {
            return $a->getDate() <=> $b->getDate();
        });
        usort($past, function($a, $b) {
            return $b->getDate() <=> $a->getDate();
        });
        $selected = array_slice($future, 0, 5);
        if (count($selected) < 5) {
            $selected = array_merge($selected, array_slice($past, 0, 5 - count($selected)));
        }
        $upcomingInterviews = [];
        foreach ($selected as $iv) {
            $app = $iv->getApplication();
            $job = $app ? $app->getJob() : null;
            $empr = $job ? $job->getEmployer() : null;
            $company = null;
            if ($empr) {
                $company = method_exists($empr, 'getCompanyName') ? $empr->getCompanyName() : ($empr->getName() ?? null);
            }
            $upcomingInterviews[] = [
                'company' => $company ?: '—',
                'position' => $job ? ($job->getTitle() ?? '—') : '—',
                'date' => $iv->getDate(),
                'jobId' => $job ? $job->getId() : null,
            ];
        }

        return $this->render('employer/dashboard.html.twig', [
            'totalJobs' => $totalJobs,
            'totalApplications' => $totalApplications,
            'totalHiredApplications' => $totalHiredApplications,
            'totalInterviewedApplications' => $totalInterviewedApplications,
            'statusCounts'=> $statusCounts,
            'messages' => $messagesView,
            'notifications' => $notifications,
            'currentJobs' => $currentJobs,
            'pastJobs' => $pastJobs,
            'upcomingInterviews' => $upcomingInterviews,
            'employerTodos' => $employerTodos,
            'appliedCount' => $appliedCount,
            'interviewCount' => $interviewCount,
            'skills' => $skills,
            'resume' => $resume,
        ]);
    }

    #[Route('/todos/{id}/toggle', name: 'app_employer_dashboard_todo_toggle', methods: ['POST'])]
    public function toggleTodo(ToDo $todo, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $employer = $user ? $user->getEmployer() : null;
        
        if (!$employer || $todo->getEmployer() !== $employer) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $todo->setIsCompleted(!$todo->isCompleted());
        $em->flush();

        return $this->json(['success' => true, 'isCompleted' => $todo->isCompleted()]);
    }
}
