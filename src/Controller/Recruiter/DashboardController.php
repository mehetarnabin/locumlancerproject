<?php

namespace App\Controller\Recruiter;

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

#[Route('/recruiter')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_recruiter_dashboard')]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        JobRepository $jobRepository,
        InterviewRepository $interviewRepository,
        ProfileAnalyticsService $analyticsService,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $recruiter = $user->getRecruiter();
        $employer = $user->getEmployer();

        // Dual View Logic: determine context
        $isRecruiterView = true; // Default to Recruiter view for now
        // If we want to toggle, we can check $request->query->get('view')

        if ($recruiter) {
            // Recruiter Context
            $recruiterId = $recruiter->getId();

            // Try to resolve Employer if not set on User (e.g. via assigned jobs)
            if (!$employer) {
                $assignedJob = $em->createQuery("SELECT j FROM App\Entity\Job j JOIN App\Entity\JobRecruiter jr WITH jr.job = j WHERE jr.recruiter = :recruiter")
                    ->setParameter('recruiter', $recruiterId, UuidType::NAME)
                    ->setMaxResults(1)
                    ->getOneOrNullResult();

                if ($assignedJob) {
                    $employer = $assignedJob->getEmployer();
                }
            }

            // Total Jobs Assigned to Recruiter
            $totalJobs = $em->createQuery("SELECT count(j.id) FROM App\Entity\Job j JOIN App\Entity\JobRecruiter jr WITH jr.job = j WHERE jr.recruiter = :recruiter")
                ->setParameter('recruiter', $recruiterId, UuidType::NAME)
                ->getSingleScalarResult();

            // Applications where Recruiter is the 'owner' (submitted by them)
            $totalApplications = $em->createQuery("SELECT count(a.id) FROM App\Entity\Application a WHERE a.recruiter = :recruiter")
                ->setParameter('recruiter', $recruiterId, UuidType::NAME)
                ->getSingleScalarResult();

            $totalInterviewedApplications = $em->createQuery("SELECT count(a.id) FROM App\Entity\Application a WHERE a.recruiter = :recruiter AND a.status = :status")
                ->setParameter('recruiter', $recruiterId, UuidType::NAME)
                ->setParameter('status', 'interview')
                ->getSingleScalarResult();

            $totalHiredApplications = $em->createQuery("SELECT count(a.id) FROM App\Entity\Application a WHERE a.recruiter = :recruiter AND a.status = :status")
                ->setParameter('recruiter', $recruiterId, UuidType::NAME)
                ->setParameter('status', 'hired')
                ->getSingleScalarResult();

            // Status Counts (Custom query for Recruiter)
            $statusCountsRaw = $em->createQuery("SELECT a.status, count(a.id) as count FROM App\Entity\Application a WHERE a.recruiter = :recruiter GROUP BY a.status")
                ->setParameter('recruiter', $recruiterId, UuidType::NAME)
                ->getResult();

            $statusCounts = [];
            foreach ($statusCountsRaw as $row) {
                // normalize structure to match existing template expectation
                $statusCounts[] = ['status' => $row['status'], 'count' => $row['count']];
            }

            // Jobs Lists
            // Past Jobs (Assigned and Closed/Expired?) - For now, show all assigned
            $currentJobs = $em->createQuery("SELECT j FROM App\Entity\Job j JOIN App\Entity\JobRecruiter jr WITH jr.job = j WHERE jr.recruiter = :recruiter ORDER BY j.createdAt DESC")
                ->setParameter('recruiter', $recruiterId, UuidType::NAME)
                ->setMaxResults(5)
                ->getResult();
            $pastJobs = []; // Placeholder

            // Interviews
            // Fetch interviews for applications owned by recruiter
            $rawInterviews = $em->createQuery("SELECT i FROM App\Entity\Interview i JOIN i.application a WHERE a.recruiter = :recruiter")
                ->setParameter('recruiter', $recruiterId, UuidType::NAME)
                ->getResult();

            // Todos - Linked to Recruiter?
            // Currently Todo entity has 'employer' field. We might need 'recruiter' field on Todo or filter by user.
            // For now, empty or fetch User's personal todos if implemented.
            // View 1: Employer View (Jobs assigned to me)
            // Already handled by $currentJobs query above (Line 90).

            // View 2: Applicant View (Candidates I am managing)
            $myApplications = $em->getRepository(Application::class)->findBy(['recruiter' => $recruiter], ['id' => 'DESC']);

            // Status counts correction:
            // $statusCounts query (Line 78) already counts "Applicant View" statuses (where a.recruiter = :recruiter).
            // So statusCounts reflects Candidates managed.



            // Recruiter To-Do Handling
            $employerTodosRaw = $em->getRepository(ToDo::class)->findBy(
                ['recruiter' => $recruiter, 'isCompleted' => false],
                ['createdAt' => 'DESC'],
                5
            );
            $employerTodos = array_map(function (ToDo $t) {
                return [
                    'id' => (string) $t->getId(),
                    'title' => $t->getTitle() ?? 'Task',
                    'description' => $t->getDescription() ?? '—',
                    'isCompleted' => $t->isCompleted(),
                    'createdAt' => $t->getCreatedAt(),
                ];
            }, $employerTodosRaw);
        } elseif ($employer) {
            $myApplications = []; // Fallback
            // Employer Context (Original Logic)
            $totalJobs = $em->createQuery("SELECT count(j.id) as total_jobs FROM App\Entity\Job j WHERE j.employer = :employer")
                ->setParameter('employer', $employer->getId(), UuidType::NAME)
                ->getSingleScalarResult();

            $totalApplications = $em->createQuery("SELECT count(a.id) as total_applications FROM App\Entity\Application a JOIN a.job j WHERE j.employer = :employer")
                ->setParameter('employer', $employer->getId(), UuidType::NAME)
                ->getSingleScalarResult();

            $totalInterviewedApplications = $em->createQuery("SELECT count(a.id) as total_applications FROM App\Entity\Application a JOIN a.job j WHERE j.employer = :employer AND a.status = :status")
                ->setParameter('employer', $employer->getId(), UuidType::NAME)
                ->setParameter('status', 'interview')
                ->getSingleScalarResult();

            $totalHiredApplications = $em->createQuery("SELECT count(a.id) as total_applications FROM App\Entity\Application a JOIN a.job j WHERE j.employer = :employer AND a.status = :status")
                ->setParameter('employer', $employer->getId(), UuidType::NAME)
                ->setParameter('status', 'hired')
                ->getSingleScalarResult();

            $statusCounts = $em->getRepository(Application::class)->getEmployerApplicationStatusCounts($employer->getId());

            $currentJobs = $jobRepository->getEmployerCurrentJobs($employer->getId());
            $pastJobs = $jobRepository->getEmployerPastJobs($employer->getId());

            $rawInterviews = $interviewRepository->getEmployerInterviews($employer->getId());

            $employerTodosRaw = $em->getRepository(ToDo::class)->findBy(
                ['employer' => $employer, 'isCompleted' => false],
                ['createdAt' => 'DESC'],
                5
            );
        } else {
            // Fallback for empty/new accounts
            $totalJobs = 0;
            $totalApplications = 0;
            $totalInterviewedApplications = 0;
            $totalHiredApplications = 0;
            $statusCounts = [];
            $currentJobs = [];
            $pastJobs = [];
            $rawInterviews = [];
            $employerTodosRaw = [];
        }

        // Processing Logic (Shared)
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

        // Messages (User centric)
        $messages = $em->getRepository(Message::class)->findBy(['receiver' => $user], ['id' => 'DESC'], 10);
        $messagesView = array_map(function (Message $m) {
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

        // Analytics (Fallback or Specific)
        if ($employer) {
            $skills = $analyticsService->getEmployerTopSkillsInDemand($employer);
            $resume = $analyticsService->getEmployerProfileInsights($employer, $user);
        } else {
            // Placeholder for Recruiter specific analytics
            $skills = [];
            $resume = [];
        }


        $employerTodos = array_map(function (ToDo $t) {
            return [
                'id' => (string) $t->getId(),
                'title' => $t->getTitle() ?? 'Task',
                'description' => $t->getDescription() ?? '—',
                'isCompleted' => $t->isCompleted(),
                'createdAt' => $t->getCreatedAt(),
            ];
        }, $employerTodosRaw);

        // Interview Processing
        $rawInterviews = array_filter($rawInterviews, function ($iv) {
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
        usort($future, function ($a, $b) {
            return $a->getDate() <=> $b->getDate();
        });
        usort($past, function ($a, $b) {
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

        return $this->render('recruiter/dashboard.html.twig', [
            'totalJobs' => $totalJobs,
            'totalApplications' => $totalApplications,
            'totalHiredApplications' => $totalHiredApplications,
            'totalInterviewedApplications' => $totalInterviewedApplications,
            'statusCounts' => $statusCounts,
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
            'myApplications' => $myApplications ?? [],
        ]);
    }

    #[Route('/todos/{id}/toggle', name: 'app_recruiter_dashboard_todo_toggle', methods: ['POST'])]
    public function toggleTodo(ToDo $todo, EntityManagerInterface $em): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $employer = $user ? $user->getEmployer() : null;
        $recruiter = $user ? $user->getRecruiter() : null;

        // Allow if owner is employer OR if TODO belongs to user
        // Refined check:
        // If todo has employer, check if user matches employer.
        // If todo has user, check if user matches.

        $isAuthorized = false;
        if ($todo->getEmployer() && $employer && $todo->getEmployer() === $employer) {
            $isAuthorized = true;
        }
        // Assuming Todo might have user field in future or we check implicit ownership
        // For now, adhere to existing logic plus Recruiter

        if (!$isAuthorized) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $todo->setIsCompleted(!$todo->isCompleted());
        $em->flush();

        return $this->json(['success' => true, 'isCompleted' => $todo->isCompleted()]);
    }
}
