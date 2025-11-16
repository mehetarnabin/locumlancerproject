<?php

namespace App\Controller\Employer;

use App\Entity\Application;
use App\Entity\Document;
use App\Entity\DocumentRequest;
use App\Entity\Education;
use App\Entity\Employer;
use App\Entity\Experience;
use App\Entity\Insurance;
use App\Entity\Invoice;
use App\Entity\Job;
use App\Entity\Review;
use App\Event\JobEvent;
use App\Form\JobType;
use App\Repository\ApplicationRepository;
use App\Repository\EmployerRepository;
use App\Repository\JobRepository;
use App\Service\ProfileAnalyticsService;;
use App\Service\JobIdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\WorkflowInterface;

#[Route('/employer/jobs')]
class JobController extends AbstractController
{
    #[Route('/', name: 'app_employer_jobs', methods: ['GET'])]
    public function index(JobRepository $jobRepository, Request $request, Registry $workflowRegistry): Response
    {
        $employer = $this->getUser()->getEmployer();
        $offset = $request->query->get('page', 1);
        $perPage = $request->get('per_page', 25);
        $filters = $request->query->all();
        $filters['employer'] = $employer->getId();

        $jobs = $jobRepository->getAll($offset, $perPage, $filters);
        $jobsArray = $jobs->getIterator()->getArrayCopy();

        if($jobs->getNbResults() > 0) {
            $workflow = $workflowRegistry->get(reset($jobsArray), 'job_workflow');
        }

        $jobTransitions = [];
        foreach ($jobs as $job) {
            $jobTransitions[$job->getId()->toString()] = array_map(fn($t) => $t->getName(), $workflow->getEnabledTransitions($job));
        }

        return $this->render('employer/job/index.html.twig', [
            'jobs' => $jobs,
            'jobTransitions' => $jobTransitions,
        ]);
    }

    #[Route('/past-jobs', name: 'app_employer_jobs_past', methods: ['GET'])]
    public function pastJobs(JobRepository $jobRepository, EmployerRepository $employerRepository): Response
    {
        $employer = $this->getUser()->getEmployer();
        return $this->render('employer/job/past-jobs.html.twig', [
            'jobs' => $jobRepository->getEmployerPastJobs($employer->getId()),
        ]);
    }

