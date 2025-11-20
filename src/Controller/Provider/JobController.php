<?php

namespace App\Controller\Provider;

use App\Entity\Application;
use App\Entity\Bookmark;
use App\Entity\Job;
use App\Entity\Review;
use App\Event\ApplicationEvent;
use App\Event\ReviewEvent;
use App\Repository\ApplicationRepository;
use App\Repository\BookmarkRepository;
use App\Repository\JobRepository;
use App\Repository\ToDoRepository;
use App\Entity\Message;
use App\Entity\Notification;
use App\Service\ApplicationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Uid\Uuid;
use Dompdf\Dompdf;
use Dompdf\Options;
#[Route('/provider')]
class JobController extends AbstractController
{
    #[Route('/jobs/archived', name: 'app_provider_jobs_archived', methods: ['GET'])]
    public function archived(): Response
    {
        // For now, return an empty array to show the page without errors
        // We'll update this later to fetch actual archived jobs
        return $this->render('provider/job/archived.html.twig', [
            'archived_jobs' => []
        ]);
    }

    #[Route('/jobs/{id}/detail', name: 'app_provider_jobs_detail', methods: ['GET'])]
    public function detail(Job $job, ApplicationRepository $applicationRepository): Response
    {
        $applications = $applicationRepository->findBy(['provider' => $this->getUser()->getProvider()]);

        $appliedJobs = [];
        $appliedJobsIds = [];
        foreach ($applications as $application){
            $appliedJobsIds[] =  (string) $application->getJob()->getId();
        }

        return $this->render('provider/job/detail.html.twig', [
            'job' => $job,
            'appliedJobsIds' => $appliedJobsIds,
        ]);
    }

    #[Route('/jobs/matching', name: 'app_provider_jobs_matching')]
    public function matchingJobs(
        Request $request,
        JobRepository $jobRepository, 
        ApplicationRepository $applicationRepository,
        EntityManagerInterface $em
    ): Response
    {
        $user = $this->getUser();
        $provider = $user->getProvider();

        // -------------------------------
        // Filter parameters from query string (AJAX or normal)
        // -------------------------------
        $location = $request->query->get('location');
        $salaryMin = $request->query->get('salaryMin');
        $salaryMax = $request->query->get('salaryMax');
        $category = $request->query->get('category'); // work_type
        $days = $request->query->get('days');

        $filters['profession'] = $provider->getProfession()?->getId();
        $providerSpecialities = $provider->getSpecialities();
        if(!empty($providerSpecialities)) {
            foreach ($providerSpecialities as $speciality) {
                $filters['speciality_ids'][] = $speciality->getId();
            }
        }
        $filters['state'] = $provider->getDesiredStates() ? implode(',', $provider->getDesiredStates()) : null;

        // Add filter parameters
        if ($location) {
            $filters['location'] = $location;
        }
        if ($salaryMin) {
            $filters['salaryMin'] = $salaryMin;
        }
        if ($salaryMax) {
            $filters['salaryMax'] = $salaryMax;
        }
        if ($category) {
            $filters['category'] = $category;
        }
        if ($days) {
            $filters['days'] = $days;
        }

        $jobs = $jobRepository->getProviderMatchingJobs($filters);

        if(empty($filters['profession']) && empty($filters['speciality']) && empty($filters['state'])) {
            $jobs = null;
        }

        $applications = $applicationRepository->findBy(['provider' => $this->getUser()->getProvider()]);

        $appliedJobs = [];
        $appliedJobsIds = [];
        foreach ($applications as $application){
            $appliedJobsIds[] =  (string) $application->getJob()->getId();
        }

        // Get bookmarks for matching jobs to show scores
        $bookmarkRepository = $em->getRepository(Bookmark::class);
        $bookmarks = [];
        if ($jobs) {
            $jobIds = array_map(fn($job) => $job->getId(), $jobs);
            $userBookmarks = $bookmarkRepository->findBy([
                'user' => $user,
                'job' => $jobIds
            ]);
            foreach ($userBookmarks as $bookmark) {
                $bookmarks[(string)$bookmark->getJob()->getId()] = $bookmark;
            }
        }

        // -------------------------------
        // Handle AJAX request
        // -------------------------------
        if ($request->isXmlHttpRequest()) {
            $html = $this->renderView('provider/job/_matching_job_list.html.twig', [
                'jobs' => $jobs,
                'appliedJobsIds' => $appliedJobsIds,
                'bookmarks' => $bookmarks,
            ]);
            return $this->json(['html' => $html]);
        }

        return $this->render('provider/job/matching.html.twig', [
            'jobs' => $jobs,
            'appliedJobsIds' => $appliedJobsIds,
            'bookmarks' => $bookmarks,
        ]);
    }

