<?php

namespace App\Controller\Provider;

use App\Entity\Document;
use App\Entity\DocumentRequest;
use App\Entity\CredentialingLink;
use App\Entity\Application;
use App\Entity\LinkTrackingLog;
use App\Event\ApplicationEvent;
use App\Form\DocumentType;
use App\Repository\DocumentRepository;
use App\Repository\DocumentRequestRepository;
use App\Repository\ToDoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Route('/provider')]
class DocumentController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }
    
    #[Route('/documents', name: 'app_provider_documents')]
    public function index(
        DocumentRepository $documentRepository,
        DocumentRequestRepository $documentRequestRepository,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): Response {
        $user = $this->getUser();
        $provider = $user->getProvider();

        $document = new Document();
        $form = $this->createForm(DocumentType::class, $document);
        $form->handleRequest($request);
        $deaDocumentsData = $request->request->all('dea_documents');
        if (!is_array($deaDocumentsData)) {
            $deaDocumentsData = [];
        }
        $deaDocumentsFiles = $request->files->all('dea_documents');
        if (!is_array($deaDocumentsFiles)) {
            $deaDocumentsFiles = [];
        }
        $deaProcessed = false;

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $file */
            $file = $form->get('fileName')->getData();

            if ($file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

                try {
                    $userUploadDir = $uploadDirectory . '/' . $user->getId();
                    if (!file_exists($userUploadDir)) {
                        mkdir($userUploadDir, 0777, true);
                    }
                    $file->move($userUploadDir, $newFilename);
                } catch (FileException $e) {
                    if ($request->isXmlHttpRequest()) {
                        return $this->json(['success' => false, 'message' => 'File upload failed: ' . $e->getMessage()], 500);
                    }
                    $this->addFlash('error', 'File upload failed: ' . $e->getMessage());
                    return $this->redirectToRoute('app_provider_documents');
                }

                $document->setFileName($newFilename);
                $document->setMimeType($file->getClientMimeType());
            }

            $document->setUser($user);
            if (!$document->getName() && $document->getCategory()) {
                if ($document->getCategory() === 'All DEAs') {
                    $document->setName('DEA 1');
                } else {
                    $document->setName($document->getCategory());
                }
            }

            // Auto-expiry logic
            $autoExpiryDocuments = [
                'Negative TB test (TST vs IGRA) in the last 12 months (or if positive a CXR is required)',
                'Influenza vaccine proof',
                'COVID-19 vaccine proof',
                'Mask fit testing'
            ];

            if ($document->getCategory() && in_array($document->getCategory(), $autoExpiryDocuments)) {
                if ($document->getIssueDate() && !$document->getExpirationDate()) {
                    $expirationDate = clone $document->getIssueDate();
                    $expirationDate->modify('+1 year');
                    $document->setExpirationDate($expirationDate);
                }
            }

            $em->persist($document);

            if (!empty($deaDocumentsFiles)) {
                $userUploadDir = $uploadDirectory . '/' . $user->getId();
                if (!file_exists($userUploadDir)) {
                    mkdir($userUploadDir, 0777, true);
                }
                foreach ($deaDocumentsFiles as $idx => $deaFileGroup) {
                    $deaFile = $deaFileGroup['fileName'] ?? null;
                    $deaData = $deaDocumentsData[$idx] ?? [];
                    if (!$deaFile instanceof UploadedFile) {
                        continue;
                    }
                    $orig = pathinfo($deaFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safe = $slugger->slug($orig);
                    $new = $safe . '-' . uniqid() . '.' . $deaFile->guessExtension();
                    try {
                        $deaFile->move($userUploadDir, $new);
                    } catch (FileException $e) {
                        continue;
                    }
                    $deaDoc = new Document();
                    $deaDoc->setUser($user);
                    $deaDoc->setCategory(isset($deaData['category']) && $deaData['category'] ? $deaData['category'] : 'All DEAs');
                    $deaDoc->setFileName($new);
                    $deaDoc->setMimeType($deaFile->getClientMimeType());
                    if (!empty($deaData['issueDate'])) {
                        try {
                            $deaDoc->setIssueDate(new \DateTime($deaData['issueDate']));
                        } catch (\Exception $e) {
                        }
                    }
                    if (!empty($deaData['expirationDate'])) {
                        try {
                            $deaDoc->setExpirationDate(new \DateTime($deaData['expirationDate']));
                        } catch (\Exception $e) {
                        }
                    }
                    if (!$deaDoc->getName()) {
                        if (($deaDoc->getCategory() ?? '') === 'All DEAs') {
                            $deaDoc->setName('DEA ' . ((int)$idx + 1));
                        } else if ($deaDoc->getCategory()) {
                            $deaDoc->setName($deaDoc->getCategory());
                        }
                    }
                    $em->persist($deaDoc);
                    $deaProcessed = true;
                }
            }
            $em->flush();

            // AJAX response for CV upload
            if ($request->isXmlHttpRequest()) {
                $cvListHtml = '';
                $latestCV = null;

                if ($document->getCategory() === 'CV') {
                    // Get all CV documents for the list
                    $cvDocuments = $documentRepository->findBy(
                        ['user' => $user, 'category' => 'CV'],
                        ['createdAt' => 'DESC']
                    );

                    $cvListHtml = $this->renderView('provider/profile/_cv_list.html.twig', [
                        'cvDocuments' => $cvDocuments,
                        'uploadDirectory' => '/uploads/' . $user->getId()
                    ]);

                    // Get the latest CV document (most recently uploaded)
                    $latestCVDocument = $documentRepository->findOneBy(
                        ['user' => $user, 'category' => 'CV'],
                        ['createdAt' => 'DESC']
                    );

                    if ($latestCVDocument) {
                        $latestCV = [
                            'fileName' => $latestCVDocument->getFileName(),
                            'userId' => $user->getId()
                        ];
                    }
                }

                return $this->json([
                    'success' => true,
                    'message' => 'Document uploaded successfully.',
                    'fileName' => $document->getName() ?: $file->getClientOriginalName(),
                    'cvListHtml' => $cvListHtml,
                    'latestCV' => $latestCV
                ]);
            }

            $this->addFlash('success', 'Document uploaded successfully.');
            return $this->redirectToRoute('app_provider_documents');
        }

        if ($request->isMethod('POST') && !empty($deaDocumentsFiles)) {
            $userUploadDir = $uploadDirectory . '/' . $user->getId();
            if (!file_exists($userUploadDir)) {
                mkdir($userUploadDir, 0777, true);
            }

            $uploadErrors = [];
            $deaProcessedCount = 0;

            foreach ($deaDocumentsFiles as $idx => $deaFileGroup) {
                $deaFile = $deaFileGroup['fileName'] ?? null;
                $deaData = $deaDocumentsData[$idx] ?? [];

                if (!$deaFile instanceof UploadedFile) {
                    continue;
                }

                // Validation
                $maxSize = 8 * 1024 * 1024; // 8MB
                $allowedMimeTypes = [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                ];

                if ($deaFile->getSize() > $maxSize) {
                    $uploadErrors[] = sprintf('File "%s" exceeds the maximum allowed size of 8MB.', $deaFile->getClientOriginalName());
                    continue;
                }

                if (!in_array($deaFile->getClientMimeType(), $allowedMimeTypes)) {
                    $uploadErrors[] = sprintf('File "%s" has an invalid format. Only PDF and DOCX are allowed.', $deaFile->getClientOriginalName());
                    continue;
                }

                $orig = pathinfo($deaFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safe = $slugger->slug($orig);
                $new = $safe . '-' . uniqid() . '.' . $deaFile->guessExtension();

                try {
                    $deaFile->move($userUploadDir, $new);
                } catch (FileException $e) {
                    $uploadErrors[] = sprintf('Failed to upload file "%s": %s', $deaFile->getClientOriginalName(), $e->getMessage());
                    continue;
                }

                $deaDoc = new Document();
                $deaDoc->setUser($user);
                $deaDoc->setCategory(isset($deaData['category']) && $deaData['category'] ? $deaData['category'] : 'All DEAs');
                $deaDoc->setFileName($new);
                $deaDoc->setMimeType($deaFile->getClientMimeType());

                if (!empty($deaData['issueDate'])) {
                    try {
                        $deaDoc->setIssueDate(new \DateTime($deaData['issueDate']));
                    } catch (\Exception $e) {
                    }
                }
                if (!empty($deaData['expirationDate'])) {
                    try {
                        $deaDoc->setExpirationDate(new \DateTime($deaData['expirationDate']));
                    } catch (\Exception $e) {
                    }
                }

                if (!$deaDoc->getName()) {
                    if (($deaDoc->getCategory() ?? '') === 'All DEAs') {
                        // Find existing DEA documents to determine the next number
                        $existingDeas = $documentRepository->createQueryBuilder('d')
                            ->where('d.user = :user')
                            ->andWhere('d.category = :category')
                            ->setParameter('user', $user)
                            ->setParameter('category', 'All DEAs')
                            ->getQuery()
                            ->getResult();

                        $nextNum = count($existingDeas) + $deaProcessedCount + 1;
                        $deaDoc->setName('DEA ' . $nextNum);
                    } else if ($deaDoc->getCategory()) {
                        $deaDoc->setName($deaDoc->getCategory());
                    }
                }

                $em->persist($deaDoc);
                $deaProcessed = true;
                $deaProcessedCount++;
            }

            if ($deaProcessed) {
                $em->flush();
                $this->addFlash('success', 'DEA documents uploaded successfully.');
            }

            if (!empty($uploadErrors)) {
                foreach ($uploadErrors as $error) {
                    $this->addFlash('error', $error);
                }
            }

            if ($deaProcessed || !empty($uploadErrors)) {
                return $this->redirectToRoute('app_provider_documents');
            }
        }

        // Fetch other data for the page (document requests, credentialing links, etc.)
        $documentRequests = $documentRequestRepository->findBy(['provider' => $provider], ['createdAt' => 'DESC']);
        $credentialingLinks = $em->getRepository(CredentialingLink::class)->findBy([
            'provider' => $provider,
            'isActive' => true,
            'status' => ['pending', 'viewed']
        ], ['createdAt' => 'DESC']);
        $documents = $documentRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);

        // Get the latest CV document for initial page load
        $latestCV = $documentRepository->findOneBy(
            ['user' => $user, 'category' => 'CV'],
            ['createdAt' => 'DESC']
        );

        return $this->render('provider/document/index.html.twig', [
            'form' => $form->createView(),
            'documents' => $documents,
            'documentRequests' => $documentRequests,
            'credentialingLinks' => $credentialingLinks,
            'editMode' => false,
            'uploadDirectory' => '/uploads/' . $user->getId(),
            'latestCV' => $latestCV
        ]);
    }

    #[Route('/documents/edit/{id}', name: 'app_provider_document_edit')]
    public function edit(
        Document $document,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        DocumentRequestRepository $documentRequestRepository,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): Response {
        $user = $this->getUser();
        $provider = $user->getProvider();

        $form = $this->createForm(DocumentType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $file */
            $file = $form->get('fileName')->getData();

            if ($file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

                try {
                    $file->move($uploadDirectory . '/' . $user->getId(), $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'File upload failed.');
                    return $this->redirectToRoute('app_provider_documents');
                }

                $document->setFileName($newFilename);
                $document->setMimeType($file->getClientMimeType());
            }

            if (!$document->getName() && $document->getCategory()) {
                $document->setName($document->getCategory());
            }

            $autoExpiryDocuments = [
                'Negative TB test (TST vs IGRA) in the last 12 months (or if positive a CXR is required)',
                'Influenza vaccine proof',
                'COVID-19 vaccine proof',
                'Mask fit testing'
            ];

            if ($document->getCategory() && in_array($document->getCategory(), $autoExpiryDocuments)) {
                if ($document->getIssueDate() && !$document->getExpirationDate()) {
                    $expirationDate = clone $document->getIssueDate();
                    $expirationDate->modify('+1 year');
                    $document->setExpirationDate($expirationDate);
                }
            }

            $em->flush();
            $this->addFlash('success', 'Document updated successfully.');

            return $this->redirectToRoute('app_provider_documents');
        }

        // Get credentialing links using EntityManager
        $credentialingLinks = $em->getRepository(CredentialingLink::class)->findBy([
            'provider' => $provider,
            'isActive' => true,
            'status' => ['pending', 'viewed']
        ], ['createdAt' => 'DESC']);

        return $this->render('provider/document/index.html.twig', [
            'form' => $form->createView(),
            'documents' => $em->getRepository(Document::class)->findBy(['user' => $user], ['createdAt' => 'DESC']),
            'editMode' => true,
            'editId' => $document->getId(),
            'editDocument' => $document,
            'documentRequests' => $documentRequestRepository->findBy(['provider' => $provider], ['createdAt' => 'DESC']),
            'credentialingLinks' => $credentialingLinks,
        ]);
    }

    #[Route('/documents/view/{id}', name: 'app_provider_document_view')]
    public function view(
        Document $document,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): Response {
        $user = $this->getUser();

        if ($document->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You do not have permission to view this document.');
        }

        $documentPath = $uploadDirectory . '/' . $user->getId() . '/' . $document->getFileName();

        if (!file_exists($documentPath)) {
            // Try alternative path if first fails
            $altPath = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $user->getId() . '/' . $document->getFileName();
            
            if (!file_exists($altPath)) {
                throw $this->createNotFoundException('Document file not found at: ' . $documentPath);
            }
            $documentPath = $altPath;
        }

        // Determine MIME type
        $mimeType = mime_content_type($documentPath) ?: 'application/octet-stream';
        
        $response = new \Symfony\Component\HttpFoundation\BinaryFileResponse($documentPath);
        $response->headers->set('Content-Type', $mimeType);
        $response->setContentDisposition(
            \Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_INLINE,
            $document->getFileName()
        );

        return $response;
    }

    #[Route('/documents/delete/{id}', name: 'app_provider_document_delete', methods: ['GET'])]
