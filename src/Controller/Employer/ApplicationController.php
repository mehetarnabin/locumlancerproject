<?php

namespace App\Controller\Employer;

use App\Entity\Application;
use App\Entity\DocumentRequest;
use App\Entity\Interview;
use App\Entity\Job;
use App\Entity\Review;
use App\Entity\ToDo;
use App\Event\ApplicationEvent;
use App\Event\ReviewEvent;
use App\Repository\ApplicationRepository;
use App\Repository\EmployerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

use Symfony\Component\Uid\Uuid;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use App\Entity\Document;
use App\Entity\Provider;

#[Route('/employer/applications')]
class ApplicationController extends AbstractController
{
    #[Route('/', name: 'app_employer_applications')]
    public function index(EntityManagerInterface $em, Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $employer = $user->getEmployer();
        $offset = $request->query->get('page', 1);
        $perPage = $request->get('per_page', 25);
        $filters = $request->query->all();

        $filters['employer'] = $employer->getId();

        // Map display status to database status for filtering
        if (array_key_exists('status', $filters) && !empty($filters['status'])) {
            $statusFilter = $filters['status'];
            $statusMapping = [
                'applied' => ['applied'],  // Only 'applied' status, not 'shortlisted'
                'shortlisted' => ['shortlisted', 'in_review'],
                'interviewing' => ['interviewing', 'interview'],
                'negotiating' => ['negotiating', 'offered'],
                'accepted' => ['accepted', 'hired'],
                'completed' => ['completed']
            ];

            // If statusFilter is already an array, use it directly (but ensure 'applied' is clean)
            if (is_array($statusFilter)) {
                // If array contains 'applied', ensure it's only 'applied' and nothing else
                if (in_array('applied', $statusFilter, true)) {
                    $filters['status'] = ['applied'];
                } else {
                    $filters['status'] = $statusFilter;
                }
            } elseif (is_string($statusFilter) && isset($statusMapping[$statusFilter])) {
                // Map the string status to array of database statuses
                $filters['status'] = $statusMapping[$statusFilter];
            } else {
                // If it's a direct status update request searching for specific status
                $filters['status'] = is_string($statusFilter) ? [$statusFilter] : [];
            }
        }

        // Handle Job ID filter
        if (array_key_exists('jobId', $filters) && !empty($filters['jobId']) && !is_array($filters['jobId'])) {
            $filters['jobId'] = $filters['jobId'];
        }

        // Location, salary, category, and days filters are already handled by the repository
        // Just ensure they're passed through
        if (array_key_exists('location', $filters) && !empty($filters['location']) && !is_array($filters['location'])) {
            $filters['location'] = $filters['location'];
        }
        if (array_key_exists('salaryMin', $filters) && !empty($filters['salaryMin']) && !is_array($filters['salaryMin'])) {
            $filters['salaryMin'] = (float) $filters['salaryMin'];
        }
        if (array_key_exists('salaryMax', $filters) && !empty($filters['salaryMax']) && !is_array($filters['salaryMax'])) {
            $filters['salaryMax'] = (float) $filters['salaryMax'];
        }
        if (array_key_exists('category', $filters) && !empty($filters['category']) && !is_array($filters['category'])) {
            $filters['category'] = $filters['category'];
        }
        if (array_key_exists('days', $filters) && !empty($filters['days']) && !is_array($filters['days'])) {
            $filters['days'] = (int) $filters['days'];
        }

        $applications = $em->getRepository(Application::class)->getAll($offset, $perPage, $filters);
        $rawStatusCounts = $em->getRepository(Application::class)->getEmployerApplicationStatusCounts($employer->getId());

        // Aggregate counts based on display status mapping
        $statusCounts = [
            'applied' => 0,
            'shortlisted' => 0,
            'interviewing' => 0,
            'negotiating' => 0,
            'accepted' => 0,
            'completed' => 0
        ];

        // Define mapping for aggregation (reverse of the filter mapping)
        $dbToDisplayMapping = [
            'applied' => 'applied',
            'in_review' => 'shortlisted',
            'shortlisted' => 'shortlisted',
            'interview' => 'interviewing',
            'interviewing' => 'interviewing',
            'offered' => 'negotiating',
            'negotiating' => 'negotiating',
            'hired' => 'accepted',
            'accepted' => 'accepted',
            'completed' => 'completed'
        ];

        foreach ($rawStatusCounts as $row) {
            $dbStatus = $row['status'];
            $count = (int)$row['count'];

            if (isset($dbToDisplayMapping[$dbStatus])) {
                $displayStatus = $dbToDisplayMapping[$dbStatus];
                if (isset($statusCounts[$displayStatus])) {
                    $statusCounts[$displayStatus] += $count;
                }
            }
        }

        $totalApplications = $em->createQuery("SELECT count(a.id) as total_applications FROM App\Entity\Application a JOIN a.job j WHERE j.employer = :employer")
            ->setParameter('employer', $employer->getId(), UuidType::NAME)
            ->getSingleScalarResult();

        return $this->render('employer/application/index.html.twig', [
            'applications' => $applications,
            'statusCounts' => $statusCounts,
            'totalApplications' => $totalApplications,
        ]);
    }

    #[Route('/{id}/review-details', name: 'app_employer_application_review_details', methods: ['GET'])]
    public function getReviewDetails(
        Application $application,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $employer = $user->getEmployer();
        $provider = $application->getProvider();

        $existingReview = $em->getRepository(Review::class)->findOneBy([
            'application' => $application,
            'employer' => $employer,
            'provider' => $provider,
            'reviewedBy' => 'EMPLOYER'
        ]);

        if ($existingReview) {
            return $this->json([
                'success' => true,
                'hasReview' => true,
                'review' => [
                    'message' => $existingReview->getMessage(),
                    'professionalism' => $existingReview->getProfessionalism(),
                    'quality' => $existingReview->getQuality(),
                    'communication' => $existingReview->getCommunication(),
                    'emotional_intelligence' => $existingReview->getEmotionalIntelligence(),
                    'date' => $existingReview->getCreatedAt() ? $existingReview->getCreatedAt()->format('M d, Y') : ''
                ]
            ]);
        }

        return $this->json([
            'success' => true,
            'hasReview' => false
        ]);
    }