    #[Route('/jobs/saved', name: 'app_provider_jobs_saved')]
    public function savedJobs(BookmarkRepository $bookmarkRepository, ApplicationRepository $applicationRepository, EntityManagerInterface $em, Request $request): Response
    {
        $user = $this->getUser();
        $provider = $user->getProvider();
        
        // -------------------------------
        // Filter parameters from query string (AJAX or normal)
        // -------------------------------
        $location = $request->query->get('location');
        $salaryMin = $request->query->get('salaryMin');
        $salaryMax = $request->query->get('salaryMax');
        $category = $request->query->get('category');
        $days = $request->query->get('days'); // Posted date filter
        
        // Apply filters if provided
        if ($location || $salaryMin || $salaryMax || $category || $days) {
            $bookmarks = $bookmarkRepository->findFilteredJobs(
                $this->getUser()->getId(),
                $location,
                $salaryMin,
                $salaryMax,
                $category,
                $days
            );
        } else {
            // No filters - get all bookmarks
        $bookmarks = $bookmarkRepository->createQueryBuilder('b')
            ->join('b.job', 'j')
            ->where('b.user = :user')
            ->setParameter('user', $this->getUser()->getId(), UuidType::NAME)
            ->orderBy('b.id', 'DESC')
            ->getQuery()
            ->getResult();
        }

        $messages = $em->getRepository(Message::class)->findBy(['receiver' => $user], ['id' => 'DESC'], 10);
        $notifications = $em->getRepository(Notification::class)->findBy(['user' => $user], ['id' => 'DESC'], 5);

        $applications = $applicationRepository->findBy(['provider' => $this->getUser()->getProvider()]);

        $appliedJobs = [];
        $appliedJobsIds = [];
        foreach ($applications as $application){
            $appliedJobsIds[] =  (string) $application->getJob()->getId();
        }

        $filters['profession'] = $provider->getProfession()?->getId();
        $filters['specialities'] = $provider->getSpecialities();
        $filters['state'] = $provider->getDesiredStates() ? implode(',', $provider->getDesiredStates()) : null;
        $filters['limit'] = 5;

        $matchingJobs = $em->getRepository(Job::class)->getProviderMatchingJobs($filters);

        if(empty($filters['profession']) && empty($filters['speciality']) && empty($filters['state'])) {
            $matchingJobs = null;
        }

        // $applications = $em->getRepository(Application::class)->findBy(['provider' => $this->getUser()->getProvider()], ['id' => 'DESC'], 5);
        $applications = $em->getRepository(Application::class)
                   ->findBy(['provider' => $this->getUser()->getProvider()], ['createdAt' => 'DESC']);
        // Temporarily removed archived filter to avoid column not found error

        $statusCounts = $em->getRepository(Application::class)->getProviderApplicationStatusCounts($provider->getId());
        $statusCounts[] = [
            'status' => 'saved',
            'count' => count($bookmarks),
        ];

        $totalApplications = $em->createQuery("SELECT count(a.id) as total_applications FROM App\Entity\Application a WHERE a.provider = :provider")
            ->setParameter('provider', $this->getUser()->getProvider()->getId(), UuidType::NAME)
            ->getSingleScalarResult();

        // -------------------------------
        // Handle AJAX requests - return JSON with HTML
        // -------------------------------
        if ($request->isXmlHttpRequest()) {
            // Render only the job list container for AJAX (using partial template)
            $html = $this->renderView('provider/job/_saved_job_list.html.twig', [
                'bookmarks' => $bookmarks,
                'appliedJobsIds' => $appliedJobsIds,
            ]);
            
            return $this->json(['html' => $html]);
        }

        return $this->render('provider/job/saved.html.twig', [
            'bookmarks' => $bookmarks,
            'appliedJobsIds' => $appliedJobsIds,
            'totalApplications' => $totalApplications,
            'statusCounts' => $statusCounts,
            'applications' => $applications,
            'messages' => $messages,
            'notifications' => $notifications,
            'totalApplications' => $totalApplications,
        ]);
    }

    #[Route('/applications', name: 'app_provider_jobs_applications')]
    public function applications(
        BookmarkRepository $bookmarkRepository,
        Request $request,
        EntityManagerInterface $em
    ): Response
    {
        $user = $this->getUser();
        $provider = $user->getProvider();
        
        $bookmarks = $bookmarkRepository->findBy(['user' => $this->getUser()], ['id' => 'DESC']);

        // -------------------------------
        // Filter parameters from query string (AJAX or normal)
        // -------------------------------
        $location = $request->query->get('location');
        $salaryMin = $request->query->get('salaryMin');
        $salaryMax = $request->query->get('salaryMax');
        $days = $request->query->get('days'); // Applied date filter

        $offset = $request->query->get('page', 1);
        $perPage = $request->get('per_page', 10);
        $filters = $request->query->all();
        $filters['provider'] = $this->getUser()->getProvider()->getId();

        // Add filter parameters
        if ($location) {
            $filters['location'] = $location;
        }
        if ($salaryMin) {
            $filters['salaryMin'] = $salaryMin;
        }
        if ($salaryMax) {
            $filters['salaryMax'] = $salaryMax;
        }
        if ($days) {
            $filters['days'] = $days;
        }

        $applications = $em->getRepository(Application::class)->getAll($offset, $perPage, $filters);
        $statusCounts = $em->getRepository(Application::class)->getProviderApplicationStatusCounts( $this->getUser()->getProvider()->getId());
         $statusCounts[] = [
            'status' => 'saved',
            'count' => count($bookmarks),
        ];

        $totalApplications = $em->createQuery("SELECT count(a.id) as total_applications FROM App\Entity\Application a WHERE a.provider = :provider")
            ->setParameter('provider', $this->getUser()->getProvider()->getId(), UuidType::NAME)
            ->getSingleScalarResult();

        // -------------------------------
        // Handle AJAX request
        // -------------------------------
        if ($request->isXmlHttpRequest()) {
            $html = $this->renderView('provider/job/_application_list.html.twig', [
                'applications' => $applications,
                'status' => $request->query->get('status', ''),
            ]);
            return $this->json(['html' => $html]);
        }

        return $this->render('provider/job/applications.html.twig', [
            'applications' => $applications,
            'statusCounts' => $statusCounts,
            'totalApplications' => $totalApplications,
        ]);
    }

    #[Route('/applications/{id}', name: 'app_provider_jobs_application_detail')]
    public function applicationDetail(Application $application, EntityManagerInterface $em): Response
    {
        $review = $em->getRepository(Review::class)->findOneBy([
            'application' => $application,
            'provider' => $application->getProvider(),
            'employer' => $application->getEmployer(),
            'reviewedBy' => 'PROVIDER'
        ]);

        return $this->render('provider/job/application-detail.html.twig', [
            'application' => $application,
            'review' => $review
        ]);
    }

    #[Route('/jobs/apply/{id}', name: 'app_provider_jobs_apply')]
    public function applyJob(Job $job, Request $request, EntityManagerInterface $em, ApplicationService $applicationService): Response
    {
        $user = $this->getUser();

        if (!$user || !$job) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid user or job']);
        }

        $redirectRoute = $request->get('redirect_route') ? : 'app_provider_jobs_applications';