    #[Route('/new', name: 'app_employer_job_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $dispatcher,
        JobIdGenerator $jobIdGenerator
    ): Response
    {
        $user = $this->getUser();
        $employer = $user->getEmployer();

        $job = new Job();
        $job->setJobId($jobIdGenerator->generate());
        $form = $this->createForm(JobType::class, $job);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $job->setUser($user);
            $job->setEmployer($employer);
            $entityManager->persist($job);
            $entityManager->flush();

            $dispatcher->dispatch(new JobEvent($job), JobEvent::JOB_CREATED);

            return $this->redirectToRoute('app_employer_jobs', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('employer/job/new.html.twig', [
            'job' => $job,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_employer_job_show', methods: ['GET'])]
    public function show(Job $job, ApplicationRepository $applicationRepository): Response
    {
        $currentEmployer = $this->getUser()->getEmployer();

        if($job->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this job.");
            return $this->redirectToRoute('app_employer_jobs');
        }

        $applications = $applicationRepository->findBy(['job' => $job], ['id' => 'DESC']);
        return $this->render('employer/job/show.html.twig', [
            'job' => $job,
            'applications' => $applications
        ]);
    }

    #[Route('/{id}/applications', name: 'app_employer_job_applications', methods: ['GET'])]
    public function applications(
        Job $job,
        Request $request,
        EntityManagerInterface $em,
        Registry $workflowRegistry,
        WorkflowInterface $jobApplicationWorkflow,
        ProfileAnalyticsService $analyticsService
    ): Response
    {
        $currentEmployer = $this->getUser()->getEmployer();

        if($job->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this job.");
            return $this->redirectToRoute('app_employer_jobs');
        }

        if(!empty($request->get('status'))) {
            $application = $em->getRepository(Application::class)->findOneBy(['job' => $job, 'employer' => $currentEmployer, 'status' => $request->get('status')], ['id' => 'DESC']);
        }else{
            $application = $em->getRepository(Application::class)->findOneBy(['job' => $job, 'employer' => $currentEmployer], ['id' => 'DESC']);
        }

        if($request->get('applicationId')) {
            $application = $em->getRepository(Application::class)->findOneBy(['id' => $request->get('applicationId'), 'employer' => $currentEmployer]);

            if($application->getStatus() == 'applied'){
                if ($jobApplicationWorkflow->can($application, 'review')) {
                    $jobApplicationWorkflow->apply($application, 'review');
                    $em->persist($application);
                    $em->flush();
                    $this->addFlash('success', "Application transitioned to " . ucfirst($application->getStatus()));
                }
            }
        }

        if(!$application){
            return $this->render('employer/job/applications-empty.html.twig', ['job' => $job,]);
        }

        $provider = $application->getProvider();

        $analyticsService->recordProfileView($provider, $this->getUser());


        $user = $provider->getUser();

        $educations = $em->getRepository(Education::class)->findBy(['user' => $user]);
        $experiences = $em->getRepository(Experience::class)->findBy(['user' => $user]);
        $insurances = $em->getRepository(Insurance::class)->findBy(['user' => $user]);
        $review = $em->getRepository(Review::class)->findOneBy(['application' => $application, 'provider' => $provider]);

        $documentRequests = $em->getRepository(DocumentRequest::class)->findBy(['provider' => $application->getProvider(), 'application' => $application]);

        if(!empty($request->get('status'))){
            $applications = $em->getRepository(Application::class)->findBy(['job' => $job, 'status' => $request->get('status')], ['id' => 'DESC']);
        }else{
            $applications = $em->getRepository(Application::class)->findBy(['job' => $job], ['id' => 'DESC']);
        }

        if(count($applications) > 0) {
            $workflow = $workflowRegistry->get(reset($applications), 'job_application_workflow');
        }

        $jobApplicationTransitions = [];
        foreach ($applications as $jobApplication) {
            $jobApplicationTransitions[$jobApplication->getId()->toString()] = array_map(fn($t) => $t->getName(), $workflow->getEnabledTransitions($jobApplication));
        }

        $statusCounts =  $em->getRepository(Application::class)->getApplicationStatusCounts();

        return $this->render('employer/job/applications.html.twig', [
            'job' => $job,
            'applicationDetail' => $application,
            'applications' => $applications,
            'educations' => $educations,
            'experiences' => $experiences,
            'insurances' => $insurances,
            'documentRequests' => $documentRequests,
            'user' => $user,
            'provider' => $provider,
            'review' => $review,
            'jobApplicationTransitions' => $jobApplicationTransitions,
            'statusCounts' => $statusCounts,
            'healthAssessment' => $provider->getHealthAssessment(),
            'riskAssessment' => $provider->getRiskAssessment(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_employer_job_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Job $job, EntityManagerInterface $entityManager): Response
    {
        $currentEmployer = $this->getUser()->getEmployer();

        if($job->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this job.");
            return $this->redirectToRoute('app_employer_jobs');
        }

        $form = $this->createForm(JobType::class, $job);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_employer_jobs', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('employer/job/edit.html.twig', [
            'job' => $job,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_employer_job_delete', methods: ['GET'])]
    public function delete(Job $job, EntityManagerInterface $entityManager): Response
    {
        $currentEmployer = $this->getUser()->getEmployer();

        if($job->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this job.");
            return $this->redirectToRoute('app_employer_jobs');
        }

        $entityManager->remove($job);

        try {
            $entityManager->flush();

            $this->addFlash('success', 'Job has been deleted.');
        }catch (\Exception $e){
            $this->addFlash('error', 'Unable to delete job. This job has applications.');
        }

        return $this->redirectToRoute('app_employer_jobs', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/transition/{transition}', name: 'app_employer_job_transition')]
    public function transitionJob(Job $job, string $transition, WorkflowInterface $jobWorkflow, EntityManagerInterface $em): RedirectResponse
    {
        $currentEmployer = $this->getUser()->getEmployer();

        if($job->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this job.");
            return $this->redirectToRoute('app_employer_jobs');
        }

        if ($jobWorkflow->can($job, $transition)) {

            if($transition == 'close'){
                $job->setExpirationDate(new \DateTime());
            }

            $jobWorkflow->apply($job, $transition);
            $em->persist($job);
            $em->flush();
            $this->addFlash('success', "Job ".$job->getTitle()." transitioned to " . ucfirst($job->getStatus()));
        } else {
            $this->addFlash('error', "Invalid transition.");
        }

        return $this->redirectToRoute('app_employer_jobs');
    }

    #[Route('/{id}/transition/{transition}/application/{applicationId}', name: 'app_employer_job_application_transition')]
    public function transitionJobApplication(Job $job, string $transition, string $applicationId, WorkflowInterface $jobApplicationWorkflow, EntityManagerInterface $em, Request $request): RedirectResponse
    {
        $currentEmployer = $this->getUser()->getEmployer();

        if($job->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this job.");
            return $this->redirectToRoute('app_employer_jobs');
        }

        $application = $em->getRepository(Application::class)->findOneBy(['id' => $applicationId, 'employer' => $currentEmployer]);

        if ($jobApplicationWorkflow->can($application, $transition)) {
            $jobApplicationWorkflow->apply($application, $transition);
            $em->persist($application);
            $em->flush();
            $this->addFlash('success', "Application transitioned to " . ucfirst($application->getStatus()));
        } else {
            $this->addFlash('error', "Invalid transition.");
        }

        return $this->redirectToRoute('app_employer_job_applications', ['id' => $job->getId(), 'applicationId' => $application->getId()]);
    }

    #[Route('/application/{id}/download-resume', name: 'app_employer_application_download_resume', methods: ['GET'])]
    public function downloadResume(
        Application $application,
        ProfileAnalyticsService $analyticsService,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): Response {
        $currentEmployer = $this->getUser()->getEmployer();

        // Check if employer has access to this application
        if($application->getJob()->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this resume.");
            return $this->redirectToRoute('app_employer_jobs');
        }

        $provider = $application->getProvider();
        $user = $provider->getUser();
        
        // 🎯 COUNT BUTTON - Record resume download
        $analyticsService->recordResumeDownload($provider, $this->getUser());
        
        $cvFilePath = $uploadDirectory.'/'. $user->getId().'/'.$provider->getCvFilename();
        
        if(!file_exists($cvFilePath)) {
            $this->addFlash('error', 'CV file not found.');
            return $this->redirectToRoute('app_employer_job_applications', ['id' => $application->getJob()->getId()]);
        }
        
        return $this->file($cvFilePath);
    }
}
