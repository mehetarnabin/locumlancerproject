<?php

namespace App\Controller\Provider;

use App\Entity\Employer;
use App\Entity\Message;
use App\Entity\User;
use App\Event\MessageEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/provider')]
class MessageController extends AbstractController
{
    #[Route('/messages', name: 'app_provider_messages')]
    public function index(Request $request, EntityManagerInterface $em)
    {
        $user = $this->getUser();
        $type = $request->query->get('type', 'inbox');
        $page = $request->query->get('page', 1);
        $perPage = $request->query->get('per_page', 10);
        $filters['keyword'] = $request->query->get('keyword');
        
        if($type == 'inbox') {
            $filters = [
                'receiver' => $user->getId(),
                'type' => 'inbox'
            ];
        }
        if($type == 'sent') {
            $filters = [
                'sender' => $user->getId(),
                'type' => 'sent'
            ];
        }

        $messages = $em->getRepository(Message::class)->getAll($page, $perPage, $filters);

        return $this->render('provider/message/index.html.twig', [
            'messages' => $messages,
            'current_type' => $type,
        ]);
    }

    #[Route('/messages/new', name: 'app_provider_messages_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        EventDispatcherInterface $dispatcher,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    )
    {
        $user = $this->getUser();

        if($request->isMethod('POST')) {
            $receiverId = $request->request->get('receiver');
            $text = $request->request->get('message');

            // Remove debug logging from production code
            // error_log('Receiver ID: ' . $request->request->get('receiver'));
            // error_log('Message: ' . $request->request->get('message'));
            // error_log('Files: ' . print_r($request->files->all(), true));

            if(empty($receiverId) || empty(trim($text))) {
                $this->addFlash('error', 'Receiver and message are required');
                return $this->redirectToRoute('app_provider_messages_new');
            }

            $message = new Message();
            $employerUser = $em->getRepository(User::class)->find($receiverId);
            
            if (!$employerUser) {
                $this->addFlash('error', 'Invalid receiver selected');
                return $this->redirectToRoute('app_provider_messages_new');
            }
            
            $employer = $employerUser->getEmployer();

            $message->setEmployer($employer);
            $message->setReceiver($employerUser);
            $message->setSender($user);
            $message->setText($text);
            $message->markAsSent(); // Mark as sent message

            if($request->files->get('attachment')) {
                $file = $request->files->get('attachment');

                // Validate file
                $allowedMimeTypes = [
                    'image/jpeg', 
                    'image/png', 
                    'image/gif', 
                    'application/pdf', 
                    'application/msword', 
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                ];
                $maxFileSize = 5 * 1024 * 1024; // 5MB
                
                if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
                    $this->addFlash('error', 'Invalid file type. Allowed: images, PDF, Word documents');
                    return $this->redirectToRoute('app_provider_messages_new');
                }
                
                if ($file->getSize() > $maxFileSize) {
                    $this->addFlash('error', 'File too large. Maximum size: 5MB');
                    return $this->redirectToRoute('app_provider_messages_new');
                }

                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

                // Create user directory if it doesn't exist
                $userUploadDir = $uploadDirectory . '/' . $user->getId();
                if (!is_dir($userUploadDir)) {
                    mkdir($userUploadDir, 0755, true);
                }

                try {
                    $file->move($userUploadDir, $newFilename);
                    $message->setAttachment($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload file');
                    // Continue without attachment rather than failing completely
                }
            }

            $em->persist($message);
            $em->flush();

            $dispatcher->dispatch(new MessageEvent($message), MessageEvent::MESSAGE_CREATED);

            $this->addFlash('success', 'Message has been sent successfully');
            return $this->redirectToRoute('app_provider_messages', ['type' => 'sent']);
        }

        $employers = $em->getRepository(User::class)->getEmployersForMessage($user->getProvider()->getId());

        return $this->render('provider/message/new.html.twig', [
            'employers' => $employers,
        ]);
    }

    #[Route('/{id}/show', name: 'app_provider_message_show', methods: ['GET'])]
    public function show(Message $message, EntityManagerInterface $em)
    {
        $user = $this->getUser();
        
        // Authorization check
        if ($message->getSender() !== $user && $message->getReceiver() !== $user) {
            throw $this->createAccessDeniedException('You cannot view this message');
        }

        // Mark as read if user is receiver
        if ($message->getReceiver() === $user && !$message->isSeen()) {
            $message->setSeen(true);
            $em->flush();
        }

        return $this->render('provider/message/show.html.twig', [
            'message' => $message,
            'replies' => $em->getRepository(Message::class)->findBy(['parent' => $message], ['createdAt' => 'ASC']),
        ]);
    }

