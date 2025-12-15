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

#[Route('/employer/applications')]
class ApplicationController extends AbstractController
{
    #[Route('/', name: 'app_employer_applications')]
    public function index(EntityManagerInterface $em, Request $request): Response
    {
        $employer = $this->getUser()->getEmployer();
        $offset = $request->query->get('page', 1);
        $perPage = $request->get('per_page', 25);
        $filters = $request->query->all();
        $filters['employer'] = $employer->getId();

        $applications = $em->getRepository(Application::class)->getAll($offset, $perPage, $filters);
        $statusCounts = $em->getRepository(Application::class)->getEmployerApplicationStatusCounts($employer->getId());

        $totalApplications = $em->createQuery("SELECT count(a.id) as total_applications FROM App\Entity\Application a JOIN a.job j WHERE j.employer = :employer")
            ->setParameter('employer', $this->getUser()->getEmployer()->getId(), UuidType::NAME)
            ->getSingleScalarResult();

        return $this->render('employer/application/index.html.twig', [
            'applications' => $applications,
            'statusCounts' => $statusCounts,
            'totalApplications' => $totalApplications,
        ]);
    }
    
    #[Route('/{id}/document-requests', name: 'app_employer_application_document_requests', methods: ['GET'])]
    public function applicationDocumentRequests(Application $application, EntityManagerInterface $em, Request $request): JsonResponse
    {
        $currentEmployer = $this->getUser()->getEmployer();
        if ($application->getEmployer() !== $currentEmployer) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        $provider = $application->getProvider();
        $requests = $em->getRepository(DocumentRequest::class)->findBy([
            'application' => $application,
            'provider' => $provider
        ], ['createdAt' => 'DESC']);
        $data = array_map(function (DocumentRequest $dr) {
            $doc = $dr->getDocument();
            $docData = null;
            if ($doc) {
                $path = $doc->getFilePath();
                if (!$path && $doc->getUser() && $doc->getFileName()) {
                    $path = '/uploads/' . $doc->getUser()->getId() . '/' . $doc->getFileName();
                }
                $docData = [
                    'id' => (string)$doc->getId(),
                    'name' => $doc->getDisplayName(),
                    'mimeType' => $doc->getMimeType(),
                    'filePath' => $path,
                    'url' => $path
                ];
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
            $contractUrl = $file ? ('/uploads/contracts/' . $file) : null;
        }
        return $this->json([
            'success' => true,
            'documentRequests' => $data,
            'contractUrl' => $contractUrl
        ]);
    }

    #[Route('/{id}/todo/create', name: 'app_employer_application_createtodo', methods: ['POST'])]
    public function createTodoForApplication(Application $application, Request $request, EntityManagerInterface $em): Response
    {
        $employer = $this->getUser()->getEmployer();
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
            'completed' => 'See your review',
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
        $currentEmployer = $this->getUser()->getEmployer();

        if ($application->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this application.");
            return $this->redirect($referer ?? $this->generateUrl('app_employer_applications'));
        }

        $documentName = $request->get('document_name');

        if (empty($documentName)) {
            $this->addFlash('error', 'Document name is required.');
            return $this->redirect($referer ?? $this->generateUrl('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]));
        }

        $documentRequest = new DocumentRequest();
        $documentRequest->setName($documentName);
        $documentRequest->setProvider($application->getProvider());
        $documentRequest->setApplication($application);

        $em->persist($documentRequest);
        $em->flush();

        // Create ToDo for provider - ENHANCED VERSION
        $todo = new \App\Entity\ToDo();
        $todo->setProvider($application->getProvider());
        $todo->setEmployer($currentEmployer); // Store employer name as string
        $todo->setDocumentRequest($documentRequest);
        $todo->setTitle('📄 Document Required: ' . $documentName);
        $todo->setDescription($documentName); // This will show as the specific document name in notification
        $todo->setType('document_request');
        $todo->setCreatedAt(new \DateTimeImmutable());
        $todo->setIsCompleted(false);

        $em->persist($todo);
        $em->flush();

        $dispatcher->dispatch(new ApplicationEvent($application), ApplicationEvent::APPLICATION_DOCUMENT_REQUESTED);

        $this->addFlash('success', 'Document "' . $documentName . '" requested from provider successfully.');
        return $this->redirect($referer ?? $this->generateUrl('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]));
    }

    #[Route('/{id}/shcudule-interview', name: 'app_employer_application_scheduleinterview', methods: ['POST'])]
    public function scheduleInterview(Application $application, Request $request, EntityManagerInterface $em, EventDispatcherInterface $dispatcher): Response
    {
        $referer = $request->headers->get('referer');
        $currentEmployer = $this->getUser()->getEmployer();

        if ($application->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this application.");
            return $this->redirect($referer ?? $this->generateUrl('app_employer_applications'));
        }

        $dates = $request->request->all('interview_dates');
        $singleDate = $request->request->get('interview_date') ?? $request->request->get('meeting_date');
        $platform = $request->request->get('meeting_platform') ?? $request->request->get('platform') ?? 'Interview';
        $url = $request->request->get('meeting_url') ?? $request->request->get('link');

        $createdAny = false;
        $firstInterview = null;

        if (is_array($dates) && count($dates) > 0) {
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
            $interview->setDate(new \DateTime($singleDate));
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
            $application->setInterview($firstInterview);
        }

        $em->persist($application);
        $em->flush();

        $dispatcher->dispatch(new ApplicationEvent($application), ApplicationEvent::APPLICATION_INTERVIEW_SCHEDULED);

        $this->addFlash('success', 'Interview schedule sent successfully.');
        return $this->redirect($referer ?? $this->generateUrl('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]));
    }

    #[Route('/{id}/ask-for-onefile', name: 'app_employer_application_askforonefile', methods: ['GET'])]
    public function askForOneFile(Application $application, Request $request, EntityManagerInterface $em, EventDispatcherInterface $dispatcher): Response
    {
        $referer = $request->headers->get('referer');
        $currentEmployer = $this->getUser()->getEmployer();

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
        $currentEmployer = $this->getUser()->getEmployer();

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
        $currentEmployer = $this->getUser()->getEmployer();

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
            $message = $request->get('message');
            $professionalism = (int)$request->get('professionalism');
            $quality = (int)$request->get('quality');
            $communication = (int)$request->get('communication');
            $emotionalIntelligence = (int)$request->get('emotional_intelligence');

            if (
                !empty($message) &&
                $professionalism && $quality && $communication && $emotionalIntelligence
            ) {
                $review = new Review();

                $review->setMessage($message);
                $review->setProfessionalism($professionalism);
                $review->setQuality($quality);
                $review->setCommunication($communication);
                $review->setEmotionalIntelligence($emotionalIntelligence);
                $review->setEmployer($application->getEmployer());
                $review->setProvider($application->getProvider());
                $review->setApplication($application);
                $review->setReviewedBy('EMPLOYER');

                $averagePoint = ($professionalism + $quality + $communication + $emotionalIntelligence) / 4;
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
                $provider->setAveragePoint(round((float)$average, 2)); // rounded to 2 decimals

                $em->persist($provider);
                $em->flush();

                $dispatcher->dispatch(new ReviewEvent($review), ReviewEvent::PROVIDER_REVIEWED);

                $this->addFlash('success', 'Review added for provider successfully.');
                return $this->redirectToRoute('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]);
            } else {
                $this->addFlash('error', 'Unable to create review. Please fill in all required fields.');
                return $this->redirectToRoute('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]);
            }
        }

        // GET request - show the review form (no error message needed)
        return $this->redirectToRoute('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_employer_application_delete', methods: ['GET'])]
    public function delete(Application $application, EntityManagerInterface $em, EventDispatcherInterface $dispatcher): Response
    {
        if ($this->getUser()->getEmployer() != $application->getEmployer()) {
            $this->addFlash('error', 'You are not allowed to delete this application.');
            return $this->redirectToRoute('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]);
        }

        $em->remove($application);
        $em->flush();

        $this->addFlash('success', 'Application deleted successfully.');
        return $this->redirectToRoute('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]);
    }
}
