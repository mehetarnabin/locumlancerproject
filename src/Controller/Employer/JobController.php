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
use App\Service\JobIdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
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
        WorkflowInterface $jobApplicationWorkflow
    ): Response
    {
        $currentEmployer = $this->getUser()->getEmployer();

        if($job->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this job.");
            return $this->redirectToRoute('app_employer_jobs');
        }

        if(!empty($request->get('status'))) {
            $application = $em->getRepository(Application::class)->findOneBy([
                'job' => $job,
                'employer' => $currentEmployer,
                'status' => $request->get('status'),
                'isArchived' => false
            ], ['id' => 'DESC']);
        }else{
            $application = $em->getRepository(Application::class)->findOneBy([
                'job' => $job,
                'employer' => $currentEmployer,
                'isArchived' => false
            ], ['id' => 'DESC']);
        }

        if($request->get('applicationId')) {
            $application = $em->getRepository(Application::class)->findOneBy([
                'id' => $request->get('applicationId'),
                'employer' => $currentEmployer,
                'isArchived' => false
            ]);

            if($application && $application->getStatus() == 'applied'){
                if ($jobApplicationWorkflow->can($application, 'review')) {
                    $jobApplicationWorkflow->apply($application, 'review');
                    $em->persist($application);
                    $em->flush();
                    $this->addFlash('success', "Application transitioned to " . ucfirst($application->getStatus()));
                }
            }
        }

        // Get ALL applications (unfiltered) for status card counts
        $allApplications = $em->getRepository(Application::class)->findBy([
            'job' => $job,
            'isArchived' => false
        ], ['id' => 'DESC']);

        // Get filtered applications for the list display
        if(!empty($request->get('status'))){
            $applications = $em->getRepository(Application::class)->findBy([
                'job' => $job,
                'status' => $request->get('status'),
                'isArchived' => false
            ], ['id' => 'DESC']);
        }else{
            $applications = $allApplications;
        }

        // Initialize variables for when there's no application
        $provider = null;
        $user = null;
        $educations = [];
        $experiences = [];
        $insurances = [];
        $review = null;
        $documentRequests = [];

        // Only set these if we have an application
        if($application){
            $provider = $application->getProvider();
            $user = $provider->getUser();
            $educations = $em->getRepository(Education::class)->findBy(['user' => $user]);
            $experiences = $em->getRepository(Experience::class)->findBy(['user' => $user]);
            $insurances = $em->getRepository(Insurance::class)->findBy(['user' => $user]);
            $review = $em->getRepository(Review::class)->findOneBy(['application' => $application, 'provider' => $provider]);
            $documentRequests = $em->getRepository(DocumentRequest::class)->findBy(['provider' => $application->getProvider(), 'application' => $application]);
        }

        // Initialize workflow and transitions
        $workflow = null;
        $jobApplicationTransitions = [];
        
        if(count($applications) > 0) {
            $workflow = $workflowRegistry->get(reset($applications), 'job_application_workflow');
            
            foreach ($applications as $jobApplication) {
                try {
                    $jobApplicationTransitions[$jobApplication->getId()->toString()] = array_map(fn($t) => $t->getName(), $workflow->getEnabledTransitions($jobApplication));
                } catch (\LogicException $e) {
                    if ($jobApplication->getStatus() === 'negotiating') {
                        $jobApplication->setStatus('offered');
                    } elseif ($jobApplication->getStatus() === 'accepted') {
                        $jobApplication->setStatus('hired');
                    }
                    $jobApplicationTransitions[$jobApplication->getId()->toString()] = array_map(fn($t) => $t->getName(), $workflow->getEnabledTransitions($jobApplication));
                }
            }
        }

        $statusCounts =  $em->getRepository(Application::class)->getJobApplicationStatusCounts($job->getId());

        return $this->render('employer/job/applications.html.twig', [
            'job' => $job,
            'applicationDetail' => $application,
            'applications' => $applications, // Filtered applications for list display
            'allApplications' => $allApplications, // All applications for status card counts
            'educations' => $educations,
            'experiences' => $experiences,
            'insurances' => $insurances,
            'documentRequests' => $documentRequests,
            'user' => $user,
            'provider' => $provider,
            'review' => $review,
            'jobApplicationTransitions' => $jobApplicationTransitions,
            'statusCounts' => $statusCounts,
            'healthAssessment' => $provider ? $provider->getHealthAssessment() : null,
            'riskAssessment' => $provider ? $provider->getRiskAssessment() : null,
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

        if ($application && $application->getStatus() === 'negotiating') {
            $application->setStatus('offered');
        }
        if ($application && $application->getStatus() === 'accepted') {
            $application->setStatus('hired');
        }

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

    #[Route('/{id}/applications/{applicationId}/detail', name: 'app_employer_job_application_detail_ajax', methods: ['GET'])]
    public function applicationDetailAjax(
        Job $job,
        string $applicationId,
        Request $request,
        EntityManagerInterface $em,
        Registry $workflowRegistry
    ): Response
    {
        $currentEmployer = $this->getUser()->getEmployer();

        if($job->getEmployer() !== $currentEmployer) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $applicationIdUuid = Uuid::fromString($applicationId);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Invalid application ID'], 400);
        }

        $application = $em->getRepository(Application::class)->findOneBy([
            'id' => $applicationIdUuid,
            'job' => $job,
            'isArchived' => false
        ]);

        if(!$application) {
            return $this->json(['error' => 'Application not found'], 404);
        }

        $provider = $application->getProvider();
        $user = $provider->getUser();
        $educations = $em->getRepository(Education::class)->findBy(['user' => $user]);
        $experiences = $em->getRepository(Experience::class)->findBy(['user' => $user]);
        $insurances = $em->getRepository(Insurance::class)->findBy(['user' => $user]);
        $review = $em->getRepository(Review::class)->findOneBy(['application' => $application, 'provider' => $provider]);
        $documentRequests = $em->getRepository(DocumentRequest::class)->findBy(['provider' => $provider, 'application' => $application]);

        if(count([$application]) > 0) {
            $workflow = $workflowRegistry->get($application, 'job_application_workflow');
        }

        $jobApplicationTransitions = [];
        try {
            $jobApplicationTransitions[$application->getId()->toString()] = array_map(fn($t) => $t->getName(), $workflow->getEnabledTransitions($application));
        } catch (\LogicException $e) {
            if ($application->getStatus() === 'negotiating') {
                $application->setStatus('offered');
            } elseif ($application->getStatus() === 'accepted') {
                $application->setStatus('hired');
            }
            $jobApplicationTransitions[$application->getId()->toString()] = array_map(fn($t) => $t->getName(), $workflow->getEnabledTransitions($application));
        }

        return $this->render('employer/job/_application_detail.html.twig', [
            'job' => $job,
            'application' => $application,
            'educations' => $educations,
            'experiences' => $experiences,
            'insurances' => $insurances,
            'documentRequests' => $documentRequests,
            'user' => $user,
            'provider' => $provider,
            'review' => $review,
            'jobApplicationTransitions' => $jobApplicationTransitions,
            'healthAssessment' => $provider->getHealthAssessment(),
            'riskAssessment' => $provider->getRiskAssessment(),
        ]);
    }
}