        $application = $em->getRepository(Application::class)->findOneBy(['provider' => $user->getProvider(), 'job' => $job, 'employer' => $job->getEmployer()]);
        if($application){
            $this->addFlash('success', 'You already have applied for this job');
            return $this->redirectToRoute($redirectRoute, [], Response::HTTP_SEE_OTHER);
        }

        $applicationService->createApplication($job, $user);

        $this->addFlash('success', 'Job applied successfully');
        return $this->redirectToRoute($redirectRoute, [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/jobs/remove-saved-job/{id}', name: 'app_provider_jobs_remove_saved_job')]
    public function removeSavedJob(Bookmark $bookmark, EntityManagerInterface $em): Response
    {
        $em->remove($bookmark);
        $em->flush();

        $this->addFlash('success', 'Your saved job removed successfully');
        return $this->redirectToRoute('app_provider_jobs_saved', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/provide-document', name: 'app_provider_application_providedocument', methods: ['GET'])]
    public function provideDocument(Application $application, EntityManagerInterface $em, EventDispatcherInterface $dispatcher): Response
    {
        if($application->getDocumentProvidedAt()){
            $this->addFlash('error', 'Document already provided to employer.');
            return $this->redirectToRoute('app_provider_jobs_applications');
        }

        $application->setDocumentProvidedAt(new \DateTime());

        $em->persist($application);
        $em->flush();

        $dispatcher->dispatch(new ApplicationEvent($application), ApplicationEvent::APPLICATION_DOCUMENT_PROVIDED);

        $this->addFlash('success', 'Document provided to employer successfully.');
        return $this->redirectToRoute('app_provider_jobs_application_detail', ['id' => $application->getId()]);
    }

    #[Route('/{id}/provide-onefile', name: 'app_provider_application_provideonefile', methods: ['GET'])]
    public function provideOneFile(Application $application, EntityManagerInterface $em, EventDispatcherInterface $dispatcher): Response
    {
        if($application->getOneFileProvidedAt()){
            $this->addFlash('error', 'One file already provided to employer.');
            return $this->redirectToRoute('app_provider_jobs_applications');
        }

        $application->setOneFileProvidedAt(new \DateTime());

        $em->persist($application);
        $em->flush();

        $dispatcher->dispatch(new ApplicationEvent($application), ApplicationEvent::APPLICATION_ONE_FILE_PROVIDED);

        $this->addFlash('success', 'One file provided to employer successfully.');
        return $this->redirectToRoute('app_provider_jobs_application_detail', ['id' => $application->getId()]);
    }

    #[Route('/{id}/send-contract', name: 'app_provider_application_sendcontract', methods: ['GET', 'POST'])]
    public function sendContract(
        Application $application,
        Request $request,
        EntityManagerInterface $em,
        EventDispatcherInterface $dispatcher,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads/contracts')] string $uploadDirectory,
        WorkflowInterface $jobApplicationWorkflow
    ): Response
    {
        $referer = $request->headers->get('referer');
        if($application->getContractSignedAt()){
            $this->addFlash('error', 'Contract already sent to employer.');
            return $this->redirect($referer ?? $this->generateUrl('app_provider_jobs_applications'));
        }

        if($request->getMethod() == 'POST') {
            $contractFile = $request->files->get('contractFile');
            if ($contractFile) {
                $originalFilename = pathinfo($contractFile->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the file name as part of the URL
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$contractFile->guessExtension();

                try {
                    $contractFile->move($uploadDirectory, $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'File upload failed.');
                    return $this->redirect($referer ?? $this->generateUrl('app_provider_jobs_applications'));
                }

                $application->setContractSignedFileName($newFilename);
                $application->setContractSignedAt(new \DateTime());
            }

            $em->persist($application);
            $em->flush();

            $dispatcher->dispatch(new ApplicationEvent($application), ApplicationEvent::APPLICATION_CONTRACT_SIGNED_SENT);

            if($application->getStatus() == 'offered'){
                if ($jobApplicationWorkflow->can($application, 'hire')) {
                    $jobApplicationWorkflow->apply($application, 'hire');
                    $em->persist($application);
                    $em->flush();
                }
            }

            $this->addFlash('success', 'Contract sent to employer successfully.');
            return $this->redirect($referer ?? $this->generateUrl('app_provider_jobs_application_detail', ['id' => $application->getId()]));
        }

        $this->addFlash('error', 'Unable to send contract');
        return $this->redirect($referer ?? $this->generateUrl('app_provider_jobs_application_detail', ['id' => $application->getId()]));
    }

    #[Route('/{id}/review-employer', name: 'app_provider_application_review_employer', methods: ['GET', 'POST'])]
    public function reviewEmployer(
        Application $application,
        Request $request,
        EntityManagerInterface $em,
        EventDispatcherInterface $dispatcher,
    ): Response
    {
        $provider = $application->getEmployer();
        $employer = $application->getEmployer();

        $existingReview = $em->getRepository(Review::class)->findOneBy([
            'application' => $application,
            'employer' => $employer,
            'provider' => $provider,
            'reviewedBy' => 'PROVIDER'
        ]);

        if($existingReview){
            $this->addFlash('error', 'You already have write review for employer.');
            return $this->redirectToRoute('app_provider_jobs_application_detail', ['id' => $application->getId()]);
        }

        if($request->getMethod() == 'POST') {
            $message = $request->get('message');
            $point = (int)$request->get('point');
            if (!empty($message) && !empty($point)) {
                $review = new Review();

                $review->setMessage($message);
                $review->setPoint($point);
                $review->setEmployer($application->getEmployer());
                $review->setProvider($application->getProvider());
                $review->setApplication($application);
                $review->setReviewedBy('PROVIDER');

                $em->persist($review);
                $em->flush();

                // Calculate average of all review points for this employer
                $qb = $em->createQueryBuilder();
                $qb->select('AVG(r.point)')
                    ->from(Review::class, 'r')
                    ->where('r.employer = :employer')
                    ->setParameter('employer', $employer->getId(), UuidType::NAME)
                    ->andWhere('r.reviewedBy = :reviewedBy')
                    ->setParameter('reviewedBy', 'PROVIDER');

                $average = $qb->getQuery()->getSingleScalarResult();
                $employer->setAveragePoint(round((float)$average, 2)); // rounded to 2 decimals

                $em->persist($employer);
                $em->flush();

                $dispatcher->dispatch(new ReviewEvent($review), ReviewEvent::EMPLOYER_REVIEWED);
            }

            $this->addFlash('success', 'Review added for employer successfully.');
            return $this->redirectToRoute('app_provider_jobs_application_detail', ['id' => $application->getId()]);
        }

        $this->addFlash('error', 'Unable to create review');
        return $this->redirectToRoute('app_provider_jobs_application_detail', ['id' => $application->getId()]);
    }

    #[Route('/update-rank', name: 'app_update_rank', methods: ['POST'])]
    public function updateRank(Request $request, EntityManagerInterface $em, BookmarkRepository $bookmarkRepository, JobRepository $jobRepository): JsonResponse
    {
        try {
        // Parse JSON body
        $data = json_decode($request->getContent(), true);
            $jobIdStr = $data['jobId'] ?? null;
        $rank = $data['rank'] ?? null;

            if (!$jobIdStr || $rank === null) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid data'], 400);
        }

            // Convert jobId string to UUID
            $jobId = Uuid::fromString($jobIdStr);
            $job = $jobRepository->find($jobId);

            if (!$job) {
                return new JsonResponse(['success' => false, 'error' => 'Job not found'], 404);
            }

            // Find user's bookmark for this job, or create one if it doesn't exist
        $bookmark = $bookmarkRepository->findOneBy([
                'job' => $job,
            'user' => $this->getUser(),
        ]);

        if (!$bookmark) {
                // Create a new bookmark if it doesn't exist (for matching jobs)
                $bookmark = new Bookmark();
                $bookmark->setJob($job);
                $bookmark->setUser($this->getUser());
                $em->persist($bookmark);
                $em->flush(); // Flush to get the ID
            }

            // Validate and clamp rank
            $rank = (float)$rank;
            if ($rank < 1) $rank = 1;
            if ($rank > 10) $rank = 10;

            // Use raw SQL to properly escape the rank column name (MySQL reserved keyword)
            // Detach entity first to prevent Doctrine listeners from interfering
            $em->detach($bookmark);
            
            $connection = $em->getConnection();
            $now = (new \DateTime())->format('Y-m-d H:i:s');
            $idBinary = $bookmark->getId()->toBinary();
            $rankStr = (string)$rank;
            
            // Get the actual PDO connection from Doctrine's connection wrapper
            // We need to go through multiple layers to get the raw PDO instance
            $wrappedConnection = $connection->getWrappedConnection();
            
            // Handle different connection wrapper types
            if (method_exists($wrappedConnection, 'getWrappedConnection')) {
                $pdo = $wrappedConnection->getWrappedConnection();
            } elseif ($wrappedConnection instanceof \PDO) {
                $pdo = $wrappedConnection;
            } else {
                // Fallback: try to get native connection
                if (method_exists($connection, 'getNativeConnection')) {
                    $pdo = $connection->getNativeConnection();
                } else {
                    // Last resort: use connection params to create new PDO
                    $params = $connection->getParams();
                    $dsn = sprintf(
                        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                        $params['host'] ?? 'localhost',
                        $params['port'] ?? 3306,
                        $params['dbname'] ?? ''
                    );
                    $pdo = new \PDO($dsn, $params['user'] ?? '', $params['password'] ?? '');
                    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                }
            }
            
            // Ensure we have a PDO instance
            if (!$pdo instanceof \PDO) {
                throw new \RuntimeException('Could not obtain PDO connection');
            }
            
            // Execute raw SQL with backticks using PDO directly
            // Backticks MUST be preserved - this bypasses all Doctrine processing
            $sql = "UPDATE `b_bookmark` SET `rank` = :rank_val, `updated_at` = :updated_at WHERE `id` = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':rank_val', $rankStr, \PDO::PARAM_STR);
            $stmt->bindValue(':updated_at', $now, \PDO::PARAM_STR);
            $stmt->bindValue(':id', $idBinary, \PDO::PARAM_STR);
            $stmt->execute();
            
            // Clear the entity manager to ensure fresh data on next fetch
            $em->clear();

            return new JsonResponse(['success' => true, 'message' => 'Rank updated successfully']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/jobs/{id}/detail-content', name: 'app_provider_jobs_detail_content')]
    public function jobDetailContent($id, EntityManagerInterface $em): Response
    {
        try {
            // Validate UUID
            if (!Uuid::isValid($id)) {
                return new Response(
                    '<div class="alert alert-danger">Invalid job ID format</div>',
                    400
                );
            }

            // Find the job
            $job = $em->getRepository(Job::class)->find($id);
            
            if (!$job) {
                return new Response(
                    '<div class="alert alert-danger">Job not found</div>',
                    404
                );
            }

            // Get applied jobs IDs
            $appliedJobsIds = $this->getAppliedJobsIds($em);

            return $this->render('provider/job/_job_detail_content.html.twig', [
                'job' => $job,
                'appliedJobsIds' => $appliedJobsIds
            ]);
            
        } catch (\Exception $e) {
            // Log the error for debugging
            error_log('Job detail content error: ' . $e->getMessage());
            
            return new Response(
                '<div class="alert alert-danger">Error loading job details. Please try again.</div>',
                500
            );
        }
    }

    private function getAppliedJobsIds(EntityManagerInterface $em): array
    {
        try {
            $user = $this->getUser();
            if (!$user || !$user->getProvider()) {
                return [];
            }

            $applications = $em->getRepository(Application::class)
                ->findBy(['provider' => $user->getProvider()]);

            $appliedJobsIds = [];
            foreach ($applications as $application) {
                $job = $application->getJob();
                if ($job && $job->getId()) {
                    $appliedJobsIds[] = (string) $job->getId();
                }
            }

            return $appliedJobsIds;
        } catch (\Exception $e) {
            // Return empty array and log the error
            error_log('Error in getAppliedJobsIds: ' . $e->getMessage());
            return [];
        }
    }
    
    #[Route('/jobs/archive-bulk', name: 'app_provider_jobs_archive_bulk', methods: ['POST'])]
    public function archiveBulk(Request $request, EntityManagerInterface $em): JsonResponse
    {
        // Temporarily disabled to avoid archived column error
        return new JsonResponse([
            'success' => true,
            'message' => "Archive functionality temporarily disabled."
        ]);
        
        /* Original code - disabled temporarily
        $data = json_decode($request->getContent(), true);
        $ids = $data['ids'] ?? [];

        if (empty($ids)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'No job IDs provided.'
            ], 400);
        }

        $repo = $em->getRepository(Job::class);
        $updated = 0;

        foreach ($ids as $idStr) {
            try {
                $uuid = Uuid::fromString($idStr);
                $job = $repo->find($uuid);
                if ($job) {
                    $job->setArchived(true);
                    $updated++;
                }
            } catch (\Throwable $e) {
                // invalid UUID or other issue, skip
                continue;
            }
        }

        $em->flush();
        $em->clear();

        return new JsonResponse([
            'success' => true,
            'message' => "$updated job(s) archived successfully."
        ]);
        */
    }

    
    #[Route('/jobs/archived', name: 'app_provider_jobs_archived')]
    public function archivedJobs(BookmarkRepository $bookmarkRepository, ApplicationRepository $applicationRepository, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $provider = $user->getProvider();
        
        // Fetch archived applications
        $archivedApplications = $applicationRepository->findBy([
            'provider' => $provider,
            'isArchived' => true
        ], ['archivedAt' => 'DESC']);
        
        // Extract job IDs from archived applications
        $appliedJobsIds = [];
        foreach ($archivedApplications as $application) {
            if ($application->getJob()) {
                $appliedJobsIds[] = $application->getJob()->getId()->toString();
            }
        }

        return $this->render('provider/job/archived.html.twig', [
            'archivedApplications' => $archivedApplications,
            'appliedJobsIds' => $appliedJobsIds,
        ]);
    }

    #[Route('/jobs/export', name: 'app_provider_jobs_export')]
    public function exportJobs(Request $request, BookmarkRepository $bookmarkRepository): Response
    {
        $page = $request->query->get('page'); // e.g., "archived" or null

        // Temporarily removed archived filter to avoid column not found error
        $qb = $bookmarkRepository->createQueryBuilder('b')
            ->join('b.job', 'j')
            ->where('b.user = :user')
            ->setParameter('user', $this->getUser()->getId(), UuidType::NAME)
            ->orderBy('b.id', 'DESC');

        $bookmarks = $qb->getQuery()->getResult();

        $csv = "Job,Location,Posted on,Expires on,Salary(Hourly),Rank\n";

        foreach ($bookmarks as $bookmark) {
            $job = $bookmark->getJob();
            $csv .= sprintf(
                "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $job->getTitle() ?: '',
                $job->getCity() ?: '',
                $job->getCreatedAt() ? $job->getCreatedAt()->format('m/d/Y') : '',
                $job->getExpirationDate() ? $job->getExpirationDate()->format('m/d/Y') : '',
                $job->getPayRateHourly() ?: '',
                $bookmark->getRank() ?: ''
            );
        }

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="jobs.csv"',
        ]);
    }


    #[Route('/download-data', name: 'app_provider_download_data', methods: ['GET'])]
    public function downloadData(
        BookmarkRepository $bookmarkRepository,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        $user = $this->getUser();
        $provider = $user->getProvider();
        
        // Get the page type from query parameter (saved, applications, matching)
        $type = $request->query->get('type', 'all');
        
        // Initialize data arrays
        $bookmarks = [];
        $appliedApplications = [];
        $interviewApplications = [];
        $completedApplications = [];
        $matchingJobs = [];
        $archivedApplications = [];
        
        // Fetch data based on page type
        switch ($type) {
            case 'saved':
                // Only saved jobs
                $bookmarks = $bookmarkRepository->findBy(['user' => $user], ['id' => 'DESC']);
                break;
                
            case 'applications':
                // Get status filter from query parameter
                $statusFilter = $request->query->get('status', '');
                
                if ($statusFilter && in_array($statusFilter, ['applied', 'interview', 'completed'])) {
                    // Only fetch applications with the specific status (for PDF display)
                    $filteredApplications = $em->getRepository(Application::class)->findBy(
                        ['provider' => $provider, 'status' => $statusFilter],
                        ['createdAt' => 'DESC']
                    );
                    
                    // Group by status (only the filtered status)
                    foreach ($filteredApplications as $application) {
                        switch ($application->getStatus()) {
                            case 'applied':
                                $appliedApplications[] = $application;
                                break;
                            case 'interview':
                                $interviewApplications[] = $application;
                                break;
                            case 'completed':
                                $completedApplications[] = $application;
                                break;
                        }
                    }
                } else {
                    // No status filter or status not in PDF sections - fetch all applications
                    $allApplications = $em->getRepository(Application::class)->findBy(
                        ['provider' => $provider],
                        ['createdAt' => 'DESC']
                    );
                    
                    // Group by status
                    foreach ($allApplications as $application) {
                        switch ($application->getStatus()) {
                            case 'applied':
                                $appliedApplications[] = $application;
                                break;
                            case 'interview':
                                $interviewApplications[] = $application;
                                break;
                            case 'completed':
                                $completedApplications[] = $application;
                                break;
                        }
                    }
                }
                break;
                
            case 'matching':
                // Only matching jobs
                $filters['profession'] = $provider->getProfession()?->getId();
                $providerSpecialities = $provider->getSpecialities();
                if(!empty($providerSpecialities)) {
                    foreach ($providerSpecialities as $speciality) {
                        $filters['speciality_ids'][] = $speciality->getId();
                    }
                }
                $filters['state'] = $provider->getDesiredStates() ? implode(',', $provider->getDesiredStates()) : null;
                
                if (empty($filters['profession']) && empty($filters['speciality']) && empty($filters['state'])) {
                    $matchingJobs = [];
                } else {
                    $matchingJobs = $em->getRepository(Job::class)->getProviderMatchingJobs($filters);
                    $matchingJobs = $matchingJobs ?? [];
                }
                break;
                
            case 'archived':
                // Only archived applications
                $archivedApplications = $em->getRepository(Application::class)->findBy(
                    ['provider' => $provider, 'isArchived' => true],
                    ['archivedAt' => 'DESC']
                );
                break;
                
            default:
                // All data (backward compatibility)
                $bookmarks = $bookmarkRepository->findBy(['user' => $user], ['id' => 'DESC']);
        $appliedApplications = $em->getRepository(Application::class)->findBy(
            ['provider' => $provider, 'status' => 'applied'],
            ['createdAt' => 'DESC']
        );
        $interviewApplications = $em->getRepository(Application::class)->findBy(
            ['provider' => $provider, 'status' => 'interview'],
            ['createdAt' => 'DESC']
        );
        $completedApplications = $em->getRepository(Application::class)->findBy(
            ['provider' => $provider, 'status' => 'completed'],
            ['createdAt' => 'DESC']
        );
                break;
        }
        
        // Configure DomPDF options
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');
        
        $dompdf = new Dompdf($options);
        
        // Get status filter for applications
        $statusFilter = $request->query->get('status', '');
        
        // Render PDF template
        $html = $this->renderView('provider/job/pdf_data.html.twig', [
            'provider' => $provider,
            'user' => $user,
            'type' => $type,
            'statusFilter' => $statusFilter,
            'bookmarks' => $bookmarks ?? [],
            'appliedApplications' => $appliedApplications ?? [],
            'interviewApplications' => $interviewApplications ?? [],
            'completedApplications' => $completedApplications ?? [],
            'matchingJobs' => $matchingJobs ?? [],
            'archivedApplications' => $archivedApplications ?? [],
        ]);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Generate filename based on type
        $typeLabel = match($type) {
            'saved' => 'saved_jobs',
            'applications' => 'applications',
            'matching' => 'matching_jobs',
            'archived' => 'archived_jobs',
            default => 'all_data'
        };
        $filename = 'provider_' . $typeLabel . '_' . date('Y-m-d') . '.pdf';
        
        // Return PDF as download
        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    #[Route('/applications/{id}/hire', name: 'app_provider_application_hire', methods: ['POST'])]
    public function hireApplication(
        Application $application,
        Request $request,
        ApplicationService $applicationService,
        EntityManagerInterface $em
    ): Response {
        // Check if user has permission to hire for this application
        $user = $this->getUser();
        
        // Check if the current user is the provider in this application
        if ($application->getProvider()->getUser()->getId() !== $user->getId()) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'You are not authorized to hire for this application.']);
            }
            $this->addFlash('error', 'You are not authorized to hire for this application.');
            return $this->redirectToRoute('app_provider_jobs_applications');
        }

        // Check if already hired
        if ($application->getStatus() === 'accepted') {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'This application has already been marked as hired.']);
            }
            $this->addFlash('error', 'This application has already been marked as hired.');
            return $this->redirectToRoute('app_provider_jobs_application_detail', ['id' => $application->getId()]);
        }

