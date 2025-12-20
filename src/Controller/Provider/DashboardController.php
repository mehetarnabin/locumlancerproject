<?php

namespace App\Controller\Provider;

use App\Entity\Application;
use App\Entity\Bookmark;
use App\Entity\Education;
use App\Entity\Experience;
use App\Entity\Job;
use App\Entity\License;
use App\Entity\Message;
use App\Entity\Notification;

use App\Entity\DocumentRequest;
use App\Entity\ToDo;

use App\Service\OnboardingService;
use App\Repository\ToDoRepository;
use App\Service\ProfileAnalyticsService;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\BookmarkRepository;
use App\Repository\InterviewRepository;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use App\Repository\DocumentRequestRepository;

#[Route('/provider')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_provider_dashboard')]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        OnboardingService $onboardingService,
        BookmarkRepository $bookmarkRepository,

        ProfileAnalyticsService $analyticsService,
        ToDoRepository $todoRepository,
        InterviewRepository $interviewRepository
    ): Response {
        $user = $this->getUser();

        // Debug: Check if user exists and has provider
        if (!$user) {
            throw new \Exception('User not found');
        }

        $provider = $user->getProvider();

        // Debug: Check if provider exists
        if (!$provider) {
            // You might want to redirect to profile setup or show a message
            $this->addFlash('warning', 'Please complete your provider profile setup.');
        }

        $isOnboardingCompleted = $onboardingService->isProviderOnboardingCompleted($user);

        if (!$isOnboardingCompleted and !$provider->isSkipOnboarding()) {
            return $this->render('provider/onboard.html.twig', []);
        }

        // Get analytics data - this will handle null provider gracefully
        $analytics = $analyticsService->getProfileAnalytics($user);

        $bookmarks = $bookmarkRepository->findBy(['user' => $this->getUser()], ['id' => 'DESC']);
        $messages = $em->getRepository(Message::class)->findBy(['receiver' => $user], ['id' => 'DESC'], 10);
        $notifications = $em->getRepository(Notification::class)->findBy(['user' => $user], ['id' => 'DESC'], 5);

        // Only get matching jobs if provider exists and has necessary data
        $matchingJobs = null;
        if ($provider) {
            $filters['profession'] = $provider->getProfession()?->getId();
            $filters['specialities'] = $provider->getSpecialities();
            $filters['state'] = $provider->getDesiredStates() ? implode(',', $provider->getDesiredStates()) : null;
            $filters['limit'] = 5;

            $matchingJobs = $em->getRepository(Job::class)->getProviderMatchingJobs($filters);

            if (empty($filters['profession']) && empty($filters['speciality']) && empty($filters['state'])) {
                $matchingJobs = null;
            }
        }

        $applications = $em->getRepository(Application::class)->findBy(['provider' => $this->getUser()->getProvider()], ['id' => 'DESC'], 5);

        // Get all applications for status counting (same as AnalyticsController)
        $allApplications = [];
        if ($provider) {
            $allApplications = $em->getRepository(Application::class)->findBy(['provider' => $provider]);
        }

        // Calculate status counts the same way as AnalyticsController
        $statusCountsArray = [
            'applied' => 0,
            'interview' => 0,
            'negotiating' => 0,
            'accepted' => 0,
            'completed' => 0,
            'rejected' => 0,
        ];

        foreach ($allApplications as $application) {
            $status = strtolower($application->getStatus() ?? '');
            if (isset($statusCountsArray[$status])) {
                $statusCountsArray[$status]++;
            }
        }

        // Calculate total applications
        $totalApplications = count($allApplications);

        // Calculate ratio - Interview percentage of total (applied + interview)
        $interviewCount = $statusCountsArray['interview'];
        $appliedCount = $statusCountsArray['applied'];
        $totalCount = $appliedCount + $interviewCount;
        $ratio = $totalCount > 0 ? ($interviewCount / $totalCount) * 100 : 0;

        // Get status counts for the status cards (original format)
        $statusCounts = [];
        if ($provider) {
            $statusCounts = $em->getRepository(Application::class)->getProviderApplicationStatusCounts($provider->getId());
        }
        $statusCounts[] = [
            'status' => 'saved',
            'count' => count($bookmarks),
        ];

        // Get analytics data
        $analytics = $analyticsService->getProfileAnalytics($user);
        // Extract metrics and other data from analytics
        $metrics = $analytics['metrics'] ?? [];
        $skills = $analytics['skills'] ?? [];
        $resume = $analytics['resume'] ?? [];

        // Get pending To-Do items (assigned by employer / document requests)
        $todos = $todoRepository->findAllAssignedToProvider($provider);

        // Also fetch pending DocumentRequests that might NOT have a linked ToDo (legacy/integrity check)
        $pendingDocRequests = $em->getRepository(DocumentRequest::class)->findBy([
            'provider' => $provider,
            'providedAt' => null
        ]);

        // Merge: Create pseudo-ToDo objects for orphan document requests
        $existingDocRequestIds = [];
        foreach ($todos as $todo) {
            if ($todo->getDocumentRequest()) {
                $existingDocRequestIds[] = $todo->getDocumentRequest()->getId();
            }
        }

        foreach ($pendingDocRequests as $dr) {
            if (!in_array($dr->getId(), $existingDocRequestIds)) {
                $pseudoTodo = new ToDo();
                $pseudoTodo->setTitle('📄 Document Required: ' . $dr->getName());
                $pseudoTodo->setDocumentRequest($dr);
                $pseudoTodo->setProvider($provider);
                $pseudoTodo->setCreatedAt($dr->getCreatedAt() ?? new \DateTime()); // Fallback if timestampable trait issue
                $pseudoTodo->setEmployer($dr->getApplication()?->getEmployer());
                $todos[] = $pseudoTodo;
            }
        }

        // Sort merged list by created at desc
        usort($todos, function ($a, $b) {
            return $b->getCreatedAt() <=> $a->getCreatedAt();
        });

        // Group document requests by employer for bundling
        $bundledTodos = [];
        $documentRequestTodos = [];
        $otherTodos = [];

        foreach ($todos as $todo) {
            // Only bundle document requests that have an employer
            if ($todo->getDocumentRequest() && $todo->getEmployer()) {
                $employerId = $todo->getEmployer()->getId()->toString();
                if (!isset($documentRequestTodos[$employerId])) {
                    $documentRequestTodos[$employerId] = [
                        'employer' => $todo->getEmployer(),
                        'todos' => [],
                        'latestDate' => $todo->getCreatedAt(),
                    ];
                }
                $documentRequestTodos[$employerId]['todos'][] = $todo;
                // Update latest date if this todo is newer
                if ($todo->getCreatedAt() > $documentRequestTodos[$employerId]['latestDate']) {
                    $documentRequestTodos[$employerId]['latestDate'] = $todo->getCreatedAt();
                }
            } else {
                $otherTodos[] = $todo;
            }
        }

        // Convert bundled document requests to single todo items
        foreach ($documentRequestTodos as $employerId => $bundle) {
            $bundledTodo = new ToDo();
            $bundledTodo->setTitle('📄 Document Request');
            $bundledTodo->setEmployer($bundle['employer']);
            $bundledTodo->setProvider($provider);
            $bundledTodo->setCreatedAt($bundle['latestDate']);
            $bundledTodo->setType('bundled_document_request');
            // Store the count in description for reference
            $bundledTodo->setDescription(count($bundle['todos']) . ' document(s)');
            $bundledTodos[] = $bundledTodo;
        }

        // Merge bundled todos with other todos and sort
        $finalTodos = array_merge($bundledTodos, $otherTodos);
        usort($finalTodos, function ($a, $b) {
            return $b->getCreatedAt() <=> $a->getCreatedAt();
        });

        $interviewsForDashboard = [];
        if ($provider) {
            $allInterviews = $interviewRepository->getProviderInterviews($provider->getId());
            usort($allInterviews, function ($a, $b) {
                return $a->getDate() <=> $b->getDate();
            });
            $now = new \DateTime();
            $upcoming = array_filter($allInterviews, function ($iv) use ($now) {
                return $iv->getDate() && $iv->getDate() >= $now;
            });
            $interviewsForDashboard = array_slice(!empty($upcoming) ? $upcoming : $allInterviews, 0, 5);
        }

        return $this->render('provider/dashboard.html.twig', [
            'bookmarks' => $bookmarks,
            'matchingJobs' => $matchingJobs,
            'statusCounts' => $statusCounts,
            'statusCountsArray' => $statusCountsArray,
            'applications' => $applications,
            'messages' => $messages,
            'notifications' => $notifications,
            'totalApplications' => $totalApplications,
            'analytics' => $analytics,
            'ratio' => round($ratio, 1),
            'interviewCount' => $interviewCount,
            'appliedCount' => $appliedCount,
            'metrics' => $metrics,
            'skills' => $skills,
            'resume' => $resume,
            'todos' => $finalTodos,
            'bundledDocumentRequests' => $documentRequestTodos, // Pass bundled data for popup
            'hasProvider' => $provider !== null,
            'interviews' => $interviewsForDashboard,
        ]);
    }

    #[Route('/skip-onboarding', name: 'app_provider_skip_onboarding')]
    public function skipOnboarding(
        EntityManagerInterface $em,
    ): Response {
        $user = $this->getUser();
        $provider = $user->getProvider();

        $provider->setSkipOnboarding(true);

        $em->persist($provider);
        $em->flush();

        return $this->redirectToRoute('app_provider_dashboard');
    }

    #[Route('/todos/{id}/toggle', name: 'app_provider_dashboard_todo_toggle', methods: ['POST'])]
    public function toggleTodo(ToDo $todo, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user || !$user->getProvider() || $todo->getProvider() !== $user->getProvider()) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $todo->setDone(!$todo->isDone());
        $em->flush();

        return $this->json(['success' => true, 'isDone' => $todo->isDone()]);
    }

    #[Route('/todos/employer/{employerId}/document-requests', name: 'app_provider_employer_document_requests', methods: ['GET'])]
    public function getEmployerDocumentRequests(
        string $employerId,
        EntityManagerInterface $em,
        DocumentRequestRepository $documentRequestRepository
    ): JsonResponse {
        $user = $this->getUser();
        $provider = $user->getProvider();

        if (!$provider) {
            return $this->json(['success' => false, 'message' => 'Provider not found'], 404);
        }

        try {
            // Convert string to UUID
            $employerUuid = Uuid::fromString($employerId);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Invalid employer ID'], 400);
        }

        // Get all pending document requests for this provider from this employer
        $documentRequests = $documentRequestRepository->createQueryBuilder('dr')
            ->leftJoin('dr.application', 'a')
            ->leftJoin('a.employer', 'e')
            ->where('dr.provider = :provider')
            ->andWhere('dr.providedAt IS NULL')
            ->andWhere('e.id = :employerId')
            ->setParameter('provider', $provider)
            ->setParameter('employerId', $employerUuid, UuidType::NAME)
            ->orderBy('dr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $data = array_map(function (DocumentRequest $dr) {
            return [
                'id' => $dr->getId()->toString(),
                'name' => $dr->getName(),
                'createdAt' => $dr->getCreatedAt()?->format('M d, Y h:i A'),
                'application' => $dr->getApplication()?->getJob()?->getTitle() ?? 'N/A',
            ];
        }, $documentRequests);

        return $this->json([
            'success' => true,
            'documentRequests' => $data,
            'count' => count($data),
        ]);
    }
}
