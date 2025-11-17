<?php

namespace App\Controller\Provider;

use App\Entity\Document;
use App\Entity\DocumentRequest;
use App\Event\ApplicationEvent;
use App\Form\DocumentType;
use App\Repository\DocumentRepository;
use App\Repository\DocumentRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Uid\Uuid;
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
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): Response
    {
        $user = $this->getUser();
        $document = new Document();
        $form = $this->createForm(DocumentType::class, $document);
        $form->handleRequest($request);

        if($request->getMethod() == 'POST') {
            if ($form->isSubmitted() && $form->isValid()) {
                /** @var UploadedFile $file */
                $file = $form->get('fileName')->getData();

                if ($file) {
                    $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    // this is needed to safely include the file name as part of the URL
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

                $document->setUser($user);

                $em->persist($document);
                $em->flush();
                $this->addFlash('success', 'Document uploaded successfully.');

                return $this->redirectToRoute('app_provider_documents');
            }
        }

        return $this->render('provider/document/index.html.twig', [
            'form' => $form->createView(),
            'documents' => $documentRepository->findBy(['user' => $user], ['createdAt' => 'DESC']),
            'documentRequests' => $documentRequestRepository->getDocumentRequests($user->getProvider()->getId()),
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
        $form = $this->createForm(DocumentType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $file */
            $file = $form->get('fileName')->getData();

            if ($file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the file name as part of the URL
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

            $em->flush();
            $this->addFlash('success', 'Document updated successfully.');

            return $this->redirectToRoute('app_provider_documents');
        }

        return $this->render('provider/document/index.html.twig', [
            'form' => $form->createView(),
            'documents' => $em->getRepository(Document::class)->findAll(),
            'editMode' => true,
            'editId' => $document->getId(),
            'documentRequests' => $documentRequestRepository->getDocumentRequests($user->getProvider()->getId()),
        ]);
    }

    #[Route('/documents/view/{id}', name: 'app_provider_document_view')]
    public function view(
        Document $document,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): Response
    {
        $user = $this->getUser();
        
        // Verify the document belongs to the current user
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

    #[Route('/document-request/{id}/assign-document', name: 'assign_document', methods: ['POST'])]
    public function assignDocument(
        DocumentRequest $documentRequest,
        Request $request,
        EntityManagerInterface $em,
        DocumentRepository $documentRepo,
        EventDispatcherInterface $dispatcher,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $documentId = $data['documentId'] ?? null;

        if (!$documentId) {
            return new JsonResponse(['success' => false, 'message' => 'Document ID missing.'], 400);
        }

        $document = $documentRepo->find($documentId);
        if (!$document) {
            return new JsonResponse(['success' => false, 'message' => 'Document not found.'], 404);
        }

        $documentRequest->setDocument($document);
        $documentRequest->setProvidedAt(new \DateTime());

        $em->flush();

        $dispatcher->dispatch(new ApplicationEvent($documentRequest->getApplication()), ApplicationEvent::APPLICATION_DOCUMENT_PROVIDED);

        return new JsonResponse([
            'success' => true,
            'providedAtFormatted' => $documentRequest->getProvidedAt()->format('m/d/Y')
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

                // Move file to documents directory
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
