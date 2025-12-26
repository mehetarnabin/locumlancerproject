<?php

namespace App\Controller\Recruiter;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use App\Entity\Application;
use App\Entity\Document;
use App\Entity\DocumentRequest;
use App\Entity\Education;
use App\Entity\Employer;
use App\Entity\Experience;
use App\Entity\Insurance;
use App\Entity\Invoice;
use App\Entity\Job;
use App\Entity\JobRecruiter;
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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\WorkflowInterface;

#[Route('/recruiter/jobs')]
class JobController extends AbstractController
{
    /**
     * Map database status to display name (matching frontend exactly)
     */
    private function getStatusDisplayName(string $status): string
    {
        // Ensure status is a string, not an array
        if (!is_string($status)) {
            $status = is_array($status) ? (string)reset($status) : (string)$status;
        }

        $statusDisplayMap = [
            'applied' => 'Applied',
            'shortlisted' => 'Shortlisted',
            'interviewing' => 'Interviewing',
            'negotiating' => 'Negotiating',
            'accepted' => 'Accepted',
            'completed' => 'Completed',
            // Legacy status mappings for backward compatibility
            'in_review' => 'Shortlisted',
            'interview' => 'Interviewing',
            'offered' => 'Negotiating',
            'hired' => 'Accepted'
        ];

        $statusKey = strtolower($status);
        return isset($statusDisplayMap[$statusKey]) ? $statusDisplayMap[$statusKey] : ucfirst($status);
    }

    #[Route('/', name: 'app_recruiter_jobs', methods: ['GET'])]
    public function index(JobRepository $jobRepository, Request $request, Registry $workflowRegistry): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $recruiter = $user->getRecruiter();

        if (!$recruiter) {
            // Fallback or error if not a recruiter
            return $this->render('recruiter/job/index.html.twig', [
                'jobs' => [],
                'status_counts' => [],
            ]);
        }

        // Fetch jobs assigned to this recruiter
        // Assuming JobRepository has a method or we use query builder
        // $jobs = $jobRepository->findAssignedToRecruiter($recruiter);
        // Or simpler: $recruiter->getJobRecruiters()->map(fn($jr) => $jr->getJob())
        // But we want pagination/sorting potentially.
        // Let's use the repository to get a query builder or list.

        $jobs = [];
        $jobTransitions = [];
        // TODO: Only get jobs that are actually "owned" or "managed" by recruiter
        // Logic: Recruiter -> JobRecruiter -> Job
        foreach ($recruiter->getJobRecruiters() as $jr) {
            $job = $jr->getJob();
            $jobs[] = $job;

            // Calculate available transitions for each job
            $workflow = $workflowRegistry->get($job, 'job_workflow');
            $transitions = [];
            foreach ($workflow->getEnabledTransitions($job) as $transition) {
                $transitions[] = $transition->getName();
            }
            $jobTransitions[$job->getId()->toString()] = $transitions;
        }

        $statusColors = [
            'draft' => 'secondary',
            'published' => 'success',
            'paused' => 'warning',
            'closed' => 'dark',
        ];

