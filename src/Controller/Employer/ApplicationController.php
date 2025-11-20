<?php

namespace App\Controller\Employer;

use App\Entity\Application;
use App\Entity\DocumentRequest;
use App\Entity\Interview;
use App\Entity\Job;
use App\Entity\Review;
use App\Entity\ToDo; // Add this import
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

        $statusColors = [
        'applied' => 'primary',
        'in_review' => 'info', 
        'interview' => 'warning',
        'offered' => 'success',
        'accepted' => 'success', // Make sure this matches your actual status
        'rejected' => 'danger',
        'hired' => 'success',
        'completed' => 'secondary'
    ];

        return $this->render('employer/application/index.html.twig', [
            'applications' => $applications,
            'statusCounts' => $statusCounts,
            'totalApplications' => $totalApplications,
            'statusColors' => $statusColors,
        ]);
    }

    #[Route('/{id}/ask-for-document', name: 'app_employer_application_askfordocument', methods: ['POST'])]
    public function askForDocument(
        Application $application, 
        Request $request, 
        EntityManagerInterface $em, 
        EventDispatcherInterface $dispatcher
    ): Response
    {
        $referer = $request->headers->get('referer');
        $currentEmployer = $this->getUser()->getEmployer();

        if($application->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this application.");
            return $this->redirect($referer ?? $this->generateUrl('app_employer_applications'));
        }

        $documentRequest = new DocumentRequest();
        $documentRequest->setName($request->get('document_name'));
        $documentRequest->setProvider($application->getProvider());
        $documentRequest->setApplication($application);

        $em->persist($documentRequest);
        
        // Create ToDo item for the provider
        $todo = new ToDo();
        $todo->setProvider($application->getProvider());
        $todo->setEmployer($currentEmployer);
        $todo->setDocumentRequest($documentRequest);
        $todo->setTitle('Document Request: ' . $request->get('document_name'));
        $todo->setDescription(sprintf(
            '%s has requested the document "%s" for the job "%s". Please upload the requested document.',
            $currentEmployer->getCompanyName(),
            $request->get('document_name'),
            $application->getJob()->getTitle()
        ));
        $todo->setType('document_request');
        
        $em->persist($todo);
        $em->flush();

        $dispatcher->dispatch(new ApplicationEvent($application), ApplicationEvent::APPLICATION_DOCUMENT_REQUESTED);

        $this->addFlash('success', 'Document requested from provider successfully.');
        return $this->redirect($referer ?? $this->generateUrl('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]));
    }

    #[Route('/bulk/ask-for-document', name: 'app_employer_application_bulk_askfordocument', methods: ['POST'])]
    public function bulkAskForDocument(
        Request $request, 
        EntityManagerInterface $em, 
        EventDispatcherInterface $dispatcher,
        ApplicationRepository $applicationRepo
    ): Response
    {
        $referer = $request->headers->get('referer');
        $currentEmployer = $this->getUser()->getEmployer();
        $documentName = $request->get('document_name');
        $applicationIds = explode(',', $request->get('application_ids'));

        if (empty($applicationIds) || empty($documentName)) {
            $this->addFlash('error', 'Please select applications and provide a document name.');
            return $this->redirect($referer ?? $this->generateUrl('app_employer_applications'));
        }

        $applications = $applicationRepo->findBy([
            'id' => $applicationIds,
            'employer' => $currentEmployer
        ]);

        $successCount = 0;
        foreach ($applications as $application) {
            // Skip applications in excluded statuses
            if (in_array($application->getStatus(), ['rejected', 'completed'])) {
                continue;
            }

            $documentRequest = new DocumentRequest();
            $documentRequest->setName($documentName);
            $documentRequest->setProvider($application->getProvider());
            $documentRequest->setApplication($application);

            $em->persist($documentRequest);
            
            // Create ToDo item for the provider
            $todo = new ToDo();
            $todo->setProvider($application->getProvider());
            $todo->setEmployer($currentEmployer);
            $todo->setDocumentRequest($documentRequest);
            $todo->setTitle('Document Request: ' . $documentName);
            $todo->setDescription(sprintf(
                '%s has requested the document "%s" for the job "%s". Please upload the requested document.',
                $currentEmployer->getCompanyName(),
                $documentName,
                $application->getJob()->getTitle()
            ));
            $todo->setType('document_request');
            
            $em->persist($todo);
            $successCount++;
        }

        $em->flush();

        if ($successCount > 0) {
            $this->addFlash('success', sprintf('Document requested from %d providers successfully.', $successCount));
        } else {
            $this->addFlash('warning', 'No document requests were sent. Please check if selected applications are eligible.');
        }

        return $this->redirect($referer ?? $this->generateUrl('app_employer_applications'));
    }

    #[Route('/{id}/shcudule-interview', name: 'app_employer_application_scheduleinterview', methods: ['POST'])]
    public function scheduleInterview(Application $application, Request $request, EntityManagerInterface $em, EventDispatcherInterface $dispatcher): Response
    {
        $referer = $request->headers->get('referer');
        $currentEmployer = $this->getUser()->getEmployer();

        if($application->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this application.");
            return $this->redirect($referer ?? $this->generateUrl('app_employer_applications'));
        }

        $interview = new Interview();

        $interview->setDate(new \DateTime($request->get('meeting_date')));
        $interview->setMeetingUrl($request->get('meeting_url'));
        $interview->setMeetingPlatform($request->get('meeting_platform'));
        $interview->setApplication($application);

        $application->setInterview($interview);

        $em->persist($interview);
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

        if($application->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this application.");
            return $this->redirect($referer ?? $this->generateUrl('app_employer_applications'));
        }

        if($application->getOneFileRequestedAt()){
            $this->addFlash('error', 'One file already requested from provider.');
            return $this->redirect($referer ?? $this->generateUrl('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]));
        }

        if($application->getOneFileProvidedAt()){
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
    ): Response
    {
        $referer = $request->headers->get('referer');
        $currentEmployer = $this->getUser()->getEmployer();

        if($application->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this application.");
            return $this->redirect($referer ?? $this->generateUrl('app_employer_applications'));
        }

        if($application->getContractSentAt()){
            $this->addFlash('error', 'Contract already sent to provider.');
            return $this->redirect($referer ?? $this->generateUrl('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]));
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
    ): Response
    {
        $currentEmployer = $this->getUser()->getEmployer();

        if($application->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this application.");
            return $this->redirectToRoute('app_employer_applications');
        }

        $provider = $application->getProvider();

        $existingReview = $em->getRepository(Review::class)->findOneBy([
            'application' => $application,
            'provider' => $provider,
            'reviewedBy' => 'EMPLOYER'
        ]);

        if($existingReview){
            $this->addFlash('error', 'You already have write review for provider.');
            return $this->redirectToRoute('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]);
        }

        if($request->getMethod() == 'POST') {
            $message = $request->get('message');
            $professionalism = (int)$request->get('professionalism');
            $quality = (int)$request->get('quality');
            $communication = (int)$request->get('communication');
            $emotionalIntelligence = (int)$request->get('emotional_intelligence');

            if (!empty($message) &&
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
            }

            $this->addFlash('success', 'Review added for provider successfully.');
            return $this->redirectToRoute('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]);
        }

        $this->addFlash('error', 'Unable to create review');
        return $this->redirectToRoute('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_employer_application_delete', methods: ['GET'])]
    public function delete(Application $application, EntityManagerInterface $em, EventDispatcherInterface $dispatcher): Response
    {
        if($this->getUser()->getEmployer() != $application->getEmployer()){
            $this->addFlash('error', 'You are not allowed to delete this application.');
            return $this->redirectToRoute('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]);
        }

        $em->remove($application);
        $em->flush();

        $this->addFlash('success', 'Application deleted successfully.');
        return $this->redirectToRoute('app_employer_job_applications', ['id' => $application->getJob()->getId(), 'applicationId' => $application->getId()]);
    }
}