    #[Route('/messages/{id}/reply', name: 'app_provider_message_reply_ajax', methods: ['POST'])]
    public function ajaxReply(
        Message $message,
        Request $request,
        EntityManagerInterface $em,
        EventDispatcherInterface $dispatcher,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): JsonResponse {
        $user = $this->getUser();

        // Authorization check
        if ($message->getSender() !== $user && $message->getReceiver() !== $user) {
            return new JsonResponse(['error' => 'Access denied.'], 403);
        }

        $replyText = $request->request->get('message');
        if (empty(trim($replyText))) {
            return new JsonResponse(['error' => 'Message cannot be empty.'], 400);
        }

        $reply = new Message();
        $reply->setParent($message);
        $reply->setEmployer($message->getEmployer());
        $reply->setSender($user);
        $reply->setReceiver($message->getSender() === $user ? $message->getReceiver() : $message->getSender());
        $reply->setText($replyText);
        $reply->markAsSent(); // Mark reply as sent

        if ($file = $request->files->get('attachment')) {
            // Validate file for reply
            $allowedMimeTypes = [
                'image/jpeg', 
                'image/png', 
                'image/gif', 
                'application/pdf', 
                'application/msword', 
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];
            $maxFileSize = 5 * 1024 * 1024;

            if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
                return new JsonResponse(['error' => 'Invalid file type. Allowed: images, PDF, Word documents'], 400);
            }

            if ($file->getSize() > $maxFileSize) {
                return new JsonResponse(['error' => 'File too large. Maximum size: 5MB'], 400);
            }

            $safeFilename = $slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

            // Create user directory if it doesn't exist
            $userUploadDir = $uploadDirectory . '/' . $user->getId();
            if (!is_dir($userUploadDir)) {
                mkdir($userUploadDir, 0755, true);
            }

            try {
                $file->move($userUploadDir, $newFilename);
                $reply->setAttachment($newFilename);
            } catch (FileException $e) {
                return new JsonResponse(['error' => 'File upload failed.'], 500);
            }
        }

        $em->persist($reply);
        $em->flush();

        $dispatcher->dispatch(new MessageEvent($reply), MessageEvent::MESSAGE_CREATED);

        return new JsonResponse([
            'success' => true,
            'replyId' => $reply->getId(),
            'replyHtml' => $this->renderView('provider/message/_reply_item.html.twig', ['reply' => $reply])
        ]);
    }

    #[Route('/{id}/delete', name: 'app_provider_message_delete', methods: ['POST'])]
    public function delete(Message $message, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        
        // Authorization check
        if ($message->getSender() !== $user && $message->getReceiver() !== $user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'You can only delete your own messages'
            ], 403);
        }

        // CSRF protection
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete'.$message->getId(), $submittedToken)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid security token'
            ], 400);
        }

        try {
            $messageId = $message->getId();
            $entityManager->remove($message);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Message deleted successfully'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to delete message'
            ], 500);
        }
    }

    #[Route('/{id}/mark-read', name: 'app_provider_message_mark_read', methods: ['POST'])]
    public function markAsRead(Message $message, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        
        if ($message->getReceiver() === $user && !$message->isSeen()) {
            $message->setSeen(true);
            $em->flush();
        }
        
        return new JsonResponse(['success' => true]);
    }

    #[Route('/{id}/download', name: 'app_provider_message_download', methods: ['GET'])]
    public function downloadAttachment(Message $message, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        
        // Check if user has permission to download this attachment
        if ($message->getSender() !== $user && $message->getReceiver() !== $user) {
            throw $this->createAccessDeniedException('You cannot download this attachment');
        }
        
        if (!$message->getAttachment()) {
            throw $this->createNotFoundException('Attachment not found');
        }
        
        $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads';
        $filePath = $uploadDirectory . '/' . $message->getSender()->getId() . '/' . $message->getAttachment();
        
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('File not found');
        }
        
        $response = new Response(file_get_contents($filePath));
        $response->headers->set('Content-Type', mime_content_type($filePath) ?: 'application/octet-stream');
        $response->headers->set('Content-Disposition', 
            'attachment; filename="' . $message->getAttachment() . '"');
        
        return $response;
    }
}