    #[Route('/{id}/document-requests', name: 'app_employer_application_document_requests', methods: ['GET'])]
    public function applicationDocumentRequests(Application $application, EntityManagerInterface $em, Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $currentEmployer = $user->getEmployer();
        if ($application->getEmployer() !== $currentEmployer) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        $provider = $application->getProvider();
        $requests = $em->getRepository(DocumentRequest::class)->findBy([
            'application' => $application,
            'provider' => $provider
        ], ['createdAt' => 'DESC']);
        $projectDir = $this->getParameter('kernel.project_dir');
        $data = array_map(function (DocumentRequest $dr) use ($projectDir) {
            $doc = $dr->getDocument();
            $docData = null;
            if ($doc) {
                $path = $doc->getFilePath();
                if (!$path && $doc->getUser() && $doc->getFileName()) {
                    $path = '/uploads/' . $doc->getUser()->getId() . '/' . $doc->getFileName();
                }

                if ($path && !file_exists($projectDir . '/public' . $path)) {
                    $path = null;
                }

                if ($path) {
                    $docData = [
                        'id' => (string)$doc->getId(),
                        'name' => $doc->getDisplayName(),
                        'mimeType' => $doc->getMimeType(),
                        'filePath' => $path,
                        'url' => $path
                    ];
                }
            }
            return [
                'id' => (string)$dr->getId(),
                'name' => $dr->getName(),
                'createdAt' => $dr->getCreatedAt() ? $dr->getCreatedAt()->format('c') : null,
                'providedAt' => $dr->getProvidedAt() ? $dr->getProvidedAt()->format('c') : null,
                'document' => $docData
            ];
        }, $requests);
        $contractUrl = null;
        if ($application->getContractSignedFileName() || $application->getContractFileName()) {
            $file = $application->getContractSignedFileName() ?: $application->getContractFileName();
            if ($file) {
                $filePath = $projectDir . '/public/uploads/contracts/' . $file;
                if (file_exists($filePath)) {
                    $contractUrl = '/uploads/contracts/' . $file;
                }
            }
        }

        // Get offer letters and contract letters sent to provider for this application
        $offerLetters = $em->getRepository(Document::class)->createQueryBuilder('d')
            ->where('d.application = :application')
            ->andWhere('d.category IN (:categories)')
            ->setParameter('application', $application)
            ->setParameter('categories', ['Offer Letter', 'Contract Letter'])
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $offerLettersData = array_map(function (Document $doc) use ($projectDir) {
            $path = $doc->getFilePath() ?? '/uploads/' . $doc->getUser()->getId() . '/' . $doc->getFileName();
            if ($path && !file_exists($projectDir . '/public' . $path)) {
                $path = null;
            }
            return [
                'id' => (string)$doc->getId(),
                'name' => $doc->getDisplayName(),
                'category' => $doc->getCategory(),
                'fileName' => $doc->getFileName(),
                'filePath' => $path,
                'createdAt' => $doc->getCreatedAt()?->format('c'),
            ];
        }, $offerLetters);

        return $this->json([
            'success' => true,
            'documentRequests' => $data,
            'contractUrl' => $contractUrl,
            'offerLetters' => $offerLettersData
        ]);
    }

    #[Route('/{id}/interview-details', name: 'app_employer_application_interview_details', methods: ['GET'])]
    public function getInterviewDetails(Application $application, EntityManagerInterface $em): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $currentEmployer = $user->getEmployer();
        if ($application->getEmployer() !== $currentEmployer) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $interviews = $em->getRepository(Interview::class)->findBy(
            ['application' => $application],
            ['date' => 'ASC']
        );

        $data = array_map(function (Interview $iv) {
            return [
                'id' => (string) $iv->getId(),
                'date' => $iv->getDate() ? $iv->getDate()->format('c') : null,
                'end_date' => $iv->getEndDate() ? $iv->getEndDate()->format('c') : null,
                'platform' => $iv->getMeetingPlatform(),
                'url' => $iv->getMeetingUrl(),
            ];
        }, $interviews);



        // Fetch Interview Reschedule Requests
        $rescheduleRequests = $em->getRepository(Document::class)->findBy([
            'application' => $application,
            'category' => 'Interview Reschedule Request'
        ], ['createdAt' => 'DESC']);

        $requestsData = array_map(function (Document $doc) {
            return [
                'id' => (string) $doc->getId(),
                'message' => $doc->getDescription(),
                'date' => $doc->getCreatedAt()->format('M d, Y H:i'),
            ];
        }, $rescheduleRequests);