public function delete(
    Document $document,
    EntityManagerInterface $em,
    #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
): Response {
    $user = $this->getUser();

    // Check if the document belongs to the current user
    if ($document->getUser()->getId() !== $user->getId()) {
        throw $this->createAccessDeniedException('You do not have permission to delete this document.');
    }

    // First, check if there are any document requests referencing this document
    $documentRequests = $em->getRepository(DocumentRequest::class)->findBy(['document' => $document]);
    
    if (count($documentRequests) > 0) {
        // REMOVE THIS: Option A: Prevent deletion and show an error message
        // $this->addFlash('error', 'Cannot delete this document because it is referenced by ' . count($documentRequests) . ' document request(s). Please reassign or delete those requests first.');
        // return $this->redirectToRoute('app_provider_documents');
        
        // ADD THIS INSTEAD: Option B: Set document to null in all requests
        foreach ($documentRequests as $request) {
            $request->setDocument(null);
            $em->persist($request);
        }
        $em->flush();
    }

    // Delete the file from the filesystem
    $documentPath = $uploadDirectory . '/' . $user->getId() . '/' . $document->getFileName();
    if (file_exists($documentPath)) {
        unlink($documentPath);
    }

    // Remove the document entity
    $em->remove($document);
    $em->flush();

    $this->addFlash('success', 'Document deleted successfully.');
    return $this->redirectToRoute('app_provider_documents');
}

    #[Route('/documents/assign-multiple', name: 'app_provider_document_request_assign_bulk', methods: ['POST'])]
    public function assignDocumentsBulk(
        Request $request,
        DocumentRepository $documentRepository,
        DocumentRequestRepository $documentRequestRepository,
        EntityManagerInterface $em,
        ToDoRepository $todoRepository,
        EventDispatcherInterface $dispatcher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $requestIds = $data['requestIds'] ?? [];
        $documentIds = $data['documentIds'] ?? [];

        if (empty($requestIds) || empty($documentIds)) {
            return $this->json([
                'success' => false,
                'message' => 'Select at least one request and one document.'
            ], 400);
        }

        $currentUser = $this->getUser();
        $currentProvider = $currentUser->getProvider();

        $requests = $documentRequestRepository->findBy(['id' => $requestIds]);
        $requestMap = [];
        foreach ($requests as $req) {
            $requestMap[$req->getId()] = $req;
        }

        $documents = $documentRepository->findBy(['id' => $documentIds, 'user' => $currentUser]);
        $documentMap = [];
        foreach ($documents as $doc) {
            $documentMap[$doc->getId()] = $doc;
        }

        if (count($documentMap) === 0) {
            return $this->json([
                'success' => false,
                'message' => 'No valid documents were selected.'
            ], 400);
        }

        $assignedRequestIds = [];
        $errors = [];

        $documentIdCount = count($documentIds);

        foreach ($requestIds as $index => $requestId) {
            if (!isset($requestMap[$requestId])) {
                $errors[] = sprintf('Request %s not found.', $requestId);
                continue;
            }

            $documentRequest = $requestMap[$requestId];

            if ($documentRequest->getProvider()->getId() !== $currentProvider->getId()) {
                $errors[] = sprintf('You cannot update request %s', $requestId);
                continue;
            }

            if ($documentRequest->getProvidedAt() !== null) {
                $errors[] = sprintf('Request %s already fulfilled.', $requestId);
                continue;
            }

            $documentId = $documentIds[$index] ?? $documentIds[$documentIdCount - 1];
            $document = $documentMap[$documentId] ?? null;

            if (!$document) {
                $errors[] = sprintf('Document %s not available.', $documentId);
                continue;
            }

            $documentRequest->setDocument($document);
            $documentRequest->setProvidedAt(new \DateTime());

            $todo = $todoRepository->findOneBy([
                'documentRequest' => $documentRequest,
                'isCompleted' => false
            ]);

            if ($todo) {
                $todo->setIsCompleted(true);
                $em->persist($todo);
            }

            $em->persist($documentRequest);
            $assignedRequestIds[] = $documentRequest->getId();

            if ($documentRequest->getApplication()) {
                $dispatcher->dispatch(
                    new ApplicationEvent($documentRequest->getApplication()),
                    ApplicationEvent::APPLICATION_DOCUMENT_PROVIDED
                );
            }
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'assignedRequestIds' => $assignedRequestIds,
            'errors' => $errors
        ]);
    }

    #[Route('/document-request/{id}/assign-document', name: 'app_provider_document_request_assign', methods: ['POST'])]
    public function assignDocument(
        DocumentRequest $documentRequest,
        Request $request,
        EntityManagerInterface $em,
        DocumentRepository $documentRepo,
        ToDoRepository $todoRepository,
        EventDispatcherInterface $dispatcher,
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            $documentId = $data['documentId'] ?? null;

            if (!$documentId) {
                return $this->json([
                    'success' => false,
                    'message' => 'No document ID provided'
                ], 400);
            }

            $document = $documentRepo->find($documentId);

            if (!$document) {
                return $this->json([
                    'success' => false,
                    'message' => 'Document not found'
                ], 404);
            }

            $currentUser = $this->getUser();
            if ($document->getUser()->getId() !== $currentUser->getId()) {
                return $this->json([
                    'success' => false,
                    'message' => 'You do not have permission to assign this document'
                ], 403);
            }

            if ($documentRequest->getProvidedAt() !== null) {
                return $this->json([
                    'success' => false,
                    'message' => 'This document request has already been fulfilled'
                ], 400);
            }

            $currentProvider = $currentUser->getProvider();
            if ($documentRequest->getProvider()->getId() !== $currentProvider->getId()) {
                return $this->json([
                    'success' => false,
                    'message' => 'You do not have permission to assign documents to this request'
                ], 403);
            }

            $documentRequest->setDocument($document);
            $documentRequest->setProvidedAt(new \DateTime());

            $todo = $todoRepository->findOneBy([
                'documentRequest' => $documentRequest,
                'isCompleted' => false
            ]);

            if ($todo) {
                $todo->setIsCompleted(true);
                $em->persist($todo);
            }

            $em->persist($documentRequest);
            $em->flush();

            if ($documentRequest->getApplication()) {
                $dispatcher->dispatch(
                    new ApplicationEvent($documentRequest->getApplication()),
                    ApplicationEvent::APPLICATION_DOCUMENT_PROVIDED
                );
            }

            return $this->json([
                'success' => true,
                'message' => 'Document assigned successfully',
                'providedAtFormatted' => $documentRequest->getProvidedAt()->format('m/d/Y')
            ]);
        } catch (\Exception $e) {
            error_log('Document assignment error: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'message' => 'An error occurred while assigning the document. Please try again.'
            ], 500);
        }
    }

    #[Route('/profile/upload-cv', name: 'profile_upload_cv')]
    public function upload(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $document = new Document();
        $document->setUser($this->getUser());
        $form = $this->createForm(DocumentType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFile = $form['fileName']->getData();

            if ($uploadedFile) {
                $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $uploadedFile->guessExtension();

                $uploadedFile->move(
                    $this->getParameter('documents_directory'),
                    $newFilename
                );

                $document->setFileName($newFilename);
                $document->setMimeType($uploadedFile->getMimeType());
            }

            $em->persist($document);
            $em->flush();

            $this->addFlash('success', 'CV uploaded successfully!');
            return $this->redirectToRoute('profile_upload_cv');
        }

        return $this->render('provider/profile/upload_cv.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/documents/application/{id}/requests', name: 'app_provider_application_document_requests', methods: ['GET'])]
    public function applicationDocumentRequests(
        Application $application,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $provider = $this->getUser()->getProvider();
        if (!$provider || $application->getProvider() !== $provider) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $requests = $em->getRepository(DocumentRequest::class)->findBy([
            'application' => $application,
            'provider' => $provider,
        ], ['createdAt' => 'DESC']);

        $data = array_map(function (DocumentRequest $req) {
            return [
                'id' => (string)$req->getId(),
                'name' => $req->getName(),
                'createdAt' => $req->getCreatedAt()?->format('c'),
                'providedAt' => $req->getProvidedAt()?->format('c'),
            ];
        }, $requests);

        $job = $application->getJob();
        $links = [];
        if ($job) {
            $links = $em->getRepository(\App\Entity\CredentialingLink::class)->findBy([
                'provider' => $provider,
                'isActive' => true,
                'job' => $job,
            ], ['createdAt' => 'DESC']);
        }
        $linksData = array_map(function (\App\Entity\CredentialingLink $link) {
            return [
                'id' => $link->getId(),
                'title' => $link->getTitle(),
                'url' => $link->getUrl(),
                'description' => $link->getDescription(),
                'createdAt' => $link->getCreatedAt()?->format('c'),
            ];
        }, $links);

        return $this->json(['success' => true, 'documentRequests' => $data, 'credentialLinks' => $linksData]);
    }