        return $this->render('recruiter/job/index.html.twig', [
            'jobs' => $jobs,
            'jobTransitions' => $jobTransitions,
            'statusColors' => $statusColors,
            'status_counts' => [], // TODO: Calculate status counts for recruiter context if needed
        ]);
    }

    #[Route('/past-jobs', name: 'app_recruiter_jobs_past', methods: ['GET'])]
    public function pastJobs(JobRepository $jobRepository, EmployerRepository $employerRepository): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $recruiter = $user->getRecruiter();

        // Placeholder for past jobs logic
        return $this->render('recruiter/job/index.html.twig', [
            'jobs' => [], // Implement past jobs filter if needed
            'status_counts' => [],
        ]);
    }

    #[Route('/new', name: 'app_recruiter_job_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $dispatcher
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $recruiter = $user->getRecruiter();

        if (!$recruiter) {
            $this->addFlash('error', 'Only recruiters can create jobs.');
            return $this->redirectToRoute('app_recruiter_jobs');
        }

        $job = new Job();
        // $job->setUser($user); // Set later

        $form = $this->createForm(JobType::class, $job, [
            'is_recruiter' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $job->setUser($user);

            // Set Employer from form (mapped to false in form, but handled here via get)
            // Wait, in JobType I used mapped=true (default) inside the if block?
            // "builder->add('employer', EntityType::class, ...)" without 'mapped' => false implies it's mapped.
            // But Job has "private ?Employer $employer = null;". So it should map automatically!
            // Let's verify Job entity again. Yes, it has getEmployer/setEmployer.
            // So automatic mapping works.

            // Set status (default to published for now as Recruiters might not need draft flow or can block it later)
            // Or use default from entity (draft). Let's stick to entity default (draft) but maybe redirect to payment?
            // Wait, Recruiters don't pay per job usually? 
            // Implementation plan says "Persist Job and JobRecruiter".
            // If Recruiter posts, they are the "owner" contextually.

            $job->setStatus(Job::JOB_STATUS_PUBLISHED); // Auto-publish for recruiters for now? Or keep draft.
            // The Task says "Recruiter is unable to create a job".
            // Let's set to PUBLISHED to avoid hidden drafts.

            $entityManager->persist($job);

            // Create association
            $jobRecruiter = new JobRecruiter();
            $jobRecruiter->setJob($job);
            $jobRecruiter->setRecruiter($recruiter);
            // Default commission?
            // If JobType has commissionRate field, we should use it?
            // "add('commissionRate', null, ['mapped' => false..."
            // So we need to get it manually.

            $commissionRate = $form->get('commissionRate')->getData();
            if ($commissionRate !== null) {
                $jobRecruiter->setCommissionRate((string)$commissionRate);
            }

            $entityManager->persist($jobRecruiter);
            $entityManager->flush();

            // Dispatch event?
            // $dispatcher->dispatch(new JobEvent($job), JobEvent::CREATED);

            $this->addFlash('success', 'Job created successfully.');

            return $this->redirectToRoute('app_recruiter_jobs', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('recruiter/job/new.html.twig', [
            'job' => $job,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_recruiter_job_show', methods: ['GET'])]
    public function show(Job $job, ApplicationRepository $applicationRepository, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $recruiter = $user->getRecruiter();

        // Check assignment
        $isAssigned = false;
        if ($recruiter) {
            $jobRecruiter = $em->getRepository(JobRecruiter::class)->findOneBy(['job' => $job, 'recruiter' => $recruiter]);
            if ($jobRecruiter) {
                $isAssigned = true;
            }
        }

        if (!$isAssigned) {
            $this->addFlash('error', "You don't have access to this job.");
            return $this->redirectToRoute('app_recruiter_jobs');
        }

        // Filter applications for this recruiter? 
        // Or show all if they are assigned? Usually Recruiter sees applications they manage.
        // But for now, maybe show all or filter. Let's show all for the job if assigned.
        $applications = $applicationRepository->findBy(['job' => $job], ['id' => 'DESC']);

        return $this->render('recruiter/job/show.html.twig', [
            'job' => $job,
            'applications' => $applications,
        ]);
    }

    #[Route('/{id}/applications', name: 'app_recruiter_job_applications', methods: ['GET'])]
    public function applications(
        Job $job,
        Request $request,
        ApplicationRepository $applicationRepository,
        EntityManagerInterface $em,
        Registry $workflowRegistry,
        WorkflowInterface $jobApplicationWorkflow
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $recruiter = $user->getRecruiter();
        $assignmentCheck = $em->getRepository(JobRecruiter::class)->findOneBy(['job' => $job, 'recruiter' => $recruiter]);

        if (!$recruiter || !$assignmentCheck) {
            $this->addFlash('error', "You don't have access to this job.");
            return $this->redirectToRoute('app_recruiter_jobs');
        }

        $currentEmployer = $job->getEmployer();

        // Priority: Get application by applicationId if provided (most specific)
        $applicationIdValue = $request->query->get('applicationId');
        if (!empty($applicationIdValue) && is_string($applicationIdValue)) {
            $application = $em->getRepository(Application::class)->findOneBy([
                'id' => $applicationIdValue,
                'employer' => $currentEmployer,
                'isArchived' => false
            ]);

            if ($application && $application->getStatus() == 'applied') {
                if ($jobApplicationWorkflow->can($application, 'review')) {
                    $jobApplicationWorkflow->apply($application, 'review');
                    $em->persist($application);
                    $em->flush();
                    $displayStatus = $this->getStatusDisplayName($application->getStatus());
                    $this->addFlash('success', "Application transitioned to " . $displayStatus);
                }
            }
        } else {
            $statusValue = $request->query->get('status');
            if (!empty($statusValue) && is_string($statusValue)) {
                // If no applicationId, try to get first application with the requested status
                $application = $em->getRepository(Application::class)->findOneBy([
                    'job' => $job,
                    'employer' => $currentEmployer,
                    'status' => $statusValue,
                    'isArchived' => false
                ], ['id' => 'DESC']);
            } else {
                // Default: get first application
                $application = $em->getRepository(Application::class)->findOneBy([
                    'job' => $job,
                    'employer' => $currentEmployer,
                    'isArchived' => false
                ], ['id' => 'DESC']);
            }
        }

        // Get ALL applications (unfiltered) for status card counts
        $allApplications = $em->getRepository(Application::class)->findBy([
            'job' => $job,
            'isArchived' => false
        ], ['id' => 'DESC']);

        // Get filtered applications for the list display using query builder
        $qb = $em->getRepository(Application::class)->createQueryBuilder('a')
            ->join('a.job', 'j')
            ->where('a.isArchived = false')
            ->andWhere('a.employer = :employer')
            ->setParameter('employer', $currentEmployer);

        // If jobId filter is provided, allow filtering across all jobs
        // Otherwise, restrict to the current job
        $jobIdValue = $request->query->get('jobId');
        if (!empty($jobIdValue) && is_string($jobIdValue)) {
            $jobIdFilter = trim($jobIdValue);
            $qb->andWhere('j.jobId LIKE :jobId')
                ->setParameter('jobId', '%' . $jobIdFilter . '%');
        } else {
            // No jobId filter, restrict to current job
            $qb->andWhere('a.job = :job')
                ->setParameter('job', $job);
        }

        // Apply status filter with backward compatibility
        $statusValue = $request->query->get('status');
        if (!empty($statusValue) && is_string($statusValue)) {
            $statusFilter = $statusValue;

            // Handle status mapping for backward compatibility
            if ($statusFilter === 'shortlisted') {
                $qb->andWhere('a.status IN (:status)')
                    ->setParameter('status', ['shortlisted', 'in_review'], \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
            } elseif ($statusFilter === 'interviewing') {
                $qb->andWhere('a.status IN (:status)')
                    ->setParameter('status', ['interviewing', 'interview'], \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
            } elseif ($statusFilter === 'negotiating') {
                $qb->andWhere('a.status IN (:status)')
                    ->setParameter('status', ['negotiating', 'offered'], \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
            } elseif ($statusFilter === 'accepted') {
                $qb->andWhere('a.status IN (:status)')
                    ->setParameter('status', ['accepted', 'hired'], \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
            } else {
                $qb->andWhere('a.status = :status')
                    ->setParameter('status', $statusFilter);
            }
        }

        // Apply location filter (city or state)
        $locationValue = $request->query->get('location');
        if (!empty($locationValue) && is_string($locationValue)) {
            $locationFilter = trim($locationValue);
            $qb->andWhere('(LOWER(j.city) LIKE :location OR LOWER(j.state) LIKE :location)')
                ->setParameter('location', '%' . strtolower($locationFilter) . '%');
        }

        // Apply salary range filters
        $salaryMinValue = $request->query->get('salaryMin');
        if (!empty($salaryMinValue) && (is_string($salaryMinValue) || is_numeric($salaryMinValue))) {
            $qb->andWhere('j.payRateHourly >= :salaryMin')
                ->setParameter('salaryMin', (float)$salaryMinValue);
        }
        $salaryMaxValue = $request->query->get('salaryMax');
        if (!empty($salaryMaxValue) && (is_string($salaryMaxValue) || is_numeric($salaryMaxValue))) {
            $qb->andWhere('j.payRateHourly <= :salaryMax')
                ->setParameter('salaryMax', (float)$salaryMaxValue);
        }

        // Apply category/work type filter
        $categoryValue = $request->query->get('category');
        if (!empty($categoryValue) && is_string($categoryValue)) {
            $category = strtolower(trim($categoryValue));
            if ($category === 'locums') {
                $qb->andWhere('j.workType = :workType')
                    ->setParameter('workType', 'locums');
            } elseif ($category === 'parttime' || $category === 'part-time') {
                $qb->andWhere('(j.workType = :workType1 OR j.workType = :workType2)')
                    ->setParameter('workType1', 'parttime')
                    ->setParameter('workType2', 'part-time');
            } elseif ($category === 'fulltime' || $category === 'full-time') {
                $qb->andWhere('(j.workType = :workType1 OR j.workType = :workType2)')
                    ->setParameter('workType1', 'fulltime')
                    ->setParameter('workType2', 'full-time');
            }
        }

        // Apply date applied filter (days)
        $daysValue = $request->query->get('days');
        if (!empty($daysValue) && (is_string($daysValue) || is_numeric($daysValue))) {
            $days = (int)$daysValue;
            $date = new \DateTime();
            $date->modify('-' . $days . ' days');
            $qb->andWhere('a.createdAt >= :date')
                ->setParameter('date', $date);
        }

        $qb->orderBy('a.id', 'DESC');
        $applications = $qb->getQuery()->getResult();

        // IMPORTANT: If we have a selected applicationId, ensure it's in the list
        // This handles cases where status was just updated
        $applicationIdValue = $request->query->get('applicationId');
        if (!empty($applicationIdValue) && is_string($applicationIdValue) && $application) {
            $applicationInList = false;
            $applicationIdStr = $applicationIdValue;

            foreach ($applications as $app) {
                if ($app->getId()->toString() === $applicationIdStr) {
                    $applicationInList = true;
                    break;
                }
            }

            // If the selected application is not in the filtered list, check its actual status
            if (!$applicationInList) {
                // Re-fetch the application to ensure we have the latest status from DB
                $em->clear();
                $freshApplication = $em->getRepository(Application::class)->findOneBy([
                    'id' => $applicationIdStr,
                    'employer' => $currentEmployer,
                    'isArchived' => false
                ]);

                if ($freshApplication) {
                    $statusFilterValue = $request->query->get('status');
                    $statusFilter = is_string($statusFilterValue) ? $statusFilterValue : null;
                    // If the fresh application's status matches the filter, add it to the list
                    if ($statusFilter && $freshApplication->getStatus() === $statusFilter) {
                        array_unshift($applications, $freshApplication);
                        $application = $freshApplication;
                    } elseif (!$statusFilter) {
                        // No status filter, just add it
                        array_unshift($applications, $freshApplication);
                        $application = $freshApplication;
                    } else {
                        // Status doesn't match - this means the update didn't work
                        // Force update the status and add to list
                        $freshApplication->setStatus($statusFilter);
                        $em->persist($freshApplication);
                        $em->flush();
                        array_unshift($applications, $freshApplication);
                        $application = $freshApplication;
                    }
                }
            }
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
        if ($application) {
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

        if (count($applications) > 0) {
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

        // Get status counts - clear cache first to ensure fresh data
        $em->getConfiguration()->getQueryCache()->clear();
        $statusCounts =  $em->getRepository(Application::class)->getJobApplicationStatusCounts($job->getId());

        // Format statusCounts for easier template access - map database statuses to display statuses
        $statusCountsFormatted = [
            'applied' => 0,
            'shortlisted' => 0,
            'interviewing' => 0,
            'negotiating' => 0,
            'accepted' => 0,
            'completed' => 0
        ];

        foreach ($statusCounts as $row) {
            // Safely get status - ensure it's a string, not an array
            // First check if row is an array and has the 'status' key
            if (!is_array($row) || !array_key_exists('status', $row)) {
                continue;
            }

            $statusValue = $row['status'];
            // If status is an array, skip this row
            if (is_array($statusValue)) {
                continue;
            }

            $dbStatus = is_string($statusValue) ? strtolower($statusValue) : '';
            $count = 0;

            // Safely get count
            if (array_key_exists('count', $row) && !is_array($row['count'])) {
                $count = (int) $row['count'];
            }

            // Skip if status is not a valid string
            if (empty($dbStatus)) {
                continue;
            }

            // Map database statuses to display statuses
            if ($dbStatus === 'applied') {
                $statusCountsFormatted['applied'] += $count;
            } elseif ($dbStatus === 'in_review' || $dbStatus === 'shortlisted') {
                $statusCountsFormatted['shortlisted'] += $count;
            } elseif ($dbStatus === 'interview' || $dbStatus === 'interviewing') {
                $statusCountsFormatted['interviewing'] += $count;
            } elseif ($dbStatus === 'offered' || $dbStatus === 'negotiating') {
                $statusCountsFormatted['negotiating'] += $count;
            } elseif ($dbStatus === 'hired' || $dbStatus === 'accepted') {
                $statusCountsFormatted['accepted'] += $count;
            } elseif ($dbStatus === 'completed') {
                $statusCountsFormatted['completed'] += $count;
            }
        }

        return $this->render('recruiter/job/applications.html.twig', [
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
            'statusCounts' => $statusCounts, // Raw array from repository
            'statusCountsFormatted' => $statusCountsFormatted, // Formatted array for easy template access
            'healthAssessment' => $provider ? $provider->getHealthAssessment() : null,
            'riskAssessment' => $provider ? $provider->getRiskAssessment() : null,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_recruiter_job_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Job $job, EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $recruiter = $user->getRecruiter();
        $assignmentCheck = $entityManager->getRepository(JobRecruiter::class)->findOneBy(['job' => $job, 'recruiter' => $recruiter]);

        if (!$recruiter || !$assignmentCheck) {
            $this->addFlash('error', "You don't have access to this job.");
            return $this->redirectToRoute('app_recruiter_jobs');
        }

        // Use Job's employer for context if needed, but for edit form we edit the job directly
        // Note: Recruiter modifying Job might not be desired, but code allowed it in Employer module.
        // We allow it here if they are assigned.

        $form = $this->createForm(JobType::class, $job);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_recruiter_jobs', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('recruiter/job/edit.html.twig', [
            'job' => $job,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_recruiter_job_delete', methods: ['GET'])]
    public function delete(Job $job, EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $recruiter = $user->getRecruiter();
        $assignmentCheck = $entityManager->getRepository(JobRecruiter::class)->findOneBy(['job' => $job, 'recruiter' => $recruiter]);

        if (!$recruiter || !$assignmentCheck) {
            $this->addFlash('error', "You don't have access to this job.");
            return $this->redirectToRoute('app_recruiter_jobs');
        }

        $entityManager->remove($job);

        try {
            $entityManager->flush();

            $this->addFlash('success', 'Job has been deleted.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Unable to delete job. This job has applications.');
        }

        return $this->redirectToRoute('app_recruiter_jobs', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/transition/{transition}', name: 'app_recruiter_job_transition')]
    public function transitionJob(Job $job, string $transition, WorkflowInterface $jobWorkflow, EntityManagerInterface $em): RedirectResponse
    {
        $user = $this->getUser();
        $recruiter = $user->getRecruiter();
        $assignmentCheck = $em->getRepository(JobRecruiter::class)->findOneBy(['job' => $job, 'recruiter' => $recruiter]);

        if (!$recruiter || !$assignmentCheck) {
            $this->addFlash('error', "You don't have access to this job.");
            return $this->redirectToRoute('app_recruiter_jobs');
        }

        if ($jobWorkflow->can($job, $transition)) {

            if ($transition == 'close') {
                $job->setExpirationDate(new \DateTime());
            }

            $jobWorkflow->apply($job, $transition);
            $em->persist($job);
            $em->flush();
            $this->addFlash('success', "Job " . $job->getTitle() . " transitioned to " . ucfirst($job->getStatus()));
        } else {
            $this->addFlash('error', "Invalid transition.");
        }

        return $this->redirectToRoute('app_recruiter_jobs');
    }

    #[Route('/{id}/transition/{transition}/application/{applicationId}', name: 'app_recruiter_job_application_transition')]
    public function transitionJobApplication(Job $job, string $transition, string $applicationId, WorkflowInterface $jobApplicationWorkflow, EntityManagerInterface $em, Request $request)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $recruiter = $user->getRecruiter();
        $assignmentCheck = $em->getRepository(JobRecruiter::class)->findOneBy(['job' => $job, 'recruiter' => $recruiter]);

        if (!$recruiter || !$assignmentCheck) {
            $this->addFlash('error', "You don't have access to this job.");
            return $this->redirectToRoute('app_recruiter_jobs');
        }

        $currentEmployer = $job->getEmployer();

        $application = $em->getRepository(Application::class)->findOneBy(['id' => $applicationId, 'employer' => $currentEmployer]);

        if (!$application) {
            $this->addFlash('error', "Application not found.");
            return $this->redirectToRoute('app_recruiter_job_applications', ['id' => $job->getId()]);
        }

        // Map legacy statuses to new statuses for backward compatibility
        $currentStatus = $application->getStatus();
        // Ensure currentStatus is a string, not an array
        if (!is_string($currentStatus)) {
            $currentStatus = is_array($currentStatus) ? (string)reset($currentStatus) : (string)$currentStatus;
        }
        $legacyStatusMap = [
            'in_review' => 'shortlisted',
            'interview' => 'interviewing',
            'offered' => 'negotiating',
            'hired' => 'accepted'
        ];

        if (is_string($currentStatus) && isset($legacyStatusMap[$currentStatus])) {
            $application->setStatus($legacyStatusMap[$currentStatus]);
            $em->persist($application);
            $em->flush();
        }

        $oldStatus = $application->getStatus();
        $newStatus = null;
        $statusUpdated = false;

        // Use workflow for all transitions
        if ($jobApplicationWorkflow->can($application, $transition)) {
            $jobApplicationWorkflow->apply($application, $transition);
            $em->persist($application);
            $em->flush();
            $em->clear();
            $application = $em->getRepository(Application::class)->findOneBy(['id' => $applicationId, 'employer' => $currentEmployer]);
            $newStatus = $application ? $application->getStatus() : null;
            $statusUpdated = true;
            // Ensure newStatus is a string before passing to getStatusDisplayName
            $statusForDisplay = is_string($newStatus) ? $newStatus : (is_string($transition) ? $transition : 'unknown');
            $displayStatus = $this->getStatusDisplayName($statusForDisplay);

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'message' => "Application transitioned to " . $displayStatus,
                    'newStatus' => $newStatus,
                    'displayStatus' => $displayStatus,
                    'statusCounts' => $em->getRepository(Application::class)->getEmployerApplicationStatusCounts($job->getEmployer()->getId()),
                ]);
            }

            $this->addFlash('success', "Application transitioned to " . $displayStatus);
        } else {
            $errorMsg = "Invalid transition '{$transition}' from current status: " . $application->getStatus();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $errorMsg
                ], 400);
            }

            $this->addFlash('error', $errorMsg);
            return $this->redirectToRoute('app_recruiter_job_applications', [
                'id' => $job->getId(),
                'applicationId' => $application->getId()
            ]);
        }

        // Get the final status - reload application if needed to verify it was saved
        $em->clear(); // Clear to ensure fresh data
        $finalApplication = $em->getRepository(Application::class)->findOneBy([
            'id' => $applicationId,
            'employer' => $currentEmployer,
            'isArchived' => false
        ]);

        if (!$finalApplication) {
            $this->addFlash('error', "Application not found after status update.");
            return $this->redirectToRoute('app_recruiter_job_applications', [
                'id' => $job->getId()
            ]);
        }

        // Verify the status was actually updated
        $finalStatus = $finalApplication->getStatus();
        // Ensure finalStatus is a string, not an array
        if (!is_string($finalStatus)) {
            $finalStatus = is_array($finalStatus) ? (string)reset($finalStatus) : (string)$finalStatus;
        }

        // Double-check: if status doesn't match expected, try to fix it
        if ($transition === 'hire' && $finalStatus !== 'hired') {
            $finalApplication->setStatus('hired');
            $em->persist($finalApplication);
            $em->flush();

            // Force direct database update
            $connection = $em->getConnection();
            $connection->executeStatement(
                'UPDATE b_application SET status = :status, updated_at = :updated_at WHERE id = :id',
                [
                    'status' => 'hired',
                    'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'id' => $finalApplication->getId()->toBinary()
                ],
                [
                    'status' => \PDO::PARAM_STR,
                    'updated_at' => \PDO::PARAM_STR,
                    'id' => \PDO::PARAM_STR
                ]
            );

            $em->clear();
            $finalApplication = $em->getRepository(Application::class)->findOneBy([
                'id' => $applicationId,
                'employer' => $currentEmployer,
                'isArchived' => false
            ]);
            $tempStatus = $finalApplication ? $finalApplication->getStatus() : 'hired';
            // Ensure tempStatus is a string, not an array
            $finalStatus = is_string($tempStatus) ? $tempStatus : (is_array($tempStatus) ? (string)reset($tempStatus) : 'hired');
        } elseif ($transition === 'complete' && $finalStatus !== 'completed') {
            $finalApplication->setStatus('completed');
            $em->persist($finalApplication);
            $em->flush();

            // Force direct database update
            $connection = $em->getConnection();
            $connection->executeStatement(
                'UPDATE b_application SET status = :status, updated_at = :updated_at WHERE id = :id',
                [
                    'status' => 'completed',
                    'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'id' => $finalApplication->getId()->toBinary()
                ],
                [
                    'status' => \PDO::PARAM_STR,
                    'updated_at' => \PDO::PARAM_STR,
                    'id' => \PDO::PARAM_STR
                ]
            );

            $em->clear();
            $finalApplication = $em->getRepository(Application::class)->findOneBy([
                'id' => $applicationId,
                'employer' => $currentEmployer,
                'isArchived' => false
            ]);
            $tempStatus = $finalApplication ? $finalApplication->getStatus() : 'completed';
            // Ensure tempStatus is a string, not an array
            $finalStatus = is_string($tempStatus) ? $tempStatus : (is_array($tempStatus) ? (string)reset($tempStatus) : 'completed');
        }

        // Clear query cache to ensure fresh counts on next page load
        $em->getConfiguration()->getQueryCache()->clear();

        // If it's an AJAX request, return JSON response
        if ($request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            // Get updated status counts for the employer
            $statusCounts = $em->getRepository(Application::class)->getEmployerApplicationStatusCounts($currentEmployer->getId());
            $statusCountsFormatted = [];
            foreach ($statusCounts as $row) {
                // Safely get status - ensure it's a string, not an array
                // First check if row is an array and has the 'status' key
                if (!is_array($row) || !array_key_exists('status', $row)) {
                    continue;
                }

                $statusValue = $row['status'];
                // If status is an array, skip this row
                if (is_array($statusValue)) {
                    continue;
                }

                $status = is_string($statusValue) ? strtolower($statusValue) : '';
                if (!empty($status)) {
                    $count = 0;
                    // Safely get count
                    if (array_key_exists('count', $row) && !is_array($row['count'])) {
                        $count = (int) $row['count'];
                    }
                    $statusCountsFormatted[$status] = $count;
                }
            }

            // Map status to display name (matching frontend exactly)
            // Ensure finalStatus is a string before passing to getStatusDisplayName
            $finalStatusForDisplay = is_string($finalStatus) ? $finalStatus : (is_array($finalStatus) ? (string)reset($finalStatus) : 'unknown');
            $displayStatus = $this->getStatusDisplayName($finalStatusForDisplay);
            $phaseName = $displayStatus;

            return $this->json([
                'success' => true,
                'message' => "Candidate moved to the {$phaseName} phase",
                'newStatus' => $finalStatus,
                'displayStatus' => $displayStatus,
                'statusCounts' => $statusCountsFormatted
            ]);
        }

        // Redirect to the appropriate status section based on the new status
        return $this->redirectToRoute('app_recruiter_job_applications', [
            'id' => $job->getId(),
            'applicationId' => $applicationId,
            'status' => $finalStatus
        ]);
    }

    #[Route('/{id}/applications/{applicationId}/detail', name: 'app_recruiter_job_application_detail_ajax', methods: ['GET'])]
    public function applicationDetailAjax(
        Job $job,
        string $applicationId,
        Request $request,
        EntityManagerInterface $em,
        Registry $workflowRegistry
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $recruiter = $user->getRecruiter();
        $assignmentCheck = $em->getRepository(JobRecruiter::class)->findOneBy(['job' => $job, 'recruiter' => $recruiter]);

        if (!$recruiter || !$assignmentCheck) {
            throw new AccessDeniedException('You do not have access to this job.');
        }

        $currentEmployer = $job->getEmployer();

        if ($job->getEmployer() !== $currentEmployer) {
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

        if (!$application) {
            return $this->json(['error' => 'Application not found'], 404);
        }

        $provider = $application->getProvider();
        $user = $provider->getUser();
        $educations = $em->getRepository(Education::class)->findBy(['user' => $user]);
        $experiences = $em->getRepository(Experience::class)->findBy(['user' => $user]);
        $insurances = $em->getRepository(Insurance::class)->findBy(['user' => $user]);
        $review = $em->getRepository(Review::class)->findOneBy(['application' => $application, 'provider' => $provider]);
        $documentRequests = $em->getRepository(DocumentRequest::class)->findBy(['provider' => $provider, 'application' => $application]);

        if (count([$application]) > 0) {
            $workflow = $workflowRegistry->get($application, 'job_application_workflow');
        }

        $jobApplicationTransitions = [];
        try {
            $jobApplicationTransitions[$application->getId()->toString()] = array_map(fn($t) => $t->getName(), $workflow->getEnabledTransitions($application));
        } catch (\LogicException $e) {
            // Map legacy statuses to new statuses for backward compatibility
            $currentStatus = $application->getStatus();
            // Ensure currentStatus is a string, not an array
            if (!is_string($currentStatus)) {
                $currentStatus = is_array($currentStatus) ? (string)reset($currentStatus) : (string)$currentStatus;
            }
            $legacyStatusMap = [
                'in_review' => 'shortlisted',
                'interview' => 'interviewing',
                'offered' => 'negotiating',
                'hired' => 'accepted'
            ];

            if (is_string($currentStatus) && isset($legacyStatusMap[$currentStatus])) {
                $application->setStatus($legacyStatusMap[$currentStatus]);
            }
            $jobApplicationTransitions[$application->getId()->toString()] = array_map(fn($t) => $t->getName(), $workflow->getEnabledTransitions($application));
        }

        return $this->render('recruiter/job/_application_detail.html.twig', [
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