        try {
            // Mark as hired - this will trigger the notification
            $applicationService->markAsHired($application);
            
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true, 
                    'message' => 'Provider hired successfully! Admin has been notified. The application has been moved to the "accepted" section.'
                ]);
            }
            
            $this->addFlash('success', 'Provider hired successfully! Admin has been notified.');
            return $this->redirectToRoute('app_provider_jobs_applications');
            
        } catch (\Exception $e) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'Error hiring provider: ' . $e->getMessage()]);
            }
            $this->addFlash('error', 'Error hiring provider: ' . $e->getMessage());
            return $this->redirectToRoute('app_provider_jobs_applications');
        }
    }

    #[Route('/saved-jobs/notify-hire', name: 'app_provider_saved_jobs_notify_hire', methods: ['POST'])]
    public function notifyHireFromSaved(
        Request $request,
        ApplicationService $applicationService,
        EntityManagerInterface $em,
        BookmarkRepository $bookmarkRepository,
        JobRepository $jobRepository,
        ApplicationRepository $applicationRepository
    ): JsonResponse {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $jobIds = $data['jobIds'] ?? [];

        if (empty($jobIds)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'No jobs selected.'
            ], 400);
        }

        try {
            $results = [
                'successful' => [],
                'failed' => [],
                'notApplied' => []
            ];

            foreach ($jobIds as $jobId) {
                // Validate UUID
                if (!Uuid::isValid($jobId)) {
                    $results['failed'][] = ['jobId' => $jobId, 'reason' => 'Invalid job ID format'];
                    continue;
                }

                // Find the job
                $job = $jobRepository->find($jobId);
                if (!$job) {
                    $results['failed'][] = ['jobId' => $jobId, 'reason' => 'Job not found'];
                    continue;
                }

                // Check if user has an application for this job
                $application = $applicationRepository->findOneBy([
                    'provider' => $user->getProvider(),
                    'job' => $job,
                    'employer' => $job->getEmployer()
                ]);

                if (!$application) {
                    $results['notApplied'][] = ['jobId' => $jobId, 'jobTitle' => $job->getTitle()];
                    continue;
                }

                // Check if already hired
                if ($application->getStatus() === 'accepted') {
                    $results['failed'][] = ['jobId' => $jobId, 'reason' => 'Already hired'];
                    continue;
                }

                // Mark as hired
                $applicationService->markAsHired($application);

                // Remove from saved jobs
                $bookmark = $bookmarkRepository->findOneBy([
                    'user' => $user,
                    'job' => $job
                ]);

                if ($bookmark) {
                    $em->remove($bookmark);
                }

                $results['successful'][] = [
                    'jobId' => $jobId,
                    'jobTitle' => $job->getTitle(),
                    'applicationId' => $application->getId()
                ];
            }

            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Hire notifications processed successfully.',
                'results' => $results
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error processing hire notifications: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/saved-jobs/apply', name: 'app_provider_saved_jobs_apply', methods: ['POST'])]
    public function applyToSavedJobs(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        // Start debug logging
        $debugLog = "=== APPLY JOBS REQUEST START ===\n";
        $debugLog .= "Time: " . date('Y-m-d H:i:s') . "\n";
        
        // Handle both JSON and form data
        if ($request->headers->get('Content-Type') === 'application/json') {
            // JSON request
            $content = $request->getContent();
            $data = json_decode($content, true);
            $jobIds = $data['jobIds'] ?? [];
            $debugLog .= "Request type: JSON\n";
        } else {
            // Form data request
            $jobIdsParam = $request->request->get('job_ids', '[]');
            if (is_string($jobIdsParam) && str_starts_with($jobIdsParam, '[')) {
                $jobIds = json_decode($jobIdsParam, true) ?? [];
            } else {
                $jobIds = [];
            }
            $debugLog .= "Request type: FORM\n";
        }
        
        $debugLog .= "Job IDs received: " . print_r($jobIds, true) . "\n";
        $debugLog .= "Job IDs count: " . count($jobIds) . "\n";
        
        file_put_contents('C:\\xampp\\htdocs\\locumlancer\\var\\apply_debug.log', $debugLog, FILE_APPEND);
        
        $user = $this->getUser();
        $debugLog = "User ID: " . ($user ? $user->getId() : 'NO USER') . "\n";
        
        if ($user && method_exists($user, 'getProvider')) {
            $provider = $user->getProvider();
            $debugLog .= "Provider: " . ($provider ? $provider->getId() : 'NO PROVIDER') . "\n";
        } else {
            $debugLog .= "User has no getProvider method or no user\n";
        }
        
        file_put_contents('C:\\xampp\\htdocs\\locumlancer\\var\\apply_debug.log', $debugLog, FILE_APPEND);
        
        $appliedCount = 0;
        $alreadyAppliedCount = 0;
        $appliedJobIds = [];
        $removedBookmarkIds = [];
        $alreadyAppliedJobIds = [];

        foreach ($jobIds as $index => $jobId) {
            $debugLog = "Processing job #$index: " . $jobId . "\n";
            file_put_contents('C:\\xampp\\htdocs\\locumlancer\\var\\apply_debug.log', $debugLog, FILE_APPEND);
            
            try {
                // Find the job
                $job = $entityManager->getRepository(Job::class)->find($jobId);
                
                if (!$job) {
                    $debugLog = "❌ Job not found: " . $jobId . "\n";
                    file_put_contents('C:\\xampp\\htdocs\\locumlancer\\var\\apply_debug.log', $debugLog, FILE_APPEND);
                    continue;
                }

                $debugLog = "✅ Found job: " . $job->getId() . " - " . $job->getTitle() . "\n";
                file_put_contents('C:\\xampp\\htdocs\\locumlancer\\var\\apply_debug.log', $debugLog, FILE_APPEND);

                // Check if already applied
                $existingApplication = $entityManager->getRepository(Application::class)
                    ->findOneBy([
                        'provider' => $user->getProvider(), 
                        'job' => $job, 
                        'employer' => $job->getEmployer()
                    ]);
                    
                if ($existingApplication) {
                    $debugLog = "ℹ️ Already applied to job: " . $jobId . " - removing bookmark only\n";
                    file_put_contents('C:\\xampp\\htdocs\\locumlancer\\var\\apply_debug.log', $debugLog, FILE_APPEND);
                    
                    $alreadyAppliedCount++;
                    $alreadyAppliedJobIds[] = $jobId;
                } else {
                    $debugLog = "✅ No existing application found, creating new one\n";
                    file_put_contents('C:\\xampp\\htdocs\\locumlancer\\var\\apply_debug.log', $debugLog, FILE_APPEND);

                    // Create new application
                    $application = new Application();
                    $application->setJob($job);
                    $application->setProvider($user->getProvider());
                    $application->setEmployer($job->getEmployer());
                    $application->setStatus('applied');
                    $application->setAppliedAt(new \DateTime());
                    
                    $entityManager->persist($application);
                    $appliedCount++;
                    $appliedJobIds[] = $jobId;
                    $debugLog = "✅ Created application for job: " . $jobId . "\n";
                    file_put_contents('C:\\xampp\\htdocs\\locumlancer\\var\\apply_debug.log', $debugLog, FILE_APPEND);
                }
                
                // Remove from bookmarks REGARDLESS of whether it was just applied or already applied
                $bookmark = $entityManager->getRepository(Bookmark::class)
                    ->findOneBy(['job' => $job, 'user' => $user]);
                    
                if ($bookmark) {
                    $removedBookmarkIds[] = $bookmark->getId();
                    $entityManager->remove($bookmark);
                    $debugLog = "✅ Removed bookmark for job: " . $jobId . " (Bookmark ID: " . $bookmark->getId() . ")\n";
                    file_put_contents('C:\\xampp\\htdocs\\locumlancer\\var\\apply_debug.log', $debugLog, FILE_APPEND);
                } else {
                    $debugLog = "❌ No bookmark found for job: " . $jobId . "\n";
                    file_put_contents('C:\\xampp\\htdocs\\locumlancer\\var\\apply_debug.log', $debugLog, FILE_APPEND);
                }
                
                $debugLog = "✅ Successfully processed job: " . $jobId . "\n";
                file_put_contents('C:\\xampp\\htdocs\\locumlancer\\var\\apply_debug.log', $debugLog, FILE_APPEND);
                
            } catch (\Exception $e) {
                $debugLog = '❌ Error applying to job ' . $jobId . ': ' . $e->getMessage() . "\n";
                $debugLog .= 'Stack trace: ' . $e->getTraceAsString() . "\n";
                file_put_contents('C:\\xampp\\htdocs\\locumlancer\\var\\apply_debug.log', $debugLog, FILE_APPEND);
                continue;
            }
        }
        
        try {
            $debugLog = "Flushing entity manager...\n";
            file_put_contents('C:\\xampp\\htdocs\\locumlancer\\var\\apply_debug.log', $debugLog, FILE_APPEND);
            
            $entityManager->flush();
            
            $debugLog = "✅ Flush completed\n";
            $debugLog .= "Final applied count: " . $appliedCount . "\n";
            $debugLog .= "Already applied count: " . $alreadyAppliedCount . "\n";
            $debugLog .= "Applied job IDs: " . print_r($appliedJobIds, true) . "\n";
            $debugLog .= "Already applied job IDs: " . print_r($alreadyAppliedJobIds, true) . "\n";
            $debugLog .= "Removed bookmark IDs: " . print_r($removedBookmarkIds, true) . "\n";
            $debugLog .= '=== APPLY JOBS REQUEST END ===' . "\n\n";
            file_put_contents('C:\\xampp\\htdocs\\locumlancer\\var\\apply_debug.log', $debugLog, FILE_APPEND);
            
            // Build success message
            $message = "";
            if ($appliedCount > 0) {
                $message .= "Successfully applied to {$appliedCount} job(s). ";
            }
            if ($alreadyAppliedCount > 0) {
                $message .= "Removed {$alreadyAppliedCount} already applied job(s) from saved jobs.";
            }
            if ($appliedCount === 0 && $alreadyAppliedCount === 0) {
                $message = "No jobs were processed.";
            }
            
            return $this->json([
                'success' => true,
                'message' => $message,
                'appliedCount' => $appliedCount,
                'alreadyAppliedCount' => $alreadyAppliedCount,
                'appliedJobIds' => $appliedJobIds,
                'alreadyAppliedJobIds' => $alreadyAppliedJobIds,
                'removedBookmarkIds' => $removedBookmarkIds
            ]);
        } catch (\Exception $e) {
            $debugLog = "❌ Flush error: " . $e->getMessage() . "\n";
            $debugLog .= 'Stack trace: ' . $e->getTraceAsString() . "\n";
            $debugLog .= '=== APPLY JOBS REQUEST END WITH ERROR ===' . "\n\n";
            file_put_contents('C:\\xampp\\htdocs\\locumlancer\\var\\apply_debug.log', $debugLog, FILE_APPEND);
            
            return $this->json([
                'success' => false,
                'message' => 'Error applying to jobs: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/todos', name: 'app_provider_jobs_todos_list', methods: ['GET'])]
public function getTodos($id, BookmarkRepository $bookmarkRepo, ToDoRepository $todoRepo, Request $request): JsonResponse
{
    $provider = $this->getUser()->getProvider();
    
    // Find the bookmark
    $bookmark = $bookmarkRepo->findOneBy([
        'id' => $id,
        'provider' => $provider
    ]);

    if (!$bookmark) {
        return new JsonResponse(['success' => false, 'message' => 'Bookmark not found'], 404);
    }

    $todos = $todoRepo->findByBookmark($bookmark->getId());
    
    $todoData = [];
    foreach ($todos as $todo) {
        $todoData[] = [
            'id' => $todo->getId(),
            'text' => $todo->getText(),
            'done' => $todo->isDone(),
            'createdAt' => $todo->getCreatedAt()->format('Y-m-d H:i:s')
        ];
    }

    return new JsonResponse([
        'success' => true,
        'items' => $todoData
    ]);
}

#[Route('/{id}/todos/add', name: 'app_provider_jobs_todos_add', methods: ['POST'])]
public function addTodo($id, BookmarkRepository $bookmarkRepo, EntityManagerInterface $em, Request $request): JsonResponse
{
    $provider = $this->getUser()->getProvider();
    
    // Find the bookmark
    $bookmark = $bookmarkRepo->findOneBy([
        'id' => $id,
        'provider' => $provider
    ]);

    if (!$bookmark) {
        return new JsonResponse(['success' => false, 'message' => 'Bookmark not found'], 404);
    }

    $data = json_decode($request->getContent(), true);
    $text = $data['text'] ?? '';

    if (empty($text)) {
        return new JsonResponse(['success' => false, 'message' => 'Todo text cannot be empty'], 400);
    }

    $todo = new ToDo();
    $todo->setProvider($provider);
    $todo->setBookmark($bookmark);
    $todo->setJob($bookmark->getJob());
    $todo->setText($text);

    $em->persist($todo);
    $em->flush();

    return new JsonResponse([
        'success' => true,
        'item' => [
            'id' => $todo->getId(),
            'text' => $todo->getText(),
            'done' => $todo->isDone(),
            'createdAt' => $todo->getCreatedAt()->format('Y-m-d H:i:s')
        ]
    ]);
}

#[Route('/todos/{id}/toggle', name: 'app_provider_jobs_todos_toggle', methods: ['POST'])]
public function toggleTodo($id, ToDoRepository $todoRepo, EntityManagerInterface $em): JsonResponse
{
    $provider = $this->getUser()->getProvider();
    
    $todo = $todoRepo->findOneBy([
        'id' => $id,
        'provider' => $provider
    ]);

    if (!$todo) {
        return new JsonResponse(['success' => false, 'message' => 'Todo not found'], 404);
    }

    $todo->setDone(!$todo->isDone());
    $em->flush();

    return new JsonResponse(['success' => true]);
}

#[Route('/todos/{id}/delete', name: 'app_provider_jobs_todos_delete', methods: ['DELETE'])]
public function deleteTodo($id, ToDoRepository $todoRepo, EntityManagerInterface $em): JsonResponse
{
    $provider = $this->getUser()->getProvider();
    
    $todo = $todoRepo->findOneBy([
        'id' => $id,
        'provider' => $provider
    ]);

    if (!$todo) {
        return new JsonResponse(['success' => false, 'message' => 'Todo not found'], 404);
    }

    $em->remove($todo);
    $em->flush();

    return new JsonResponse(['success' => true]);
}



    
}