#[Route('/link-track/event', name: 'app_provider_link_track_event', methods: ['POST'])]
public function trackLinkEvent(Request $request): JsonResponse
{
    try {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['linkId']) || !isset($data['action'])) {
            return $this->json(['success' => false, 'message' => 'Missing parameters'], 400);
        }
        
        $link = $this->entityManager->getRepository(CredentialingLink::class)->find($data['linkId']);
        
        if (!$link) {
            return $this->json(['success' => false, 'message' => 'Link not found'], 404);
        }
        
        $currentUser = $this->getUser();
        $currentProvider = $currentUser->getProvider();
        
        // Verify the link belongs to the current provider
        if (!$currentProvider || $link->getProvider()->getId() !== $currentProvider->getId()) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        $message = '';
        
        // Handle different actions
        switch ($data['action']) {
            case 'opened':
                $link->setLastOpenedAt(new \DateTime());
                $link->setOpenCount($link->getOpenCount() + 1);
                
                if ($link->getStatus() === 'pending') {
                    $link->setStatus('viewed');
                }
                $message = 'Link opened successfully';
                break;
                
            case 'submitted':
                $link->setStatus('submitted');
                $link->setSubmittedAt(new \DateTime());
                $link->setProviderResponse('Form submitted by user');
                $link->setIsActive(false);
                $message = 'Form submitted successfully! The link has been moved to completed section.';
                break;
                
            case 'completed':
                $link->setStatus('completed');
                $link->setCompletedAt(new \DateTime());
                $link->setIsActive(false);
                $message = 'Link marked as completed';
                break;
                
            default:
                return $this->json(['success' => false, 'message' => 'Unknown action'], 400);
        }
        
        $this->entityManager->persist($link);
        $this->entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => $message,
            'status' => $link->getStatus(),
            'isActive' => $link->getIsActive()
        ]);
        
    } catch (\Exception $e) {
        error_log('Link tracking error: ' . $e->getMessage());
        
        return $this->json([
            'success' => false, 
            'message' => 'An error occurred. Please try again.'
        ], 500);
    }
}

