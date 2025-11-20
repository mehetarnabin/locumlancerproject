<?php

namespace App\Controller\Provider;

use App\Entity\Document;
use App\Entity\DocumentRequest;
use App\Entity\CredentialingLink;
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
    #[Route('/documents', name: 'app_provider_documents')]
    public function index(
        DocumentRepository $documentRepository,
        DocumentRequestRepository $documentRequestRepository,
        Request $request,
        EntityManagerInterface $em, // Use EntityManager instead of CredentialingLinkRepository
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): Response
    {
        $user = $this->getUser();
        $provider = $user->getProvider();
        
        // TEMPORARY: Test if CredentialingLink works
        try {
            $testLink = new CredentialingLink();
            $testLink->setProvider($provider);
            $testLink->setTitle('Test Link - Please Delete');
            $testLink->setUrl('https://example.com/test');
            $testLink->setDescription('This is a test link to verify the entity works');
            $testLink->setCreatedAt(new \DateTime());
            $testLink->setSender('System');
            $testLink->setIsActive(true);
            
            $em->persist($testLink);
            $em->flush();
            
            $this->addFlash('success', '✅ CredentialingLink entity is working! Test link saved.');
        } catch (\Exception $e) {
            $this->addFlash('error', '❌ CredentialingLink error: ' . $e->getMessage());
        }
        
        $document = new Document();
        $form = $this->createForm(DocumentType::class, $document);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
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
            $em->flush();
            $this->addFlash('success', 'Document uploaded successfully.');

            return $this->redirectToRoute('app_provider_documents');
        }

        // Get document requests for the current provider
        $documentRequests = $documentRequestRepository->findBy(
            ['provider' => $provider],
            ['createdAt' => 'DESC']
        );

        // Get credentialing links using EntityManager instead of repository
        $credentialingLinks = $em->getRepository(CredentialingLink::class)->findBy([
            'provider' => $provider,
            'isActive' => true
        ], ['createdAt' => 'DESC']);

        return $this->render('provider/document/index.html.twig', [
            'form' => $form->createView(),
            'documents' => $documentRepository->findBy(['user' => $user], ['createdAt' => 'DESC']),
            'documentRequests' => $documentRequests,
            'credentialingLinks' => $credentialingLinks,
            'editMode' => false,
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
            $this->addFlash('error', 'You do not have permission to view this document.');
            return $this->redirectToRoute('app_provider_documents');
        }

        $documentPath = $uploadDirectory.'/'.$user->getId().'/'.$document->getFileName();
        $documentUrl = '/uploads/'.$user->getId().'/'.$document->getFileName();
        
        if (!file_exists($documentPath)) {
            $this->addFlash('error', 'Document file not found.');
            return $this->redirectToRoute('app_provider_documents');
        }

        return $this->render('provider/document/view.html.twig', [
            'document' => $document,
            'documentUrl' => $documentUrl,
            'documentPath' => $documentPath,
        ]);
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
}