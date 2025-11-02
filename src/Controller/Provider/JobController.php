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

#[Route('/provider')]
class JobController extends AbstractController
{
    #[Route('/jobs/{id}/detail', name: 'app_provider_jobs_detail', methods: ['GET'])]
    public function detail(Job $job, ApplicationRepository $applicationRepository): Response
    {
        $applications = $applicationRepository->findBy(['provider' => $this->getUser()->getProvider()]);

        $appliedJobs = [];
        foreach ($applications as $application){
            $appliedJobsIds[] =  (string) $application->getJob()->getId();
        }

        return $this->render('provider/job/detail.html.twig', [
            'job' => $job,
            'appliedJobsIds' => $appliedJobsIds,
        ]);
    }

    #[Route('/jobs/matching', name: 'app_provider_jobs_matching')]
    public function matchingJobs(JobRepository $jobRepository, ApplicationRepository $applicationRepository): Response
    {
        $user = $this->getUser();
        $provider = $user->getProvider();

        $filters['profession'] = $provider->getProfession()?->getId();
        $providerSpecialities = $provider->getSpecialities();
        if(!empty($providerSpecialities)) {
            foreach ($providerSpecialities as $speciality) {
                $filters['speciality_ids'][] = $speciality->getId();
            }
        }
        $filters['state'] = $provider->getDesiredStates() ? implode(',', $provider->getDesiredStates()) : null;

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

        return $this->render('provider/job/matching.html.twig', [
            'jobs' => $jobs,
            'appliedJobsIds' => $appliedJobsIds,
        ]);
    }

    #[Route('/jobs/saved', name: 'app_provider_jobs_saved')]
    public function savedJobs(BookmarkRepository $bookmarkRepository, ApplicationRepository $applicationRepository, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $provider = $user->getProvider();
        
        $bookmarks = $bookmarkRepository->findBy(['user' => $this->getUser()], ['id' => 'DESC']);
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

        $applications = $em->getRepository(Application::class)->findBy(['provider' => $this->getUser()->getProvider()], ['id' => 'DESC'], 5);
        $statusCounts = $em->getRepository(Application::class)->getProviderApplicationStatusCounts($provider->getId());
        $statusCounts[] = [
            'status' => 'saved',
            'count' => count($bookmarks),
        ];

        $totalApplications = $em->createQuery("SELECT count(a.id) as total_applications FROM App\Entity\Application a WHERE a.provider = :provider")
            ->setParameter('provider', $this->getUser()->getProvider()->getId(), UuidType::NAME)
            ->getSingleScalarResult();

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

        $offset = $request->query->get('page', 1);
        $perPage = $request->get('per_page', 10);
        $filters = $request->query->all();
        $filters['provider'] = $this->getUser()->getProvider()->getId();

        $applications = $em->getRepository(Application::class)->getAll($offset, $perPage, $filters);
        $statusCounts = $em->getRepository(Application::class)->getProviderApplicationStatusCounts( $this->getUser()->getProvider()->getId());
         $statusCounts[] = [
            'status' => 'saved',
            'count' => count($bookmarks),
        ];

        $totalApplications = $em->createQuery("SELECT count(a.id) as total_applications FROM App\Entity\Application a WHERE a.provider = :provider")
            ->setParameter('provider', $this->getUser()->getProvider()->getId(), UuidType::NAME)
            ->getSingleScalarResult();

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

    #[Route('/provider/update-rank', name: 'app_update_rank', methods: ['POST'])]
    public function updateRank(Request $request, EntityManagerInterface $em, BookmarkRepository $bookmarkRepo): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $jobId = $data['jobId'] ?? null;
        $rank = $data['rank'] ?? null;

        if ($jobId === null || $rank === null) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid data'], 400);
        }

        try {
            // Fetch the Job entity
            $job = $em->getRepository(Job::class)->find($jobId);
            if (!$job) {
                return new JsonResponse(['success' => false, 'error' => 'Job not found'], 404);
            }

            // Fetch the Bookmark for the current user and job
            $bookmark = $bookmarkRepo->findOneBy([
                'job' => $job,
                'user' => $this->getUser()
            ]);

            if (!$bookmark) {
                return new JsonResponse(['success' => false, 'error' => 'Bookmark not found'], 404);
            }

            // Update rank
            $bookmark->setRank((int) $rank);
            $em->flush();

            return new JsonResponse(['success' => true, 'rank' => $rank]);
        } catch (\Throwable $e) {
            // Return full error in JSON for debugging
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }
}