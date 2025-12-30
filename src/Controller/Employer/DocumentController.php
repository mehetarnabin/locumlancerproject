<?php

namespace App\Controller\Employer;

use App\Entity\Application;
use App\Entity\Document;
use App\Entity\DocumentRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/employer')]
class DocumentController extends AbstractController
{
    #[Route('/documents', name: 'app_employer_documents')]
    public function index(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if (!$user instanceof \App\Entity\User) {
            throw $this->createAccessDeniedException();
        }

        // 1. Get documents provided by candidates via Document Requests
        // Find requests created by this employer (via application) where a document has been provided
        $employer = $user->getEmployer();

        if (!$employer) {
            return $this->render('employer/document/index.html.twig', [
                'pendingDocumentRequests' => [],
                'credentialingLinks' => [],
                'pendingContracts' => [],
                'receivedDocuments' => [],
            ]);
        }

        // 1. Pending Document Requests (Sent but not yet provided)
        $pendingDocumentRequests = $em->getRepository(DocumentRequest::class)->createQueryBuilder('dr')
            ->join('dr.application', 'a')
            ->where('a.employer = :employer')
            ->andWhere('dr.providedAt IS NULL')
            ->setParameter('employer', $employer->getId(), \Symfony\Bridge\Doctrine\Types\UuidType::NAME)
            ->orderBy('dr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        // 2. Credentialing Links (Created by this employer via jobs)
        // CredentialingLink -> Job -> Employer
        $credentialingLinks = $em->getRepository(\App\Entity\CredentialingLink::class)->createQueryBuilder('cl')
            ->join('cl.job', 'j')
            ->where('j.employer = :employer')
            ->setParameter('employer', $employer)
            ->orderBy('cl.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        // 3. Pending Contracts (Sent contracts not yet signed)
        // We look for applications where contractFileName is NOT NULL but contractSignedFileName IS NULL?
        // Or just list them as "contracts sent". Let's list those where a contract was sent.
        // 3. Contracts List (Sent contracts, including signed ones)
        $pendingContracts = $em->getRepository(Application::class)->createQueryBuilder('a')
            ->where('a.employer = :employer')
            ->andWhere('a.contractFileName IS NOT NULL')
            ->setParameter('employer', $employer->getId(), \Symfony\Bridge\Doctrine\Types\UuidType::NAME)
            ->orderBy('a.contractSentAt', 'DESC')
            ->getQuery()
            ->getResult();

        // 4. Received Documents 
        // a) DocumentRequests that HAVE been provided
        $receivedRequests = $em->getRepository(DocumentRequest::class)->createQueryBuilder('dr')
            ->join('dr.application', 'a')
            ->where('a.employer = :employer')
            ->andWhere('dr.providedAt IS NOT NULL')
            ->andWhere('dr.document IS NOT NULL')
            ->setParameter('employer', $employer->getId(), \Symfony\Bridge\Doctrine\Types\UuidType::NAME)
            ->orderBy('dr.providedAt', 'DESC')
            ->getQuery()
            ->getResult();

        // b) Signed Contracts (Applications with signed contract file)
        $signedContracts = $em->getRepository(Application::class)->createQueryBuilder('a')
            ->where('a.employer = :employer')
            ->andWhere('a.contractSignedFileName IS NOT NULL')
            ->setParameter('employer', $employer->getId(), \Symfony\Bridge\Doctrine\Types\UuidType::NAME)
            ->orderBy('a.contractSignedAt', 'DESC')
            ->getQuery()
            ->getResult();

        // 3a. Pending One File Requests
        $pendingOneFileRequests = $em->getRepository(Application::class)->createQueryBuilder('a')
            ->where('a.employer = :employer')
            ->andWhere('a.oneFileRequestedAt IS NOT NULL')
            ->andWhere('a.oneFileProvidedAt IS NULL')
            ->setParameter('employer', $employer->getId(), \Symfony\Bridge\Doctrine\Types\UuidType::NAME)
            ->orderBy('a.oneFileRequestedAt', 'DESC')
            ->getQuery()
            ->getResult();

        // c) Completed Credentialing Links
        $completedLinks = $em->getRepository(\App\Entity\CredentialingLink::class)->createQueryBuilder('cl')
            ->join('cl.job', 'j')
            ->where('j.employer = :employer')
            ->andWhere('cl.completedAt IS NOT NULL')
            ->setParameter('employer', $employer)
            ->getQuery()
            ->getResult();

        // Merge and sort received documents (requests + signed contracts) for the "Uploaded Documents" list
        // logic to normalize them into a single structure for the template
        $receivedDocuments = [];

        foreach ($receivedRequests as $req) {
            $receivedDocuments[] = [
                'type' => 'request',
                'id' => $req->getId(),
                'name' => $req->getName(),
                'provider' => $req->getApplication()->getProvider(),
                'jobTitle' => $req->getApplication()->getJob() ? $req->getApplication()->getJob()->getTitle() : 'N/A',
                'date' => $req->getProvidedAt(),
                'document' => $req->getDocument(),
                'downloadUrl' => $this->generateUrl('app_employer_document_download', ['id' => $req->getDocument()->getId()]),
                'viewUrl' => $this->generateUrl('app_employer_document_view', ['id' => $req->getDocument()->getId()]),
                'status' => 'Received',
                'providerStatus' => $req->getApplication()->getStatus()
            ];
        }

        foreach ($signedContracts as $app) {
            $receivedDocuments[] = [
                'type' => 'contract',
                'id' => $app->getId(),
                'name' => 'Signed Contract - ' . $app->getJob()->getTitle(),
                'provider' => $app->getProvider(),
                'jobTitle' => $app->getJob() ? $app->getJob()->getTitle() : 'N/A',
                'date' => $app->getContractSignedAt(), // or uploadedAt
                'document' => null, // Needs special handling for download since it's a file path string in Application
                'filePath' => $app->getContractSignedFileName(), // Store path/filename
                'downloadUrl' => $this->generateUrl('app_employer_contract_download', ['id' => $app->getId()]),
                'viewUrl' => $this->generateUrl('app_employer_contract_view', ['id' => $app->getId()]),
                'status' => 'Signed',
                'providerStatus' => $app->getStatus()
            ];
        }

        foreach ($completedLinks as $link) {
            $receivedDocuments[] = [
                'type' => 'link',
                'id' => $link->getId(),
                'name' => 'Link Completed: ' . $link->getTitle(),
                'provider' => $link->getProvider(),
                'jobTitle' => $link->getJob() ? $link->getJob()->getTitle() : 'N/A',
                'date' => $link->getCompletedAt(),
                'document' => null,
                'externalUrl' => $link->getUrl(), // The link itself
                'downloadUrl' => null, // No file to download
                'viewUrl' => null,
                'status' => 'Completed',
                'providerStatus' => 'N/A'
            ];
        }

        // Sort by date DESC
        usort($receivedDocuments, function ($a, $b) {
            return $b['date'] <=> $a['date'];
        });

        // Active Applications for Dropdowns (Applied, Shortlisted, Interviewing, Negotiating, Accepted)
        $allowedStatuses = [
            Application::STATUS_APPLIED,
            Application::STATUS_SHORTLISTED,
            Application::STATUS_INTERVIEWING,
            Application::STATUS_NEGOTIATING,
            Application::STATUS_ACCEPTED
        ];

        $activeApplications = $em->getRepository(Application::class)->createQueryBuilder('a')
            ->where('a.employer = :employer')
            ->andWhere('a.isArchived = false')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('employer', $employer->getId(), \Symfony\Bridge\Doctrine\Types\UuidType::NAME)
            ->setParameter('statuses', $allowedStatuses)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('employer/document/index.html.twig', [
            'pendingDocumentRequests' => $pendingDocumentRequests,
            'pendingOneFileRequests' => $pendingOneFileRequests,
            'credentialingLinks' => $credentialingLinks,
            'pendingContracts' => $pendingContracts,
            'receivedDocuments' => $receivedDocuments,
            'activeApplications' => $activeApplications
        ]);
    }

    #[Route('/documents/download/{id}', name: 'app_employer_document_download')]
    public function download(
        Document $document,
        EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $employer = $user->getEmployer();

        if (!$employer) {
            throw $this->createAccessDeniedException('Employer not found.');
        }

        // Verify the employer has access to this document
        // Access is granted if the document is linked to a document request for one of their applications
        // OR if it's linked to an application of theirs (like a contract)

        $hasAccess = false;

        // Check DocumentRequests
        // Use robust PHP-side check to avoid query issues
        $requests = $em->getRepository(DocumentRequest::class)->findBy(['document' => $document]);
        foreach ($requests as $req) {
            $app = $req->getApplication();
            if ($app && $app->getEmployer() && $app->getEmployer() === $employer) {
                $hasAccess = true;
                break;
            }
        }

        if (!$hasAccess) {
            // Allow if the user OWNS the document (e.g. if we add employer upload later)
            if ($document->getUser() === $user) {
                $hasAccess = true;
            }
        }

        if (!$hasAccess) {
            throw $this->createAccessDeniedException('You do not have permission to view this document.');
        }

        $documentPath = $uploadDirectory . '/' . $document->getUser()->getId() . '/' . $document->getFileName();

        if (!file_exists($documentPath)) {
            throw $this->createNotFoundException('Document file not found.');
        }

        $response = new BinaryFileResponse($documentPath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $document->getFileName()
        );

        return $response;
    }

    #[Route('/documents/view/{id}', name: 'app_employer_document_view')]
    public function view(
        Document $document,
        EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $employer = $user->getEmployer();

        if (!$employer) {
            throw $this->createAccessDeniedException('Employer not found.');
        }

        // Access check (same as download)
        $hasAccess = false;

        // Check DocumentRequests (view)
        // Use robust PHP-side check to avoid query issues
        $requests = $em->getRepository(DocumentRequest::class)->findBy(['document' => $document]);
        foreach ($requests as $req) {
            $app = $req->getApplication();
            if ($app && $app->getEmployer() && $app->getEmployer() === $employer) {
                $hasAccess = true;
                break;
            }
        }

        if (!$hasAccess) {
            if ($document->getUser() === $user) {
                $hasAccess = true;
            }
        }

        if (!$hasAccess) {
            // Check if it's attached to an application of this employer (e.g. legacy logic)
            // simplified for now as per download logic
            throw $this->createAccessDeniedException('You do not have permission to view this document.');
        }

        $documentPath = $uploadDirectory . '/' . $document->getUser()->getId() . '/' . $document->getFileName();

        if (!file_exists($documentPath)) {
            throw $this->createNotFoundException('Document file not found.');
        }

        $response = new BinaryFileResponse($documentPath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $document->getFileName()
        );

        return $response;
    }

    #[Route('/documents/contract/download/{id}', name: 'app_employer_contract_download')]
    public function downloadContract(
        Application $application,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $employer = $user->getEmployer();

        if (!$employer) {
            throw $this->createAccessDeniedException('Employer not found.');
        }

        if ($application->getEmployer() !== $employer) {
            throw $this->createAccessDeniedException('You do not have access to this application contract.');
        }

        $filename = $application->getContractSignedFileName();
        if (!$filename) {
            throw $this->createNotFoundException('Signed contract not found.');
        }

        // Contracts signed by provider are stored in provider's user upload folder OR in /contracts/
        $providerUser = $application->getProvider()->getUser();

        // 1. Check Provider's User Folder
        $documentPath = $uploadDirectory . '/' . $providerUser->getId() . '/' . $filename;

        if (!file_exists($documentPath)) {
            // 2. Fallback: Check 'contracts' subfolder (used by Provider\JobController)
            $documentPath = $uploadDirectory . '/contracts/' . $filename;
        }

        if (!file_exists($documentPath)) {
            // Render attractive error page instead of 404 exception
            return $this->render('employer/document/file_not_found.html.twig', [
                'checked_path' => $documentPath
            ]);
        }

        $response = new BinaryFileResponse($documentPath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'Signed_Contract_' . $filename
        );

        return $response;
    }

    #[Route('/documents/contract/view/{id}', name: 'app_employer_contract_view')]
    public function viewContract(
        Application $application,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $employer = $user->getEmployer();

        if (!$employer) {
            throw $this->createAccessDeniedException('Employer not found.');
        }

        if ($application->getEmployer() !== $employer) {
            throw $this->createAccessDeniedException('You do not have access to this application contract.');
        }

        $filename = $application->getContractSignedFileName();
        if (!$filename) {
            // Fallback to unsigned contract if signed is missing but we want to view *a* contract? 
            // Usually we only view the signed one here based on index logic.
            throw $this->createNotFoundException('Signed contract not found.');
        }

        $providerUser = $application->getProvider()->getUser();
        $documentPath = $uploadDirectory . '/' . $providerUser->getId() . '/' . $filename;

        if (!file_exists($documentPath)) {
            $documentPath = $uploadDirectory . '/contracts/' . $filename;
        }

        if (!file_exists($documentPath)) {
            throw $this->createNotFoundException('Contract file not found on server.');
        }

        $response = new BinaryFileResponse($documentPath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            'Signed_Contract_' . $filename
        );

        return $response;
    }

    #[Route('/documents/request', name: 'app_employer_document_request', methods: ['POST'])]
    public function requestDocument(Request $request, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $employer = $user->getEmployer();

        if (!$employer) {
            throw $this->createAccessDeniedException();
        }

        $applicationId = $request->request->get('application_id');
        $documentName = $request->request->get('document_name');

        if (!$applicationId || !$documentName) {
            $this->addFlash('error', 'Missing required fields.');
            return $this->redirectToRoute('app_employer_documents');
        }

        $application = $em->getRepository(Application::class)->find($applicationId);

        if (!$application || $application->getEmployer() !== $employer) {
            $this->addFlash('error', 'Invalid application.');
            return $this->redirectToRoute('app_employer_documents');
        }

        $docRequest = new DocumentRequest();
        $docRequest->setApplication($application);
        $docRequest->setName($documentName);
        $docRequest->setRequestedBy($user->getRecruiter());

        $em->persist($docRequest);
        $em->flush();

        $this->addFlash('success', 'Document requested successfully.');
        return $this->redirectToRoute('app_employer_documents');
    }

    #[Route('/documents/upload-contract', name: 'app_employer_contract_upload', methods: ['POST'])]
    public function uploadContract(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $employer = $user->getEmployer();

        if (!$employer) {
            throw $this->createAccessDeniedException();
        }

        $applicationId = $request->request->get('application_id');
        $file = $request->files->get('contract_file');

        if (!$applicationId || !$file) {
            $this->addFlash('error', 'Missing application or file.');
            return $this->redirectToRoute('app_employer_documents');
        }

        $application = $em->getRepository(Application::class)->find($applicationId);

        if (!$application || $application->getEmployer() !== $employer) {
            $this->addFlash('error', 'Invalid application.');
            return $this->redirectToRoute('app_employer_documents');
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        // Move to 'contracts' folder or provider folder? 
        // Provider\JobController uses $uploadDirectory . '/contracts/'
        $targetDir = $uploadDirectory . '/contracts';

        try {
            $file->move($targetDir, $newFilename);

            $application->setContractFileName($newFilename);
            $application->setContractSentAt(new \DateTime());

            $em->persist($application);
            $em->flush();

            $this->addFlash('success', 'Contract uploaded and sent successfully.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error uploading file: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_employer_documents');
    }

    #[Route('/documents/request-one-file', name: 'app_employer_document_request_one_file', methods: ['POST'])]
    public function requestOneFile(Request $request, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $employer = $user->getEmployer();

        if (!$employer) {
            throw $this->createAccessDeniedException();
        }

        $applicationId = $request->request->get('application_id');

        if (!$applicationId) {
            $this->addFlash('error', 'Missing application.');
            return $this->redirectToRoute('app_employer_documents');
        }

        $application = $em->getRepository(Application::class)->find($applicationId);

        if (!$application || $application->getEmployer() !== $employer) {
            $this->addFlash('error', 'Invalid application.');
            return $this->redirectToRoute('app_employer_documents');
        }

        if ($application->getOneFileRequestedAt()) {
            $this->addFlash('info', 'One File already requested for this candidate.');
            return $this->redirectToRoute('app_employer_documents');
        }

        $application->setOneFileRequestedAt(new \DateTime());
        $em->persist($application);
        $em->flush();

        $this->addFlash('success', 'One File requested successfully.');
        return $this->redirectToRoute('app_employer_documents');
    }

    #[Route('/documents/send-credentialing-link', name: 'app_employer_send_credentialing_link', methods: ['POST'])]
    public function sendCredentialingLink(Request $request, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $employer = $user->getEmployer();

        if (!$employer) {
            throw $this->createAccessDeniedException();
        }

        $applicationId = $request->request->get('application_id');
        $title = $request->request->get('title');
        $url = $request->request->get('url');
        $description = $request->request->get('description');

        if (!$applicationId || !$title || !$url) {
            $this->addFlash('error', 'Missing required fields.');
            return $this->redirectToRoute('app_employer_documents');
        }

        $application = $em->getRepository(Application::class)->find($applicationId);

        if (!$application || $application->getEmployer() !== $employer) {
            $this->addFlash('error', 'Invalid application.');
            return $this->redirectToRoute('app_employer_documents');
        }

        $link = new \App\Entity\CredentialingLink();
        $link->setProvider($application->getProvider());
        $link->setJob($application->getJob());
        $link->setTitle($title);
        $link->setUrl($url);
        $link->setDescription($description);
        // $link->setSender($user->getName()); // Optional if needed

        $em->persist($link);
        $em->flush();

        $this->addFlash('success', 'Credentialing link sent successfully.');
        return $this->redirectToRoute('app_employer_documents');
    }
}