// Remove the createSimpleDocumentRecord and createDocumentFromLink methods completely
// They're no longer needed since we're not creating Document records

#[Route('/link/{id}/complete', name: 'app_provider_link_complete', methods: ['POST'])]
public function completeLink(Request $request, CredentialingLink $link): JsonResponse
{
    // Mark link as completed and remove from pending
    $link->setStatus('submitted'); // Changed from 'completed' to 'submitted' for consistency
    $link->setSubmittedAt(new \DateTime());
    $link->setProviderResponse('User confirmed form submission');
    $link->setIsActive(false);
    
    $this->entityManager->flush();
    
    return $this->json([
        'success' => true,
        'message' => 'Form submitted successfully! The link has been moved to completed section.',
        'redirect' => $this->generateUrl('app_provider_documents')
    ]);
}

    #[Route('/link/{id}/status', name: 'app_provider_link_status', methods: ['GET'])]
    public function getLinkStatus(CredentialingLink $link): JsonResponse
    {
        $currentUser = $this->getUser();
        $currentProvider = $currentUser->getProvider();
        
        if ($link->getProvider()->getId() !== $currentProvider->getId()) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        return $this->json([
            'success' => true,
            'status' => $link->getStatus(),
            'lastOpenedAt' => $link->getLastOpenedAt()?->format('Y-m-d H:i:s'),
            'submittedAt' => $link->getSubmittedAt()?->format('Y-m-d H:i:s'),
            'openCount' => $link->getOpenCount()
        ]);
    }
}
