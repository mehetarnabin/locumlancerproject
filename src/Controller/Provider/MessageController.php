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
        $offset = $request->query->get('page', 1);
        $perPage = $request->get('per_page', 10);
        $filters = [];
        $filters['keyword'] = $request->query->get('keyword');
        
        if($type == 'inbox') {
            $filters = [
                'receiver' => $user->getId()
            ];
        }
        if($type == 'sent') {
            $filters = [
                'sender' => $user->getId()
            ];
        }
        // Handle drafts
        if($type == 'drafts') {
            $filters = [
                'sender' => $user->getId(),
                'drafts_only' => true
            ];
        }
        // Handle trash
        if($type == 'trash') {
            $filters = [
                'deleted' => true,
                'user' => $user->getId()
            ];
        }

        $messages = $em->getRepository(Message::class)->getAll($offset, $perPage, $filters);

        // Get draft count for badge
        $draftCount = $em->getRepository(Message::class)->getDraftCount($user);
        
        // ADD THIS: Get trash count for badge
        $trashCount = $em->getRepository(Message::class)->getTrashCount($user);

        // Get employers for compose modal
        $employers = $em->getRepository(User::class)->getEmployersForMessage($user->getProvider()->getId());

        return $this->render('provider/message/index.html.twig', [
            'messages' => $messages,
            'draft_count' => $draftCount,
            'trash_count' => $trashCount, // ADD THIS LINE
            'current_type' => $type,
            'employers' => $employers,
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
            $receiverId = $request->get('receiver');
            $text = $request->get('message');
            $saveAsDraft = $request->get('save_as_draft', false);
            $draftId = $request->get('draft_id');

            // For drafts, only text is required
            if(!$saveAsDraft && (empty($receiverId) || empty(trim($text)))) {
                $this->addFlash('error', 'Receiver and message are required');
                return $this->redirectToRoute('app_provider_messages');
            }

            // For saving as draft, find existing or create new
            if ($draftId) {
                $message = $em->getRepository(Message::class)->findDraft($draftId, $user);
                if (!$message) {
                    $this->addFlash('error', 'Draft not found');
                    return $this->redirectToRoute('app_provider_messages');
                }
            } else {
                $message = new Message();
            }

            if ($receiverId) {
                $employerUser = $em->getRepository(User::class)->find($receiverId);
                if ($employerUser) {
                    $employer = $employerUser->getEmployer();
                    $message->setEmployer($employer);
                    $message->setReceiver($employerUser);
                }
            }

            $message->setSender($user);
            $message->setText($text);

            // Handle draft vs send
            if ($saveAsDraft) {
                $message->setIsDraft(true);
                $successMessage = 'Message saved as draft successfully';
                $redirectParams = ['type' => 'drafts'];
            } else {
                $message->setIsDraft(false);
                $successMessage = 'Message has been sent successfully';
                $redirectParams = [];
                
                // Only dispatch event for actual sent messages
                $dispatcher->dispatch(new MessageEvent($message), MessageEvent::MESSAGE_CREATED);
            }

            // Handle file upload
            if($request->files->get('attachment')) {
                $file = $request->files->get('attachment');

                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

                try {
                    $file->move($uploadDirectory. '/' . $user->getId(), $newFilename);
                    $message->setAttachment($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('warning', 'Message saved but file upload failed');
                }
            }

            $em->persist($message);
            $em->flush();

            $this->addFlash('success', $successMessage);
            return $this->redirectToRoute('app_provider_messages', $redirectParams);
        }

        $employers = $em->getRepository(User::class)->getEmployersForMessage($user->getProvider()->getId());

        return $this->render('provider/message/new.html.twig', [
            'employers' => $employers,
        ]);
    }

    // NEW: Save draft via AJAX (for auto-saving)
    #[Route('/messages/draft/save', name: 'app_provider_draft_save', methods: ['POST'])]
    public function saveDraft(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): JsonResponse {
        $user = $this->getUser();
        
        $data = json_decode($request->getContent(), true);
        $draftId = $data['id'] ?? null;
        $receiverId = $data['receiver_id'] ?? null;
        $messageText = $data['message'] ?? '';

        // If no content, don't save
        if (empty(trim($messageText))) {
            return new JsonResponse(['success' => true, 'empty' => true]);
        }

        // Find existing draft or create new one
        if ($draftId) {
            $message = $em->getRepository(Message::class)->findDraft($draftId, $user);
            if (!$message) {
                return new JsonResponse(['error' => 'Draft not found'], 404);
            }
        } else {
            $message = new Message();
            $message->setSender($user);
            $message->setIsDraft(true);
        }

        // Set receiver if provided
        if ($receiverId) {
            $receiver = $em->getRepository(User::class)->find($receiverId);
            if ($receiver) {
                $message->setReceiver($receiver);
                $message->setEmployer($receiver->getEmployer());
            }
        }

        $message->setText($messageText);
        $message->setSavedAt(new \DateTime());

        $em->persist($message);
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'draft' => [
                'id' => $message->getId(),
                'receiver_id' => $message->getReceiver() ? $message->getReceiver()->getId() : null,
                'message' => $message->getText(),
                'savedAt' => $message->getSavedAt()->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    // NEW: Load draft via AJAX
    #[Route('/messages/draft/{id}', name: 'app_provider_draft_load', methods: ['GET'])]
    public function loadDraft(Message $message, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        
        // Security check - user can only load their own drafts
        if ($message->getSender()->getId() !== $user->getId() || !$message->isDraft()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        return new JsonResponse([
            'success' => true,
            'draft' => [
                'id' => $message->getId(),
                'receiver_id' => $message->getReceiver() ? $message->getReceiver()->getId() : null,
                'message' => $message->getText(),
                'attachment' => $message->getAttachment(),
                'savedAt' => $message->getSavedAt()->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    // NEW: Send draft (convert draft to actual message)
    #[Route('/messages/draft/{id}/send', name: 'app_provider_draft_send', methods: ['POST'])]
    public function sendDraft(
        Message $message,
        Request $request,
        EntityManagerInterface $em,
        EventDispatcherInterface $dispatcher,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): JsonResponse {
        $user = $this->getUser();
        
        // Security check
        if ($message->getSender()->getId() !== $user->getId() || !$message->isDraft()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        // Check if draft can be sent
        if (!$message->canBeSent()) {
            return new JsonResponse(['error' => 'Cannot send draft: receiver and message are required'], 400);
        }

        // Handle file upload if provided in the request
        if ($request->files->get('attachment')) {
            $file = $request->files->get('attachment');
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

            try {
                $file->move($uploadDirectory. '/' . $user->getId(), $newFilename);
                $message->setAttachment($newFilename);
            } catch (FileException $e) {
                // Continue without attachment if upload fails
            }
        }

        // Convert draft to actual message
        $message->send();

        $em->persist($message);
        $em->flush();

        // Dispatch message event
        $dispatcher->dispatch(new MessageEvent($message), MessageEvent::MESSAGE_CREATED);

        return new JsonResponse([
            'success' => true,
            'message' => 'Draft sent successfully',
            'message_id' => $message->getId()
        ]);
    }

    // NEW: Delete draft
    #[Route('/messages/draft/{id}', name: 'app_provider_draft_delete', methods: ['DELETE'])]
    public function deleteDraft(Message $message, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        
        // Security check
        if ($message->getSender()->getId() !== $user->getId() || !$message->isDraft()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $em->remove($message);
        $em->flush();

        return new JsonResponse([
            'success' => true, 
            'message' => 'Draft deleted successfully'
        ]);
    }

    // NEW: Get draft count for badge
    #[Route('/messages/drafts/count', name: 'app_provider_drafts_count', methods: ['GET'])]
    public function getDraftCount(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $count = $em->getRepository(Message::class)->getDraftCount($user);

        return new JsonResponse(['count' => $count]);
    }

    // Your existing methods...
    #[Route('/{id}/show', name: 'app_provider_message_show', methods: ['GET'])]
    public function show(Message $message, EntityManagerInterface $em)
    {
        return $this->render('provider/message/show.html.twig', [
            'message' => $message,
            'replies' => $em->getRepository(Message::class)->findBy(['parent' => $message]),
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

        $reply = new Message();
        $reply->setParent($message);
        $reply->setEmployer($message->getEmployer());
        $reply->setSender($user);
        $reply->setReceiver($message->getSender());
        $reply->setText($request->request->get('message'));

        if ($file = $request->files->get('attachment')) {
            $safeFilename = $slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

            try {
                $file->move($uploadDirectory . '/' . $user->getId(), $newFilename);
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
            'replyHtml' => $this->renderView('provider/message/_reply_item.html.twig', ['reply' => $reply])
        ]);
    }

    #[Route('/{id}/delete', name: 'app_provider_message_delete', methods: ['GET'])]
    public function delete(Message $message, EntityManagerInterface $entityManager)
    {
        // Instead of removing, move to trash
        $message->setDeleted(true);
        $message->setDeletedAt(new \DateTime());
        
        $entityManager->persist($message);
        $entityManager->flush();

        $this->addFlash('success', 'Message has been moved to trash');
        return $this->redirectToRoute('app_provider_messages');
    }

    // NEW: Get trash count for badge
    #[Route('/messages/trash/count', name: 'app_provider_trash_count', methods: ['GET'])]
    public function getTrashCount(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $count = $em->getRepository(Message::class)->getTrashCount($user);

        return new JsonResponse(['count' => $count]);
    }

    // NEW: Restore message from trash
    #[Route('/messages/restore/{id}', name: 'app_provider_message_restore', methods: ['POST'])]
    public function restoreMessage(Message $message, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        
        // Security check - user can only restore their own messages
        if ($message->getSender()->getId() !== $user->getId() && $message->getReceiver()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $message->setDeleted(false);
        $message->setDeletedAt(null);
        
        $em->persist($message);
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Message restored successfully'
        ]);
    }

    // NEW: Permanently delete message
    #[Route('/messages/permanent-delete/{id}', name: 'app_provider_message_permanent_delete', methods: ['DELETE'])]
    public function permanentDelete(Message $message, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        
        // Security check - user can only delete their own messages
        if ($message->getSender()->getId() !== $user->getId() && $message->getReceiver()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $em->remove($message);
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Message permanently deleted'
        ]);
    }

    // NEW: Empty trash
    #[Route('/messages/empty-trash', name: 'app_provider_empty_trash', methods: ['DELETE'])]
    public function emptyTrash(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        
        $messages = $em->getRepository(Message::class)->findBy([
            'deleted' => true,
            'user' => $user->getId()
        ]);

        foreach ($messages as $message) {
            $em->remove($message);
        }
        
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Trash emptied successfully',
            'deleted_count' => count($messages)
        ]);
    }
}