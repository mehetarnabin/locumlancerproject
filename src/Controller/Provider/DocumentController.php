<?php

namespace App\Controller\Provider;

use App\Entity\Document;
use App\Entity\DocumentRequest;
use App\Entity\CredentialingLink;
use App\Entity\Application;
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
use App\Service\CredentialingLinkService;
 

#[Route('/provider')]
class DocumentController extends AbstractController
{
    #[Route('/documents', name: 'app_provider_documents')]
    public function index(
    DocumentRepository $documentRepository,
    DocumentRequestRepository $documentRequestRepository,
    Request $request,
    EntityManagerInterface $em,
    SluggerInterface $slugger,
    #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
): Response
{
    $user = $this->getUser();
    $provider = $user->getProvider();

    $document = new Document();
    $form = $this->createForm(DocumentType::class, $document);
    $form->handleRequest($request);
    $deaDocumentsData = $request->request->get('dea_documents');
    if (!is_array($deaDocumentsData)) { $deaDocumentsData = []; }
    $deaDocumentsFiles = $request->files->get('dea_documents');
    if (!is_array($deaDocumentsFiles)) { $deaDocumentsFiles = []; }
    $deaProcessed = false;

    if ($form->isSubmitted() && $form->isValid()) {
        /** @var UploadedFile $file */
        $file = $form->get('fileName')->getData();

        if ($file) {
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

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
            $document->setName($document->getCategory());
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

        if (is_array($deaDocumentsData) || is_array($deaDocumentsFiles)) {
            $userUploadDir = $uploadDirectory . '/' . $user->getId();
            if (!file_exists($userUploadDir)) {
                mkdir($userUploadDir, 0777, true);
            }
            foreach ($deaDocumentsData as $idx => $deaData) {
                $deaFile = $deaDocumentsFiles[$idx]['fileName'] ?? null;
                if (!$deaFile instanceof UploadedFile) {
                    continue;
                }
                $orig = pathinfo($deaFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safe = $slugger->slug($orig);
                $new = $safe.'-'.uniqid().'.'.$deaFile->guessExtension();
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
                    try { $deaDoc->setIssueDate(new \DateTime($deaData['issueDate'])); } catch (\Exception $e) {}
                }
                if (!empty($deaData['expirationDate'])) {
                    try { $deaDoc->setExpirationDate(new \DateTime($deaData['expirationDate'])); } catch (\Exception $e) {}
                }
                if (!$deaDoc->getName() && $deaDoc->getCategory()) {
                    $deaDoc->setName($deaDoc->getCategory());
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

    

    if ($request->isMethod('POST') && (is_array($deaDocumentsData) || is_array($deaDocumentsFiles))) {
        $userUploadDir = $uploadDirectory . '/' . $user->getId();
        if (!file_exists($userUploadDir)) {
            mkdir($userUploadDir, 0777, true);
        }
        foreach ($deaDocumentsData as $idx => $deaData) {
            $deaFile = $deaDocumentsFiles[$idx]['fileName'] ?? null;
            if (!$deaFile instanceof UploadedFile) {
                continue;
            }
            $orig = pathinfo($deaFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safe = $slugger->slug($orig);
            $new = $safe.'-'.uniqid().'.'.$deaFile->guessExtension();
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
                try { $deaDoc->setIssueDate(new \DateTime($deaData['issueDate'])); } catch (\Exception $e) {}
            }
            if (!empty($deaData['expirationDate'])) {
                try { $deaDoc->setExpirationDate(new \DateTime($deaData['expirationDate'])); } catch (\Exception $e) {}
            }
            if (!$deaDoc->getName() && $deaDoc->getCategory()) {
                $deaDoc->setName($deaDoc->getCategory());
            }
            $em->persist($deaDoc);
            $deaProcessed = true;
        }
        if ($deaProcessed) {
            $em->flush();
            $this->addFlash('success', 'DEA documents uploaded successfully.');
            return $this->redirectToRoute('app_provider_documents');
        }
    }

    // Fetch other data for the page (document requests, credentialing links, etc.)
    $documentRequests = $documentRequestRepository->findBy(['provider' => $provider], ['createdAt' => 'DESC']);
    $credentialingLinks = $em->getRepository(CredentialingLink::class)->findBy(['provider' => $provider, 'isActive' => true], ['createdAt' => 'DESC']);
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
        'latestCV' => $latestCV  // This was missing!
    ]);
}

    #[Route('/credentialing-links.json', name: 'app_provider_credentialing_links', methods: ['GET'])]
    public function credentialingLinks(CredentialingLinkService $credentialingLinkService): JsonResponse
    {
        $provider = $this->getUser()->getProvider();
        if (!$provider) {
            return $this->json(['success' => false, 'data' => [], 'message' => 'Provider not found'], 400);
        }
        $links = $credentialingLinkService->getActiveLinksForProvider($provider);
        $data = array_map(function($link){
            return [
                'id' => $link->getId(),
                'title' => $link->getTitle(),
                'url' => $link->getUrl(),
                'description' => $link->getDescription(),
                'sender' => $link->getSender(),
                'createdAt' => $link->getCreatedAt() ? $link->getCreatedAt()->format('Y-m-d H:i:s') : null,
                'jobId' => $link->getJob() ? $link->getJob()->getId() : null,
                'lastOpenedAt' => $link->getLastOpenedAt() ? $link->getLastOpenedAt()->format('Y-m-d H:i:s') : null,
                'openCount' => method_exists($link, 'getOpenCount') ? $link->getOpenCount() : null,
            ];
        }, $links);
        return $this->json(['success' => true, 'data' => $data]);
    }

    #[Route('/credentialing-links/{id}/opened', name: 'app_provider_credentialing_link_opened', methods: ['POST'])]
    public function markCredentialingLinkOpened(int $id, EntityManagerInterface $em): JsonResponse
    {
        $provider = $this->getUser()->getProvider();
        if (!$provider) {
            return $this->json(['success' => false, 'message' => 'Provider not found'], 400);
        }
        $link = $em->getRepository(CredentialingLink::class)->find($id);
        if (!$link) {
            return $this->json(['success' => false, 'message' => 'Link not found'], 404);
        }
        if ($link->getProvider()?->getId() !== $provider->getId()) {
            return $this->json(['success' => false, 'message' => 'Not authorized'], 403);
        }
        $link->setLastOpenedAt(new \DateTime());
        if (method_exists($link, 'getOpenCount') && method_exists($link, 'setOpenCount')) {
            $count = $link->getOpenCount();
            $link->setOpenCount($count + 1);
        }
        $em->flush();
        return $this->json([
            'success' => true,
            'lastOpenedAt' => $link->getLastOpenedAt() ? $link->getLastOpenedAt()->format('Y-m-d H:i:s') : null,
            'openCount' => method_exists($link, 'getOpenCount') ? $link->getOpenCount() : null,
        ]);
    }

    #[Route('/documents/list', name: 'app_provider_documents_list', methods: ['GET'])]
    public function listDocuments(DocumentRepository $documentRepository): JsonResponse
    {
        $user = $this->getUser();
        $docs = $documentRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);
        $data = array_map(function (\App\Entity\Document $doc) {
            return [
                'id' => (string)$doc->getId(),
                'name' => $doc->getDisplayName(),
                'fileName' => $doc->getFileName(),
                'mimeType' => $doc->getMimeType(),
                'createdAt' => $doc->getCreatedAt()?->format('c'),
            ];
        }, $docs);
        return $this->json(['success' => true, 'documents' => $data]);
    }

    #[Route('/documents/edit/{id}', name: 'app_provider_document_edit')]
    public function edit(
        Document $document,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        DocumentRequestRepository $documentRequestRepository,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): Response
    {
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
                $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

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
            'isActive' => true
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
    ): Response
    {
        $user = $this->getUser();
        
        if ($document->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You do not have permission to view this document.');
        }

        $documentPath = $uploadDirectory.'/'.$user->getId().'/'.$document->getFileName();
        
        if (!file_exists($documentPath)) {
            throw $this->createNotFoundException('Document file not found.');
        }

        $response = new \Symfony\Component\HttpFoundation\BinaryFileResponse($documentPath);
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
    ): Response
    {
        $user = $this->getUser();

        $documentPath = $uploadDirectory.'/'.$user->getId().'/'.$document->getFileName();
        if(file_exists($documentPath)) {
            unlink($documentPath);
        }

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

    #[Route('/documents/upload-ajax', name: 'app_provider_documents_upload_ajax', methods: ['POST'])]
    public function uploadAjax(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        $category = $request->request->get('category');
        if (!$file instanceof UploadedFile) {
            return $this->json(['success' => false, 'message' => 'No file uploaded'], 400);
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

        try {
            $userUploadDir = $uploadDirectory . '/' . $user->getId();
            if (!file_exists($userUploadDir)) {
                mkdir($userUploadDir, 0777, true);
            }
            $file->move($userUploadDir, $newFilename);
        } catch (FileException $e) {
            return $this->json(['success' => false, 'message' => 'File upload failed'], 500);
        }

        $document = new Document();
        $document->setUser($user);
        $document->setFileName($newFilename);
        $document->setMimeType($file->getClientMimeType());
        if ($category) {
            $document->setCategory($category);
        }

        $em->persist($document);
        $em->flush();

        return $this->json([
            'success' => true,
            'document' => [
                'id' => (string)$document->getId(),
                'name' => $document->getDisplayName(),
                'fileName' => $document->getFileName(),
                'mimeType' => $document->getMimeType(),
            ]
        ]);
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
                $newFilename = $safeFilename.'-'.uniqid().'.'.$uploadedFile->guessExtension();

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

    #[Route('/documents/application/{id}/requests-details', name: 'app_provider_application_document_requests_details', methods: ['GET'])]
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
            $doc = $req->getDocument();
            return [
                'id' => (string)$req->getId(),
                'name' => $req->getName(),
                'createdAt' => $req->getCreatedAt()?->format('c'),
                'providedAt' => $req->getProvidedAt()?->format('c'),
                'documentUrl' => $doc ? $doc->getFilePath() : null,
                'documentName' => $doc ? $doc->getFileName() : null,
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

    #[Route('/applications/{id}/upload-document', name: 'app_provider_application_upload_document', methods: ['POST'])]
    public function uploadDocumentFromApplication(
        string $id,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        EventDispatcherInterface $dispatcher,
        ToDoRepository $todoRepository,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): JsonResponse {
        $user = $this->getUser();
        $provider = $user->getProvider();

        // Get the application
        $application = $em->getRepository(Application::class)->find($id);
        if (!$application || $application->getProvider() !== $provider) {
            return $this->json(['success' => false, 'message' => 'Application not found'], 404);
        }

        // Get the uploaded file
        $uploadedFile = $request->files->get('documentFile');
        if (!$uploadedFile) {
            return $this->json(['success' => false, 'message' => 'No file uploaded'], 400);
        }

        // Validate file
        $maxFileSize = 10 * 1024 * 1024; // 10MB
        if ($uploadedFile->getSize() > $maxFileSize) {
            return $this->json(['success' => false, 'message' => 'File size exceeds 10MB limit'], 400);
        }

        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
        $fileExtension = strtolower($uploadedFile->getClientOriginalExtension());
        if (!in_array($fileExtension, $allowedExtensions)) {
            return $this->json(['success' => false, 'message' => 'File type not allowed'], 400);
        }

        try {
            // Create Document object
            $document = new Document();
            $document->setUser($user); // Set the User, not Provider entity directly if types mismatch
            $document->setApplication($application);
            $document->setName($uploadedFile->getClientOriginalName());

            // Save the file
            $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $uploadedFile->guessExtension();

            $userUploadDir = $uploadDirectory . '/' . $user->getId();
            if (!file_exists($userUploadDir)) {
                mkdir($userUploadDir, 0777, true);
            }
            $uploadedFile->move($userUploadDir, $newFilename);
            
            $document->setFileName($newFilename); // Required field
            $document->setFilePath('/uploads/' . $user->getId() . '/' . $newFilename);
            $document->setMimeType($uploadedFile->getClientMimeType());
            $document->setCategory('Application Document');

            // Add description if provided
            $description = $request->request->get('documentDescription');
            if ($description) {
                $document->setDescription($description);
            }

            $em->persist($document);
            $em->flush();

            // Create event notification
            $event = new ApplicationEvent($application);
            $dispatcher->dispatch($event, ApplicationEvent::DOCUMENT_UPLOADED);

            // Check if we should also assign to a document request
            $documentRequestId = $request->request->get('documentRequestId');
            $assigned = false;
            $providedAt = null;

            if ($documentRequestId) {
                $documentRequest = $em->getRepository(DocumentRequest::class)->find($documentRequestId);
                if ($documentRequest && $documentRequest->getApplication() === $application) {
                    // Assign the document to the request
                    $documentRequest->setDocument($document);
                    $documentRequest->setProvidedAt(new \DateTime());
                    $providedAt = $documentRequest->getProvidedAt()->format('m/d/Y');

                    // Mark to-do as completed
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

                    // Dispatch event for assignment
                    $dispatcher->dispatch(
                        new ApplicationEvent($application),
                        ApplicationEvent::APPLICATION_DOCUMENT_PROVIDED
                    );

                    $assigned = true;
                }
            }

            return $this->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'documentId' => $document->getId(),
                'assigned' => $assigned,
                'providedAt' => $providedAt
            ]);
        } catch (FileException $e) {
            return $this->json(['success' => false, 'message' => 'Error uploading file: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}
