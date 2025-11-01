<?php

namespace App\Controller;

use App\Entity\Application;
use App\Entity\Bookmark;
use App\Entity\Job;
use App\Entity\JobReport;
use App\Entity\User;
use App\Event\BookmarkEvent;
use App\Repository\JobRepository;
use App\Service\ApplicationService;
use App\Service\OnboardingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class JobController extends AbstractController
{
    #[Route('/jobs', name: 'app_jobs')]
    public function index(Request $request, JobRepository $jobRepository): Response
    {
        $filters = $request->query->all();
        $offset = $request->query->get('page', 1);
        $perPage = $request->get('per_page', 25);
        $filters['blocked'] = false;
        $filters['verified'] = true;
        $filters['status'] = Job::JOB_STATUS_PUBLISHED;

        $jobs = $jobRepository->getAll($offset, $perPage, $filters);
        $jobsArray = $jobs->getIterator()->getArrayCopy();

        if(count($jobsArray) > 0) {
            $jobDetail = $jobsArray[0];
        }

        if($request->get('id')){
            $jobDetail = $jobRepository->findOneBy(['id' => $request->get('id'), 'verified' => true, 'blocked' => false]);
        }

        return $this->render('job/index.html.twig', [
            'jobs' => $jobs,
            'jobDetail' => $jobDetail ?? null,
        ]);
    }

    #[Route('/jobs/detail/{id}', name: 'app_jobs_detail_ajax')]
    public function jobDetail(JobRepository $jobRepository, $id): Response
    {
        $job = $jobRepository->find($id);

        if (!$job) {
            throw $this->createNotFoundException();
        }

        return $this->render('job/_job_detail.html.twig', [
            'jobDetail' => $job,
        ]);
    }

    #[Route('/jobs/report', name: 'app_job_report_ajax', methods: ['POST'])]
    public function reportJob(Request $request, JobRepository $jobRepo, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Not authenticated'], 401);
        }

        if ($user->getUserType() !== User::TYPE_PROVIDER) {
            return new JsonResponse(['error' => 'You are not allowed to report this job'], 400);
        }

        $data = json_decode($request->getContent(), true);
        $job = $jobRepo->find($data['job_id'] ?? null);

        if (!$job || empty($data['text'])) {
            return new JsonResponse(['error' => 'Invalid input'], 400);
        }

        $existingReport = $em->getRepository(JobReport::class)->findOneBy([
            'job' => $job,
            'user' => $user,
        ]);

        if($existingReport) {
            return new JsonResponse(['error' => "You've already reported this job"], 400);
        }

        $report = new JobReport();
        $report->setUser($user);
        $report->setJob($job);
        $report->setText($data['text']);

        $em->persist($report);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/jobs/{id}', name: 'app_job_detail')]
    public function detail(Job $job): Response
    {
        return $this->render('job/detail.html.twig', [
            'job' => $job
        ]);
    }

    #[Route('/jobs/{id}/apply', name: 'app_job_apply')]
    public function apply(
        Job $job,
        EntityManagerInterface $em,
        ApplicationService $applicationService,
        OnboardingService $onboardingService
    ): Response
    {
        $user = $this->getUser();

        if(!$job){
            return new JsonResponse(['status' => 'error', 'message' => 'Job not found']);
        }

        if (!$user  || !$user->getProvider()) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid user']);
        }

        if($onboardingService->isProviderOnboardingCompleted($user) === false){
            return new JsonResponse(['status' => 'error', 'message' => 'Please complete your profile before applying']);
        }

        $application = $em->getRepository(Application::class)->findOneBy(['provider' => $user->getProvider(), 'job' => $job, 'employer' => $job->getEmployer()]);
        if($application){
            return new JsonResponse(['status' => 'success', 'message' => 'You have already applied to the job']);
        }

        $applicationService->createApplication($job, $user);

        return new JsonResponse(['status' => 'success', 'message' => 'Applied for job successfully']);
    }

    #[Route('/jobs/{id}/save', name: 'app_job_save')]
    public function save(Job $job, EntityManagerInterface $em, EventDispatcherInterface $dispatcher): Response
    {
        $user = $this->getUser();

        if(!$job){
            return new JsonResponse(['status' => 'error', 'message' => 'Job not found']);
        }

        if (!$user  || !$user->getProvider()) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid user']);
        }

        $bookmark = $em->getRepository(Bookmark::class)->findOneBy(['user' => $user, 'job' => $job]);
        if($bookmark){
            return new JsonResponse(['status' => 'success', 'message' => 'You have already saved the job']);
        }

        $bookmark = new Bookmark();

        $bookmark->setJob($job);
        $bookmark->setUser($user);

        $em->persist($bookmark);
        $em->flush();

        $dispatcher->dispatch(new BookmarkEvent($bookmark), BookmarkEvent::BOOKMARK_CREATED);

        return new JsonResponse(['status' => 'success', 'message' => 'Job saved successfully']);
    }
}