        return $this->json([
            'success' => true,
            'interviews' => $data,
            'rescheduleRequests' => $requestsData
        ]);
    }

    #[Route('/{id}/todo/create', name: 'app_employer_application_createtodo', methods: ['POST'])]
    public function createTodoForApplication(Application $application, Request $request, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $employer = $user->getEmployer();
        if ($application->getEmployer() !== $employer) {
            return $this->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $actionKey = $payload['actionKey'] ?? null;
        $status = $application->getStatus();

        $map = [
            'applied' => 'Revisit Alert',
            'shortlisted' => 'Open Response',
            'interview' => 'Confirm interview',
            'interviewing' => 'Confirm interview',
            'negotiating' => 'Sign offer',
            'accepted' => 'Open Credentialing Docs',
            'completed' => 'Write your review',
        ];

        $title = null;
        if ($actionKey && isset($map[$actionKey])) {
            $title = $map[$actionKey];
        } elseif ($status && isset($map[$status])) {
            $title = $map[$status];
        } else {
            $title = 'Employer Task';
        }

        $todo = new ToDo();
        $todo->setProvider($application->getProvider());
        $todo->setEmployer($employer);
        $todo->setJob($application->getJob());
        $todo->setTitle($title);
        $todo->setType('employer_task');
        $todo->setIsCompleted(false);

        $em->persist($todo);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'To-Do created for provider',
            'todo' => [
                'id' => (string)$todo->getId(),
                'title' => $todo->getTitle(),
                'type' => $todo->getType(),
                'createdAt' => $todo->getCreatedAt()->format('c'),
            ]
        ]);
    }

    #[Route('/{id}/ask-for-document', name: 'app_employer_application_askfordocument', methods: ['POST'])]
    public function askForDocument(Application $application, Request $request, EntityManagerInterface $em, EventDispatcherInterface $dispatcher): Response
    {
        $referer = $request->headers->get('referer');
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $currentEmployer = $user->getEmployer();

        if ($application->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this application.");
            return $this->redirect($referer ?? $this->generateUrl('app_employer_applications'));
        }

        $documentNames = $request->get('document_names');
        $singleDocumentName = $request->get('document_name');

        if (empty($documentNames) && empty($singleDocumentName)) {
            $this->addFlash('error', 'Please select at least one document.');
            return $this->redirect($referer ?? $this->generateUrl('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]));
        }

        $namesToRequest = [];
        if (!empty($documentNames) && is_array($documentNames)) {
            $namesToRequest = $documentNames;
        }
        if (!empty($singleDocumentName)) {
            $namesToRequest[] = $singleDocumentName;
        }

        // Remove duplicates and filter empty
        $namesToRequest = array_unique(array_filter($namesToRequest));

        foreach ($namesToRequest as $name) {
            $documentRequest = new DocumentRequest();
            $documentRequest->setName($name);
            $documentRequest->setProvider($application->getProvider());
            $documentRequest->setApplication($application);

            $em->persist($documentRequest);

            // Create ToDo for provider - ENHANCED VERSION
            $todo = new \App\Entity\ToDo();
            $todo->setProvider($application->getProvider());
            $todo->setEmployer($currentEmployer);
            $todo->setDocumentRequest($documentRequest);
            $todo->setTitle('📄 Document Required: ' . $name);
            $todo->setDescription($name);
            $todo->setType('document_request');
            $todo->setCreatedAt(new \DateTimeImmutable());
            $todo->setIsCompleted(false);

            $em->persist($todo);
        }

        $em->flush();

        $dispatcher->dispatch(new ApplicationEvent($application), ApplicationEvent::APPLICATION_DOCUMENT_REQUESTED);

        $flashMessage = count($namesToRequest) > 1
            ? 'Documents requested from provider successfully.'
            : 'Document "' . $namesToRequest[0] . '" requested from provider successfully.';

        $this->addFlash('success', $flashMessage);
        return $this->redirect($referer ?? $this->generateUrl('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]));
    }

    #[Route('/{id}/shcudule-interview', name: 'app_employer_application_scheduleinterview', methods: ['POST'])]

    public function scheduleInterview(Application $application, Request $request, EntityManagerInterface $em, EventDispatcherInterface $dispatcher, MailerInterface $mailer): Response
    {
        $referer = $request->headers->get('referer');
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $currentEmployer = $user->getEmployer();

        if ($application->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this application.");
            return $this->redirect($referer ?? $this->generateUrl('app_employer_applications'));
        }

        $startDates = $request->request->all('interview_start');
        $endDates = $request->request->all('interview_end');
        $dates = $request->request->all('interview_dates');
        $singleDate = $request->request->get('interview_date') ?? $request->request->get('meeting_date');
        $platform = $request->request->get('meeting_platform') ?? $request->request->get('platform') ?? 'Interview';
        $url = $request->request->get('meeting_url') ?? $request->request->get('link');

        $createdAny = false;
        $firstInterview = null;

        if (is_array($startDates) && count($startDates) > 0) {
            foreach ($startDates as $index => $startStr) {
                if (!$startStr) {
                    continue;
                }
                $endStr = $endDates[$index] ?? null;

                $interview = new Interview();
                $interview->setDate(new \DateTime($startStr));
                if ($endStr) {
                    $interview->setEndDate(new \DateTime($endStr));
                }
                $interview->setMeetingUrl($url);
                $interview->setMeetingPlatform($platform);
                $interview->setApplication($application);
                $em->persist($interview);
                if (!$firstInterview) {
                    $firstInterview = $interview;
                }
                $createdAny = true;
            }
        } elseif (is_array($dates) && count($dates) > 0) {
            foreach ($dates as $dateStr) {
                if (!$dateStr) {
                    continue;
                }
                $interview = new Interview();
                $interview->setDate(new \DateTime($dateStr));
                $interview->setMeetingUrl($url);
                $interview->setMeetingPlatform($platform);
                $interview->setApplication($application);
                $em->persist($interview);
                if (!$firstInterview) {
                    $firstInterview = $interview;
                }
                $createdAny = true;
            }
        } elseif ($singleDate) {
            $interview = new Interview();
            $duration = (int) ($request->request->get('meeting_duration') ?? 60);
            $date = new \DateTime($singleDate);
            $interview->setDate($date);

            $endDate = clone $date;
            $endDate->modify(sprintf('+%d minutes', $duration));
            $interview->setEndDate($endDate);
            $interview->setMeetingUrl($url);
            $interview->setMeetingPlatform($platform);
            $interview->setApplication($application);
            $em->persist($interview);
            $firstInterview = $interview;
            $createdAny = true;
        }

        if (!$createdAny) {
            $this->addFlash('error', 'Please provide at least one interview date.');
            return $this->redirect($referer ?? $this->generateUrl('app_employer_applications'));
        }

        if ($firstInterview) {
            // Do NOT automatically set the first interview as confirmed
            // $application->setInterview($firstInterview);

            if ($application->getStatus() !== Application::STATUS_INTERVIEWING) {
                // Automatic switch to Interviewing when proposal sent
                $application->setStatus(Application::STATUS_INTERVIEWING);
            }
        }

        $em->persist($application);
        $em->flush();

        $dispatcher->dispatch(new ApplicationEvent($application), ApplicationEvent::APPLICATION_INTERVIEW_SCHEDULED);

        // Send Email Notification to Provider and Employer
        if ($firstInterview) {
            try {
                $jobTitle = $application->getJob()->getTitle();
                $dateStr = $firstInterview->getDate()->format('M d, Y');
                $timeStr = $firstInterview->getDate()->format('h:i A');
                $platform = $firstInterview->getMeetingPlatform();
                $meetingUrl = $firstInterview->getMeetingUrl();

                $email = (new TemplatedEmail())
                    ->from(new Address('support@locumlancer.com', 'LocumLancer Team')) // Replace with param if available
                    ->subject('Interview Scheduled: ' . $jobTitle)
                    ->htmlTemplate('emails/interview_scheduled.html.twig')
                    ->context([
                        'job_title' => $jobTitle,
                        'date' => $dateStr,
                        'time' => $timeStr,
                        'platform' => $platform,
                        'url' => $meetingUrl
                    ]);

                // Send to Provider
                if ($application->getProvider() && $application->getProvider()->getUser() && $application->getProvider()->getUser()->getEmail()) {
                    $email->to($application->getProvider()->getUser()->getEmail());
                    $mailer->send($email);
                }

                // Send to Employer
                if ($application->getEmployer() && $application->getEmployer()->getEmail()) {
                    $email->to($application->getEmployer()->getEmail());
                    $mailer->send($email);
                }
            } catch (\Exception $e) {
                // Log error but don't fail request
                // error_log($e->getMessage());
            }
        }

        if ($request->isXmlHttpRequest()) {
            $interviews = $em->getRepository(Interview::class)->findBy(['application' => $application], ['date' => 'ASC']);
            $payload = array_map(function (Interview $iv) {
                return [
                    'id' => (string) $iv->getId(),
                    'date' => $iv->getDate() ? $iv->getDate()->format('c') : null,
                    'end_date' => $iv->getEndDate() ? $iv->getEndDate()->format('c') : null,
                    'platform' => $iv->getMeetingPlatform(),
                    'url' => $iv->getMeetingUrl(),
                ];
            }, $interviews);
            return new JsonResponse([
                'success' => true,
                'message' => 'Interview schedule sent successfully.',
                'interviews' => $payload
            ]);
        }

        $this->addFlash('success', 'Interview schedule sent successfully.');
        return $this->redirect($referer ?? $this->generateUrl('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]));
    }

    #[Route('/{id}/update-interview/{interviewId}', name: 'app_employer_application_updateinterview', methods: ['POST'])]
    public function updateInterview(
        Application $application,
        string $interviewId,
        Request $request,
        EntityManagerInterface $em,
        EventDispatcherInterface $dispatcher
    ): Response {
        $referer = $request->headers->get('referer');
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $currentEmployer = $user->getEmployer();

        if ($application->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this application.");
            return $this->redirect($referer ?? $this->generateUrl('app_employer_applications'));
        }

        $interview = $em->getRepository(Interview::class)->find($interviewId);

        if (!$interview || $interview->getApplication()->getId() !== $application->getId()) {
            $this->addFlash('error', 'Interview not found.');
            return $this->redirect($referer ?? $this->generateUrl('app_employer_applications'));
        }

        $singleDate = $request->request->get('interview_date') ?? $request->request->get('meeting_date');
        $platform = $request->request->get('meeting_platform') ?? $request->request->get('platform') ?? 'Interview';
        $url = $request->request->get('meeting_url') ?? $request->request->get('link');

        if ($singleDate) {
            $interview->setDate(new \DateTime($singleDate));
            $interview->setMeetingUrl($url);
            $interview->setMeetingPlatform($platform);

            $em->persist($interview);
            $em->flush();

            if ($request->isXmlHttpRequest()) {
                $interviews = $em->getRepository(Interview::class)->findBy(['application' => $application], ['date' => 'ASC']);
                $payload = array_map(function (Interview $iv) {
                    return [
                        'id' => (string) $iv->getId(),
                        'date' => $iv->getDate() ? $iv->getDate()->format('c') : null,
                        'platform' => $iv->getMeetingPlatform(),
                        'url' => $iv->getMeetingUrl(),
                    ];
                }, $interviews);
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Interview updated successfully.',
                    'interviews' => $payload
                ]);
            }

            $this->addFlash('success', 'Interview updated successfully.');
        } else {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Interview date is required.'
                ]);
            }
            $this->addFlash('error', 'Interview date is required.');
        }

        return $this->redirect($referer ?? $this->generateUrl('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]));
    }

    #[Route('/{id}/ask-for-onefile', name: 'app_employer_application_askforonefile', methods: ['GET'])]
    public function askForOneFile(Application $application, Request $request, EntityManagerInterface $em, EventDispatcherInterface $dispatcher): Response
    {
        $referer = $request->headers->get('referer');
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $currentEmployer = $user->getEmployer();

        if ($application->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this application.");
            return $this->redirect($referer ?? $this->generateUrl('app_employer_applications'));
        }

        if ($application->getOneFileRequestedAt()) {
            $this->addFlash('error', 'One file already requested from provider.');
            return $this->redirect($referer ?? $this->generateUrl('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]));
        }

        if ($application->getOneFileProvidedAt()) {
            $this->addFlash('error', 'One file already provided by provider.');
            return $this->redirect($referer ?? $this->generateUrl('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]));
        }

        $application->setOneFileRequestedAt(new \DateTime());

        $em->persist($application);
        $em->flush();

        $dispatcher->dispatch(new ApplicationEvent($application), ApplicationEvent::APPLICATION_ONE_FILE_REQUESTED);

        $this->addFlash('success', 'One file requested from provider successfully.');
        return $this->redirect($referer ?? $this->generateUrl('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]));
    }

    #[Route('/{id}/send-offer', name: 'app_employer_application_sendoffer', methods: ['POST'])]
    public function sendOffer(
        Application $application,
        Request $request,
        EntityManagerInterface $em,
        EventDispatcherInterface $dispatcher,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): Response {
        try {
            $referer = $request->headers->get('referer');
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $currentEmployer = $user->getEmployer();

            $isAjax = $request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

            if ($application->getEmployer() !== $currentEmployer) {
                if ($isAjax) {
                    return $this->json(['success' => false, 'message' => "You don't have access to this application."], 403);
                }
                $this->addFlash('error', "You don't have access to this application.");
                return $this->redirect($referer ?? $this->generateUrl('app_employer_applications'));
            }

            $documentType = $request->request->get('document_type');

            // Get file from request - try different possible field names
            $documentFile = $request->files->get('document_file');
            if (!$documentFile) {
                $documentFile = $request->files->get('file');
            }
            if (!$documentFile) {
                // Check all files
                $allFiles = $request->files->all();
                if (!empty($allFiles)) {
                    $documentFile = reset($allFiles);
                }
            }

            // Validate file upload
            if (!$documentFile) {
                if ($isAjax) {
                    return $this->json(['success' => false, 'message' => 'No file uploaded. Please select a file.'], 400);
                }
                $this->addFlash('error', 'No file uploaded.');
                return $this->redirect($referer ?? $this->generateUrl('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]));
            }

            // Check if it's a valid UploadedFile instance
            if (!($documentFile instanceof \Symfony\Component\HttpFoundation\File\UploadedFile)) {
                if ($isAjax) {
                    return $this->json(['success' => false, 'message' => 'Invalid file upload. Expected UploadedFile instance.'], 400);
                }
                $this->addFlash('error', 'Invalid file upload.');
                return $this->redirect($referer ?? $this->generateUrl('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]));
            }

            // Check if file was uploaded successfully (UPLOAD_ERR_OK = 0)
            $errorCode = $documentFile->getError();
            if ($errorCode !== UPLOAD_ERR_OK) {
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive.',
                    UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive.',
                    UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                    UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
                ];
                $errorMessage = $errorMessages[$errorCode] ?? 'Unknown upload error (code: ' . $errorCode . ')';
                if ($isAjax) {
                    return $this->json(['success' => false, 'message' => 'File upload error: ' . $errorMessage], 400);
                }
                $this->addFlash('error', 'File upload error: ' . $errorMessage);
                return $this->redirect($referer ?? $this->generateUrl('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]));
            }

            $provider = $application->getProvider();
            if (!$provider || !$provider->getUser()) {
                if ($isAjax) {
                    return $this->json(['success' => false, 'message' => 'Provider not found.'], 404);
                }
                $this->addFlash('error', 'Provider not found.');
                return $this->redirect($referer ?? $this->generateUrl('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]));
            }

            $providerUser = $provider->getUser();
            $userUploadDir = $uploadDirectory . '/' . $providerUser->getId();
            if (!file_exists($userUploadDir)) {
                if (!mkdir($userUploadDir, 0777, true) && !is_dir($userUploadDir)) {
                    if ($isAjax) {
                        return $this->json(['success' => false, 'message' => 'Failed to create upload directory.'], 500);
                    }
                    $this->addFlash('error', 'Failed to create upload directory.');
                    return $this->redirect($referer ?? $this->generateUrl('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]));
                }
            }

            // Get file extension safely
            $extension = $documentFile->guessExtension();
            if (!$extension) {
                $extension = $documentFile->getClientOriginalExtension() ?: 'bin';
            }

            $originalFilename = pathinfo($documentFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

            // Try to move the file
            try {
                // Get file info before moving (might fail if file is missing)
                $tempPath = $documentFile->getPathname();
                $mimeType = $documentFile->getMimeType(); // This might fail if file is missing

                $movedFile = $documentFile->move($userUploadDir, $newFilename);
            } catch (\Exception $e) {
                // Fallback: This is common in Windows/XAMPP environments where move() fails due to temp path issues
                $tempPath = $tempPath ?? $documentFile->getPathname();
                $finalPath = $userUploadDir . '/' . $newFilename;

                // Try manual copy
                if (@copy($tempPath, $finalPath)) {
                    // Success via copy!
                    // Optionally try to delete temp file, ignore if fails
                    @unlink($tempPath);
                    $mimeType = $mimeType ?? mime_content_type($finalPath); // Update mimetype from new file if needed
                } else {
                    // Determine error message
                    if (strpos($e->getMessage(), 'does not exist') !== false || strpos($e->getMessage(), 'not readable') !== false) {
                        $errorMsg = 'Upload failed: Temp file missing. Please try again.';
                    } else {
                        $errorMsg = 'File upload failed: ' . $e->getMessage();
                    }

                    if ($isAjax) {
                        return $this->json(['success' => false, 'message' => $errorMsg], 500);
                    }
                    $this->addFlash('error', $errorMsg);
                    return $this->redirect($referer ?? $this->generateUrl('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]));
                }
            }


            // Create Document entity for offer/contract letter
            $document = new Document();
            $document->setUser($providerUser);
            $document->setApplication($application);
            $document->setFileName($newFilename);
            $document->setFilePath('/uploads/' . $providerUser->getId() . '/' . $newFilename);
            $document->setMimeType($mimeType ?? 'application/octet-stream');

            if ($documentType === 'offer') {
                $document->setCategory('Offer Letter');
                $document->setName('Offer Letter');
            } elseif ($documentType === 'contract') {
                $document->setCategory('Contract Letter');
                $document->setName('Contract Letter');
                $application->setContractFileName($newFilename);
                $application->setContractSentAt(new \DateTime());
            } elseif ($documentType === 'both') {
                // Create two documents - one for offer, one for contract
                $offerDoc = new Document();
                $offerDoc->setUser($providerUser);
                $offerDoc->setApplication($application);
                $offerDoc->setFileName($newFilename);
                $offerDoc->setFilePath('/uploads/' . $providerUser->getId() . '/' . $newFilename);
                $offerDoc->setMimeType($documentFile->getMimeType());
                $offerDoc->setCategory('Offer Letter');
                $offerDoc->setName('Offer Letter');
                $em->persist($offerDoc);

                $contractDoc = new Document();
                $contractDoc->setUser($providerUser);
                $contractDoc->setApplication($application);
                $contractDoc->setFileName($newFilename);
                $contractDoc->setFilePath('/uploads/' . $providerUser->getId() . '/' . $newFilename);
                $contractDoc->setMimeType($documentFile->getMimeType());
                $contractDoc->setCategory('Contract Letter');
                $contractDoc->setName('Contract Letter');
                $em->persist($contractDoc);

                $application->setContractFileName($newFilename);
                $application->setContractSentAt(new \DateTime());
            } else {
                $document->setCategory('Offer Letter');
                $document->setName('Offer Letter');
            }

            if ($documentType !== 'both') {
                $em->persist($document);
            }

            $em->persist($application);
            $em->flush();

            $dispatcher->dispatch(new ApplicationEvent($application), ApplicationEvent::APPLICATION_CONTRACT_SENT);

            // Automatic switch to Negotiating when offer letter sent
            if ($documentType === 'offer' || $documentType === 'both') {
                $application->setStatus(Application::STATUS_NEGOTIATING);
                $em->persist($application);
                $em->flush();
            }

            if ($isAjax) {
                return $this->json([
                    'success' => true,
                    'message' => ucfirst($documentType ?? 'offer') . ' letter sent to provider successfully.'
                ]);
            }

            $this->addFlash('success', ucfirst($documentType ?? 'offer') . ' letter sent to provider successfully.');
            return $this->redirect($referer ?? $this->generateUrl('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]));
        } catch (\Exception $e) {
            // Log the error for debugging
            error_log('Error in sendOffer: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());

            $isAjax = $request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

            if ($isAjax) {
                return $this->json([
                    'success' => false,
                    'message' => 'An error occurred while sending the offer letter: ' . $e->getMessage()
                ], 500);
            }

            $this->addFlash('error', 'An error occurred while sending the offer letter.');
            $referer = $request->headers->get('referer');
            return $this->redirect($referer ?? $this->generateUrl('app_employer_applications'));
        }
    }

    #[Route('/{id}/send-contract', name: 'app_employer_application_sendcontract', methods: ['GET', 'POST'])]
    public function sendContract(
        Application $application,
        Request $request,
        EntityManagerInterface $em,
        EventDispatcherInterface $dispatcher,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads/contracts')] string $uploadDirectory
    ): Response {
        $referer = $request->headers->get('referer');
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $currentEmployer = $user->getEmployer();

        if ($application->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this application.");
            return $this->redirect($referer ?? $this->generateUrl('app_employer_applications'));
        }

        if ($application->getContractSentAt()) {
            $this->addFlash('error', 'Contract already sent to provider.');
            return $this->redirect($referer ?? $this->generateUrl('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]));
        }

        if ($request->getMethod() == 'POST') {
            $contractFile = $request->files->get('contractFile');
            if ($contractFile) {
                $originalFilename = pathinfo($contractFile->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the file name as part of the URL
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $contractFile->guessExtension();

                try {
                    $contractFile->move($uploadDirectory, $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'File upload failed.');
                    return $this->redirect($referer ?? $this->generateUrl('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]));
                }

                $application->setContractFileName($newFilename);
                $application->setContractSentAt(new \DateTime());
            }

            $em->persist($application);
            $em->flush();

            $dispatcher->dispatch(new ApplicationEvent($application), ApplicationEvent::APPLICATION_CONTRACT_SENT);

            $this->addFlash('success', 'Contract sent to provider successfully.');
            return $this->redirect($referer ?? $this->generateUrl('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]));
        }

        $this->addFlash('error', 'Unable to send contract');
        return $this->redirect($referer ?? $this->generateUrl('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]));
    }

    #[Route('/{id}/review-provider', name: 'app_employer_application_review_provider', methods: ['GET', 'POST'])]
    public function reviewProvider(
        Application $application,
        Request $request,
        EntityManagerInterface $em,
        EventDispatcherInterface $dispatcher,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $currentEmployer = $user->getEmployer();

        if ($application->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this application.");
            return $this->redirectToRoute('app_employer_applications');
        }

        $provider = $application->getProvider();

        $existingReview = $em->getRepository(Review::class)->findOneBy([
            'application' => $application,
            'provider' => $provider,
            'reviewedBy' => 'EMPLOYER'
        ]);

        if ($existingReview) {
            $this->addFlash('error', 'You already have write review for provider.');
            return $this->redirectToRoute('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]);
        }

        if ($request->getMethod() == 'POST') {
            $message = $request->get('review_text') ?? $request->get('message');
            $rating = (int)$request->get('rating');
            $professionalism = (int)$request->get('professionalism');
            $quality = (int)$request->get('quality');
            $communication = (int)$request->get('communication');
            $emotionalIntelligence = (int)$request->get('emotional_intelligence');

            $hasCategoryScores = $professionalism && $quality && $communication && $emotionalIntelligence;
            $hasRatingOnly = $rating && $rating >= 1 && $rating <= 5;

            if (!empty($message) && ($hasCategoryScores || $hasRatingOnly)) {
                $review = new Review();

                $review->setMessage($message);
                if ($hasCategoryScores) {
                    $review->setProfessionalism($professionalism);
                    $review->setQuality($quality);
                    $review->setCommunication($communication);
                    $review->setEmotionalIntelligence($emotionalIntelligence);
                    $averagePoint = ($professionalism + $quality + $communication + $emotionalIntelligence) / 4;
                } else {
                    // Map the single rating to all category fields
                    $review->setProfessionalism($rating);
                    $review->setQuality($rating);
                    $review->setCommunication($rating);
                    $review->setEmotionalIntelligence($rating);
                    $averagePoint = (float)$rating;
                }

                $review->setEmployer($application->getEmployer());
                $review->setProvider($application->getProvider());
                $review->setApplication($application);
                $review->setReviewedBy('EMPLOYER');
                $review->setPoint($averagePoint);

                $em->persist($review);
                $em->flush();

                // Calculate average of all review points for this provider
                $qb = $em->createQueryBuilder();
                $qb->select('AVG(r.point)')
                    ->from(Review::class, 'r')
                    ->where('r.provider = :provider')
                    ->setParameter('provider', $provider->getId(), UuidType::NAME)
                    ->andWhere('r.reviewedBy = :reviewedBy')
                    ->setParameter('reviewedBy', 'EMPLOYER');

                $average = $qb->getQuery()->getSingleScalarResult();
                $provider->setAveragePoint(round((float)$average, 2));

                $em->persist($provider);
                $em->flush();

                $dispatcher->dispatch(new ReviewEvent($review), ReviewEvent::PROVIDER_REVIEWED);

                $this->addFlash('success', 'Review added for provider successfully.');
                $this->addFlash('success', 'Review added for provider successfully.');

                // Automatic switch to Completed when review is added (if not already)
                if ($application->getStatus() !== Application::STATUS_COMPLETED) {
                    $application->setStatus(Application::STATUS_COMPLETED);
                    $em->persist($application);
                    $em->flush();
                }

                return $this->redirectToRoute('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]);
            } else {
                $this->addFlash('error', 'Unable to create review. Please fill in all required fields.');
                return $this->redirectToRoute('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]);
            }
        }

        // GET request - show the review form (no error message needed)
        return $this->redirectToRoute('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]);
    }

    #[Route('/archive', name: 'app_employer_applications_archive', methods: ['POST'])]
    public function archiveApplications(Request $request, EntityManagerInterface $em): Response
    {
        $applicationIdsJson = $request->request->get('application_ids') ?? $request->getContent();
        $applicationIds = is_string($applicationIdsJson) ? json_decode($applicationIdsJson, true) : (array)$applicationIdsJson;
        if (empty($applicationIds)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'No applications selected for archive.'], 400);
            }
            $this->addFlash('error', 'No applications selected for archive.');
            return $this->redirectToRoute('app_employer_applications');
        }
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $employer = $user->getEmployer();
        $count = 0;
        foreach ($applicationIds as $id) {
            try {
                $uuid = Uuid::fromString($id);
            } catch (\Throwable $e) {
                continue;
            }
            $application = $em->getRepository(Application::class)->find($uuid);
            if (!$application) {
                continue;
            }
            if ($application->getEmployer() !== $employer) {
                continue;
            }
            // Always archive to refresh archivedAt and ensure consistency
            $application->archive();
            $em->persist($application);
            $count++;
        }
        $em->flush();
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => $count > 0,
                'message' => 'Archived ' . $count . ' application(s).',
                'count' => $count
            ]);
        }
        $this->addFlash('success', 'Archived ' . $count . ' application(s).');
        return $this->redirectToRoute('app_employer_applications');
    }

    #[Route('/delete', name: 'app_employer_applications_delete', methods: ['POST'])]
    public function deleteApplications(Request $request, EntityManagerInterface $em): Response
    {
        $applicationIdsJson = $request->request->get('application_ids') ?? $request->getContent();
        $applicationIds = is_string($applicationIdsJson) ? json_decode($applicationIdsJson, true) : (array)$applicationIdsJson;
        if (empty($applicationIds)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'No applications selected for deletion.'], 400);
            }
            $this->addFlash('error', 'No applications selected for deletion.');
            return $this->redirectToRoute('app_employer_applications');
        }
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $employer = $user->getEmployer();
        $count = 0;
        foreach ($applicationIds as $id) {
            try {
                $uuid = Uuid::fromString($id);
            } catch (\Throwable $e) {
                continue;
            }
            $application = $em->getRepository(Application::class)->find($uuid);
            if (!$application) {
                continue;
            }
            if ($application->getEmployer() !== $employer) {
                continue;
            }
            // Remove dependent reviews to satisfy FK constraints
            foreach ($em->getRepository(Review::class)->findBy(['application' => $application]) as $review) {
                $em->remove($review);
            }
            $em->remove($application);
            $count++;
        }
        $em->flush();
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => $count > 0,
                'message' => 'Deleted ' . $count . ' application(s).',
                'count' => $count
            ]);
        }
        $this->addFlash('success', 'Deleted ' . $count . ' application(s).');
        return $this->redirectToRoute('app_employer_applications');
    }

    #[Route('/{id}/delete', name: 'app_employer_application_delete', methods: ['GET'])]
    public function delete(Application $application, EntityManagerInterface $em, EventDispatcherInterface $dispatcher): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if ($user->getEmployer() != $application->getEmployer()) {
            $this->addFlash('error', 'You are not allowed to delete this application.');
            return $this->redirectToRoute('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]);
        }

        // Remove dependent reviews to satisfy FK constraints
        foreach ($em->getRepository(Review::class)->findBy(['application' => $application]) as $review) {
            $em->remove($review);
        }
        $em->remove($application);
        $em->flush();

        $this->addFlash('success', 'Application deleted successfully.');

        return $this->redirectToRoute('app_employer_job_applications', ['id' => $application->getJob()->getId()]);
    }

    #[Route('/update-rank', name: 'app_employer_application_update_rank', methods: ['POST'])]
    public function updateRank(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $applicationId = $payload['applicationId'] ?? null;
        $rankValue = $payload['rank'] ?? null;

        if (!$applicationId) {
            return $this->json(['success' => false, 'message' => 'Application ID is required'], 400);
        }

        try {
            $uuid = Uuid::fromString($applicationId);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'message' => 'Invalid Application ID'], 400);
        }

        $application = $em->getRepository(Application::class)->find($uuid);

        if (!$application) {
            return $this->json(['success' => false, 'message' => 'Application not found'], 404);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if ($application->getEmployer() !== $user->getEmployer()) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Validate rank
        if (!is_numeric($rankValue)) {
            return $this->json(['success' => false, 'message' => 'Invalid rank value'], 400);
        }

        $application->setRank((string)$rankValue);
        $em->persist($application);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Score updated successfully',
            'rank' => $rankValue
        ]);
    }

    #[Route('/send-provider-email', name: 'app_employer_send_provider_email', methods: ['POST'])]
    public function sendProviderEmail(
        Request $request,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?: [];

        $applicationId = $data['application_id'] ?? null;
        $subject = trim($data['subject'] ?? '');
        $message = trim($data['message'] ?? '');

        if (!$applicationId || !$subject || !$message) {
            return $this->json(['success' => false, 'message' => 'Missing required data'], 400);
        }

        try {
            // Fetch Application and Provider Email
            $application = $entityManager->getRepository(Application::class)->find($applicationId);
            if (!$application) {
                return $this->json(['success' => false, 'message' => 'Application not found'], 404);
            }

            $provider = $application->getProvider();
            if (!$provider) {
                return $this->json(['success' => false, 'message' => 'Provider not found'], 404);
            }

            $providerUser = $provider->getUser();
            if (!$providerUser) {
                return $this->json(['success' => false, 'message' => 'Provider user not found'], 404);
            }

            // Provider email
            $providerEmail = trim($providerUser->getEmail() ?? '');
            if (!filter_var($providerEmail, FILTER_VALIDATE_EMAIL)) {
                return $this->json(['success' => false, 'message' => 'Invalid provider email: ' . $providerEmail], 400);
            }

            // Fetch Employer (logged-in user) Email
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $employer = $user?->getEmployer();

            $employerName = $employer?->getName() ?: ($user?->getName() ?: 'Employer');
            $employerEmail = trim($employer?->getContactEmail() ?: $employer?->getEmail() ?: $user?->getEmail() ?? '');

            // Employer email fallback
            if (!filter_var($employerEmail, FILTER_VALIDATE_EMAIL)) {
                $employerEmail = 'notifications@locumlancer.com';
                $employerName = 'LocumLancer Employer';
            }

            // Build Email
            $email = (new Email())
                ->from($employerEmail)
                ->to($providerEmail)
                ->subject($subject)
                ->html(
                    $this->renderView('emails/message_notification.html.twig', [
                        'subject'        => $subject,
                        'message_text'   => $message,
                        'sender_name'    => $employerName,
                        'sender_email'   => $employerEmail,
                        'has_attachment' => false,
                        'attachment_name' => null,
                    ])
                );

            // Send Email
            $mailer->send($email);

            return $this->json(['success' => true, 'message' => 'Email sent successfully']);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error sending email: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/application/{id}/send-message', name: 'app_employer_application_send_message', methods: ['POST'])]
    public function sendMessageFromTodo(
        Application $application,
        Request $request,
        EntityManagerInterface $em,
        EventDispatcherInterface $dispatcher,
        MailerInterface $mailer
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $employer = $user?->getEmployer();

        // Security check
        if ($application->getEmployer() !== $employer) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $subject = trim($data['subject'] ?? '');
        $messageText = trim($data['message'] ?? '');

        if (empty($subject) || empty($messageText)) {
            return $this->json(['success' => false, 'message' => 'Subject and message are required'], 400);
        }

        try {
            $provider = $application->getProvider();
            if (!$provider) {
                return $this->json(['success' => false, 'message' => 'Provider not found'], 404);
            }

            $providerUser = $provider->getUser();
            if (!$providerUser) {
                return $this->json(['success' => false, 'message' => 'Provider user not found'], 404);
            }

            // Create message
            $message = new \App\Entity\Message();
            $message->setSender($user);
            $message->setReceiver($providerUser);
            $message->setEmployer($employer);
            $message->setJob($application->getJob());
            $message->setApplication($application);
            $message->setSubject($subject);
            $message->setText($messageText);
            $message->setIsDraft(false);
            $message->setSentAt(new \DateTime());
            $message->setSeen(false);

            $em->persist($message);
            $em->flush();

            // Send email notification (non-blocking - don't fail if email fails)
            try {
                $dispatcher->dispatch(new \App\Event\MessageEvent($message), \App\Event\MessageEvent::MESSAGE_CREATED);

                // Send email
                $providerEmail = $providerUser->getEmail();
                if ($providerEmail) {
                    $employerEmail = $employer?->getContactEmail() ?: $employer?->getEmail() ?: $user?->getEmail() ?? 'notifications@locumlancer.com';
                    $email = (new \Symfony\Component\Mime\Email())
                        ->from($employerEmail)
                        ->to($providerEmail)
                        ->subject($subject . ' - LocumLancer')
                        ->html(
                            $this->renderView('emails/message_notification.html.twig', [
                                'subject' => $subject,
                                'message_text' => $messageText,
                                'sender_name' => $employer?->getName() ?: $user->getName(),
                                'sender_email' => $employerEmail,
                                'has_attachment' => false,
                                'attachment_name' => null,
                            ])
                        );
                    $mailer->send($email);
                }
            } catch (\Exception $emailException) {
                // Log email error but don't fail the request since message is already saved
                // You can log this to a logger if needed
                error_log('Email sending failed: ' . $emailException->getMessage());
            }

            return $this->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'message_id' => $message->getId()->toRfc4122()
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error sending message: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/application/{id}/messages', name: 'app_employer_application_messages', methods: ['GET'])]
    public function getApplicationMessages(
        Application $application,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $employer = $user?->getEmployer();

        // Security check
        if ($application->getEmployer() !== $employer) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Get provider info
        $provider = $application->getProvider();
        $providerUser = $provider ? $provider->getUser() : null;
        $providerEmail = $providerUser ? $providerUser->getEmail() : null;
        $providerName = $providerUser ? $providerUser->getName() : null;

        // Get all messages for this application
        $messages = $em->getRepository(\App\Entity\Message::class)->createQueryBuilder('m')
            ->where('m.application = :application')
            ->andWhere('m.deleted = false')
            ->andWhere('m.isDraft = false')
            ->setParameter('application', $application)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        $messagesData = [];
        foreach ($messages as $msg) {
            $messagesData[] = [
                'id' => $msg->getId()->toRfc4122(),
                'sender_id' => $msg->getSender()->getId()->toRfc4122(),
                'sender_name' => $msg->getSender()->getName(),
                'sender_email' => $msg->getSender()->getEmail(),
                'receiver_id' => $msg->getReceiver() ? $msg->getReceiver()->getId()->toRfc4122() : null,
                'receiver_name' => $msg->getReceiver() ? $msg->getReceiver()->getName() : null,
                'subject' => $msg->getSubject(),
                'text' => $msg->getText(),
                'created_at' => $msg->getCreatedAt()->format('Y-m-d H:i:s'),
                'is_seen' => $msg->isSeen(),
            ];
        }

        return $this->json([
            'success' => true,
            'messages' => $messagesData,
            'provider_email' => $providerEmail,
            'provider_name' => $providerName
        ]);
    }

    #[Route('/{id}/update-status', name: 'app_employer_application_update_status', methods: ['POST'])]
    public function updateStatus(
        Application $application,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $employer = $user->getEmployer();

        if ($application->getEmployer() !== $employer) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $newStatus = $request->request->get('status');

        $validStatuses = [
            Application::STATUS_APPLIED,
            Application::STATUS_SHORTLISTED,
            Application::STATUS_INTERVIEWING,
            Application::STATUS_NEGOTIATING,
            Application::STATUS_ACCEPTED,
            Application::STATUS_COMPLETED,
            Application::STATUS_REJECTED
        ];

        if (!in_array($newStatus, $validStatuses)) {
            return $this->json(['success' => false, 'message' => 'Invalid status'], 400);
        }

        $application->setStatus($newStatus);

        // Handle specific actions based on status if needed
        if ($newStatus === Application::STATUS_COMPLETED) {
            $application->setHiredAt(new \DateTime());
        }

        $em->persist($application);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Status updated successfully',
            'status' => $newStatus
        ]);
    }
}
