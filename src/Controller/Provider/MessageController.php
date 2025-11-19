<?php

namespace App\Controller\Provider;

use App\Entity\Employer;
use App\Entity\Message;
use App\Entity\Provider;
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
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[Route('/provider')]
class MessageController extends AbstractController
{
    
    #[Route('/messages', name: 'app_provider_messages')]
    public function index(Request $request, EntityManagerInterface $em)
    {
        $user = $this->getUser();
        $type = $request->query->get('type', 'inbox');
        $page = $request->query->get('page', 1);
        $perPage = 10;
        $offset = ($page - 1) * $perPage;
        $filters = [];
        $filters['keyword'] = $request->query->get('keyword');
        
        // CRITICAL FIX: Proper filtering based on type
        switch ($type) {
            case 'inbox':
                $filters['receiver'] = $user->getId();
                $filters['deleted'] = false; // Don't show deleted messages in inbox
                break;
            case 'sent':
                $filters['sender'] = $user->getId();
                $filters['drafts_only'] = false;
                $filters['deleted'] = false; // Don't show deleted messages in sent
                break;
            case 'drafts':
                $filters['sender'] = $user->getId();
                $filters['drafts_only'] = true;
                $filters['deleted'] = false; // Don't show deleted drafts
                break;
            case 'trash':
                $filters['deleted'] = true;
                $filters['user'] = $user->getId();
                break;
            default:
                $filters['receiver'] = $user->getId();
                $filters['deleted'] = false;
                break;
        }

        // Get messages with pagination
        $messages = $em->getRepository(Message::class)->getAll($offset, $perPage, $filters);
        
        // Get total count for pagination
        $totalMessages = $em->getRepository(Message::class)->getCount($filters);
        $totalPages = ceil($totalMessages / $perPage);

        // Get counts for badges
        $draftCount = $em->getRepository(Message::class)->getDraftCount($user);
        $trashCount = $em->getRepository(Message::class)->getTrashCount($user);

        // Get employers for compose modal
        $employers = $em->getRepository(User::class)->getEmployersForMessage($user->getProvider()->getId());

        return $this->render('provider/message/index.html.twig', [
            'messages' => $messages,
            'draft_count' => $draftCount,
            'trash_count' => $trashCount,
            'current_type' => $type,
            'employers' => $employers,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'per_page' => $perPage,
                'total_items' => $totalMessages,
                'has_previous' => $page > 1,
                'has_next' => $page < $totalPages,
                'previous_page' => $page - 1,
                'next_page' => $page + 1,
            ]
        ]);
    }
    
    #[Route('/messages/new', name: 'app_provider_messages_new')]
    public function new(
        Request $request, 
        EntityManagerInterface $em,
        EventDispatcherInterface $dispatcher,
        MailerInterface $mailer,
        SluggerInterface $slugger,
        #[Autowire('%messages_attachments_directory%')] string $uploadDirectory
    ) {
        $user = $this->getUser();
        
        // Find provider by user relationship
        $provider = $em->getRepository(Provider::class)->findOneBy(['user' => $user]);
        
        if (!$provider) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'No provider profile found'], 400);
            }
            $this->addFlash('error', 'Please complete your provider profile before sending messages.');
            return $this->redirectToRoute('app_provider_profile');
        }
        
        // Get employers
        try {
            $employers = $em->getRepository(User::class)->getEmployersForMessage($provider->getId());
        } catch (\Exception $e) {
            $employers = $em->getRepository(User::class)->createQueryBuilder('u')
                ->andWhere('u.userType = :userType')
                ->setParameter('userType', User::TYPE_EMPLOYER)
                ->getQuery()
                ->getResult();
        }

        // Handle POST requests
        if ($request->isMethod('POST')) {
            error_log("=== MESSAGE SUBMISSION DEBUG ===");
            error_log("Receiver ID: " . $request->get('receiver'));
            error_log("Subject: " . $request->get('subject'));
            error_log("Message Text: " . $request->get('message'));
            error_log("Save as Draft: " . ($request->get('save_as_draft') ? 'YES' : 'NO'));
            error_log("Draft ID: " . $request->get('draft_id'));
            
            $receiverId = $request->get('receiver');
            $subject = $request->get('subject');
            $text = $request->get('message');
            $saveAsDraft = (bool) $request->get('save_as_draft', false);
            $draftId = $request->get('draft_id');

            // CRITICAL FIX: Clear logic for draft vs send
            $isDraftAction = $saveAsDraft;

            // VALIDATION: Different rules for sending vs drafting
            if (!$isDraftAction) {
                // For sending, both receiver and text are required
                if (empty($receiverId) || empty(trim($text))) {
                    error_log("❌ SEND VALIDATION FAILED: Receiver or message empty");
                    $this->addFlash('error', 'Receiver and message are required to send');
                    return $this->redirectToRoute('app_provider_messages');
                }
            } else {
                // For drafts, only text is required
                if (empty(trim($text))) {
                    error_log("❌ DRAFT VALIDATION FAILED: Message text is empty");
                    $this->addFlash('error', 'Message text is required for draft');
                    return $this->redirectToRoute('app_provider_messages');
                }
            }

            // Find existing draft or create new message
            if ($draftId) {
                $message = $em->getRepository(Message::class)->findDraft($draftId, $user);
                if (!$message) {
                    error_log("❌ DRAFT NOT FOUND: " . $draftId);
                    $this->addFlash('error', 'Draft not found');
                    return $this->redirectToRoute('app_provider_messages');
                }
                error_log("✅ LOADED EXISTING DRAFT: " . $draftId);
            } else {
                $message = new Message();
                $message->setSender($user);
                error_log("✅ CREATING NEW MESSAGE");
            }

            // Set receiver if provided (can be empty for drafts)
            if ($receiverId) {
                $employerUser = $em->getRepository(User::class)->find($receiverId);
                error_log("🔍 RECEIVER USER: " . ($employerUser ? $employerUser->getId() : 'NOT FOUND'));
                
                if ($employerUser) {
                    $employer = $employerUser->getEmployer();
                    error_log("🔍 EMPLOYER: " . ($employer ? $employer->getId() : 'NO EMPLOYER'));
                    
                    $message->setEmployer($employer);
                    $message->setReceiver($employerUser);
                    error_log("✅ RECEIVER SET: " . $employerUser->getEmail());
                } else {
                    error_log("❌ RECEIVER USER NOT FOUND WITH ID: " . $receiverId);
                    if (!$isDraftAction) {
                        $this->addFlash('error', 'Receiver not found');
                        return $this->redirectToRoute('app_provider_messages');
                    }
                }
            } else {
                error_log("ℹ️ NO RECEIVER SET - THIS IS OK FOR DRAFTS");
            }

            // Set subject and text
            $message->setSubject($subject);
            $message->setText($text);

            // Handle file upload BEFORE deciding to send or save draft
            if($request->files->get('attachment')) {
                $file = $request->files->get('attachment');
                error_log("📎 ATTACHMENT FOUND: " . $file->getClientOriginalName());

                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

                try {
                    // Ensure directory exists
                    if (!is_dir($uploadDirectory)) {
                        mkdir($uploadDirectory, 0755, true);
                        error_log("📁 CREATED MESSAGES UPLOAD DIRECTORY: " . $uploadDirectory);
                    }
                    
                    $file->move($uploadDirectory, $newFilename);
                    $message->setAttachment($newFilename);
                    error_log("✅ ATTACHMENT UPLOADED: " . $newFilename);
                    error_log("📁 STORED AT: " . $uploadDirectory . '/' . $newFilename);
                    
                    // Verify the file was actually saved
                    if (file_exists($uploadDirectory . '/' . $newFilename)) {
                        error_log("✅ FILE VERIFICATION: File exists after upload");
                    } else {
                        error_log("❌ FILE VERIFICATION: File NOT found after upload!");
                    }
                } catch (FileException $e) {
                    error_log("❌ ATTACHMENT UPLOAD FAILED: " . $e->getMessage());
                    $this->addFlash('warning', 'Message saved but file upload failed');
                }
            } else {
                error_log("📎 NO ATTACHMENT FILE IN REQUEST");
            }

            // CRITICAL FIX: Clear draft/sent logic
            if ($isDraftAction) {
                // SAVE AS DRAFT
                $message->setIsDraft(true);
                $message->setSavedAt(new \DateTime());
                $message->setSeen(true); // Drafts are always "seen" by sender
                $successMessage = 'Message saved as draft successfully';
                $redirectParams = ['type' => 'drafts'];
                error_log("💾 SAVING AS DRAFT");
            } else {
                // SEND MESSAGE
                $message->setIsDraft(false);
                $message->setSentAt(new \DateTime());
                $message->setSeen(false); // Sent messages start as unread for receiver
                $successMessage = 'Message has been sent successfully';
                $redirectParams = ['type' => 'sent'];
                
                error_log("📤 SENDING MESSAGE");
                error_log("📧 RECEIVER EMAIL: " . ($message->getReceiver() ? $message->getReceiver()->getEmail() : 'NO EMAIL'));
                
                // Only dispatch event and send email for actual sent messages
                if ($message->getReceiver()) {
                    $dispatcher->dispatch(new MessageEvent($message), MessageEvent::MESSAGE_CREATED);
                    
                    // ✅ DEBUG: Check if attachment exists before sending email
                    if ($message->getAttachment()) {
                        $filePath = $uploadDirectory . '/' . $message->getAttachment();
                        
                        error_log("🔍 PRE-EMAIL ATTACHMENT CHECK:");
                        error_log("  - Attachment: " . $message->getAttachment());
                        error_log("  - File path: " . $filePath);
                        error_log("  - Exists: " . (file_exists($filePath) ? 'YES' : 'NO'));
                        error_log("  - Readable: " . (is_readable($filePath) ? 'YES' : 'NO'));
                        
                        if (file_exists($filePath)) {
                            $originalFilename = $this->getOriginalFilename($message->getAttachment());
                            error_log("  - Original filename: " . $originalFilename);
                            error_log("  - File size: " . filesize($filePath) . " bytes");
                        }
                    } else {
                        error_log("ℹ️ NO ATTACHMENT TO CHECK");
                    }
                    
                    $this->sendEmailToReceiver($message, $mailer);
                } else {
                    error_log("⚠️ NO RECEIVER - CANNOT SEND EMAIL");
                }
            }


            $em->persist($message);
            $em->flush();
            error_log("✅ MESSAGE SAVED TO DATABASE WITH ID: " . $message->getId());
            error_log("🎯 FINAL DRAFT STATUS: " . ($message->isDraft() ? 'YES (Draft)' : 'NO (Sent)'));
            error_log("🎯 FINAL RECEIVER: " . ($message->getReceiver() ? $message->getReceiver()->getEmail() : 'NONE'));
            error_log("🎯 FINAL ATTACHMENT: " . ($message->getAttachment() ? $message->getAttachment() : 'NONE'));

            $this->addFlash('success', $successMessage);
            return $this->redirectToRoute('app_provider_messages', $redirectParams);
        }

        return $this->render('provider/message/new.html.twig', [
            'employers' => $employers,
        ]);
    }

    // Save draft via AJAX (manual saving only)
    #[Route('/messages/draft/save', name: 'app_provider_draft_save', methods: ['POST'])]
    public function saveDraft(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUser();
        
        $data = json_decode($request->getContent(), true);
        $draftId = $data['id'] ?? null;
        $receiverId = $data['receiver_id'] ?? null;
        $subject = $data['subject'] ?? '';
        $messageText = $data['message'] ?? '';

        // If no content, don't save
        if (empty(trim($messageText)) && empty(trim($subject)) && empty($receiverId)) {
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

        // Set subject and text
        $message->setSubject($subject);
        $message->setText($messageText);
        $message->setSavedAt(new \DateTime());
        $message->setSeen(true); // Drafts are always "seen" by sender

        $em->persist($message);
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'draft' => [
                'id' => $message->getId(),
                'receiver_id' => $message->getReceiver() ? $message->getReceiver()->getId() : null,
                'subject' => $message->getSubject(),
                'message' => $message->getText(),
                'savedAt' => $message->getSavedAt()->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    // Load draft via AJAX
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
                'subject' => $message->getSubject(),
                'message' => $message->getText(),
                'attachment' => $message->getAttachment(),
                'savedAt' => $message->getSavedAt()->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    // Delete draft
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

    // Get draft count for badge
    #[Route('/messages/drafts/count', name: 'app_provider_drafts_count', methods: ['GET'])]
    public function getDraftCount(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $count = $em->getRepository(Message::class)->getDraftCount($user);

        return new JsonResponse(['count' => $count]);
    }

    #[Route('/message/{id}/show', name: 'app_provider_message_show', methods: ['GET'])]
    public function show(Message $message, EntityManagerInterface $em)
    {
        $user = $this->getUser();
        
        // Security check - user can only view their own messages
        if ($message->getSender()->getId() !== $user->getId() && $message->getReceiver()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You cannot access this message.');
        }

        // Mark as read if receiver is viewing
        if ($message->getReceiver() && $message->getReceiver()->getId() === $user->getId() && !$message->isSeen()) {
            $message->setSeen(true);
            $em->persist($message);
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
        #[Autowire('%messages_attachments_directory%')] string $uploadDirectory
    ): JsonResponse {
        $user = $this->getUser();

        // Security check
        if ($message->getSender()->getId() !== $user->getId() && $message->getReceiver()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $reply = new Message();
        $reply->setParent($message);
        $reply->setEmployer($message->getEmployer());
        $reply->setSender($user);
        
        // Set receiver - if user is sender, reply goes to original receiver, and vice versa
        if ($message->getSender()->getId() === $user->getId()) {
            $reply->setReceiver($message->getReceiver());
        } else {
            $reply->setReceiver($message->getSender());
        }
        
        $reply->setText($request->request->get('message'));

        if ($file = $request->files->get('attachment')) {
            $safeFilename = $slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

            try {
                $file->move($uploadDirectory, $newFilename);
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

    #[Route('/message/{id}/delete', name: 'app_provider_message_delete', methods: ['POST', 'DELETE'])]
    public function delete(Message $message, EntityManagerInterface $entityManager, Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        // Security check - user can only delete their own messages
        if ($message->getSender()->getId() !== $user->getId() && $message->getReceiver()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        try {
            // Instead of removing, move to trash
            $message->setDeleted(true);
            $message->setDeletedAt(new \DateTime());
            
            $entityManager->persist($message);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Message has been moved to trash'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to delete message: ' . $e->getMessage()
            ], 500);
        }
    }

    // Get trash count for badge
    #[Route('/messages/trash/count', name: 'app_provider_trash_count', methods: ['GET'])]
    public function getTrashCount(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $count = $em->getRepository(Message::class)->getTrashCount($user);

        return new JsonResponse(['count' => $count]);
    }

    // Restore message from trash
    #[Route('/messages/restore/{id}', name: 'app_provider_message_restore', methods: ['POST'])]
    public function restoreMessage(Message $message, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        
        // Security check - user can only restore their own messages
        if ($message->getSender()->getId() !== $user->getId() && $message->getReceiver()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        // Additional check: message must be in trash
        if (!$message->isDeleted()) {
            return new JsonResponse(['error' => 'Message is not in trash'], 400);
        }

        try {
            $message->setDeleted(false);
            $message->setDeletedAt(null);
            
            $em->persist($message);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Message restored successfully'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to restore message: ' . $e->getMessage()
            ], 500);
        }
    }

    // Permanently delete message from trash
    #[Route('/messages/permanent-delete/{id}', name: 'app_provider_message_permanent_delete', methods: ['DELETE'])]
    public function permanentDelete(Message $message, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        
        // Security check - user can only delete their own messages
        if ($message->getSender()->getId() !== $user->getId() && $message->getReceiver()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        // Additional check: message must be in trash
        if (!$message->isDeleted()) {
            return new JsonResponse(['error' => 'Message is not in trash'], 400);
        }

        try {
            $em->remove($message);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Message permanently deleted'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to permanently delete message: ' . $e->getMessage()
            ], 500);
        }
    }

    // Empty trash
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

    // Mark message as read
    #[Route('/message/{id}/mark-read', name: 'app_provider_message_mark_read', methods: ['POST'])]
    public function markAsRead(Message $message, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        
        // Security check - user can only mark their own received messages as read
        if ($message->getReceiver()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $message->setSeen(true);
        $em->persist($message);
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Message marked as read'
        ]);
    }

    // FORWARD FUNCTIONALITY - RENAMED TO AVOID CONFLICT
    #[Route('/messages/{id}/forward-message', name: 'app_provider_message_forward', methods: ['GET', 'POST'])]
    public function forwardMessage(
        Message $message,
        Request $request,
        EntityManagerInterface $em,
        EventDispatcherInterface $dispatcher,
        MailerInterface $mailer,
        SluggerInterface $slugger,
        #[Autowire('%messages_attachments_directory%')] string $uploadDirectory
    ) {
        $user = $this->getUser();
        
        // Security check - user can only forward messages they have access to
        if ($message->getSender()->getId() !== $user->getId() && $message->getReceiver()->getId() !== $user->getId()) {
            $this->addFlash('error', 'You cannot forward this message.');
            return $this->redirectToRoute('app_provider_messages');
        }

        // Get employers for the forward modal
        $employers = $em->getRepository(User::class)->getEmployersForMessage($user->getProvider()->getId());

        // Handle POST request (actual forwarding)
        if ($request->isMethod('POST')) {
            $receiverId = $request->get('receiver');
            $forwardText = $request->get('forward_message');
            $subject = $request->get('subject');

            // Validation
            if (empty($receiverId) || empty(trim($forwardText))) {
                $this->addFlash('error', 'Receiver and message are required to forward');
                return $this->redirectToRoute('app_provider_messages');
            }

            // Create forwarded message
            $forwardedMessage = new Message();
            $forwardedMessage->setSender($user);
            $forwardedMessage->setIsForwarded(true);
            $forwardedMessage->setOriginalSubject($message->getSubject());
            
            // Set receiver
            $receiverUser = $em->getRepository(User::class)->find($receiverId);
            if ($receiverUser) {
                $forwardedMessage->setReceiver($receiverUser);
                $forwardedMessage->setEmployer($receiverUser->getEmployer());
            } else {
                $this->addFlash('error', 'Receiver not found');
                return $this->redirectToRoute('app_provider_messages');
            }

            // Build the forwarded message content
            $forwardedContent = $this->buildForwardedContent($message, $forwardText);
            $forwardedMessage->setText($forwardedContent);
            $forwardedMessage->setSubject($subject ?: $this->buildForwardSubject($message->getSubject()));
            $forwardedMessage->setSentAt(new \DateTime());

            // Handle file upload for new attachment
            if ($request->files->get('attachment')) {
                $file = $request->files->get('attachment');
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

                try {
                    if (!is_dir($uploadDirectory)) {
                        mkdir($uploadDirectory, 0755, true);
                    }
                    
                    $file->move($uploadDirectory, $newFilename);
                    $forwardedMessage->setAttachment($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('warning', 'Message forwarded but file upload failed');
                }
            }

            $em->persist($forwardedMessage);
            $em->flush();

            // Send email notification
            $dispatcher->dispatch(new MessageEvent($forwardedMessage), MessageEvent::MESSAGE_CREATED);
            $this->sendEmailToReceiver($forwardedMessage, $mailer);

            $this->addFlash('success', 'Message has been forwarded successfully');
            return $this->redirectToRoute('app_provider_messages', ['type' => 'sent']);
        }

        // For GET request, show the forward form
        return $this->render('provider/message/forward.html.twig', [
            'message' => $message,
            'employers' => $employers,
        ]);
    }

    private function buildForwardedContent(Message $originalMessage, string $forwardText): string
    {
        $originalSender = $originalMessage->getSender()->getName() ?: $originalMessage->getSender()->getEmail();
        $originalDate = $originalMessage->getCreatedAt()->format('F j, Y \\a\\t g:i A');
        
        $content = "---------- Forwarded message ---------\n";
        $content .= "From: {$originalSender}\n";
        $content .= "Date: {$originalDate}\n";
        $content .= "Subject: {$originalMessage->getSubject()}\n";
        $content .= "To: " . ($originalMessage->getReceiver() ? $originalMessage->getReceiver()->getEmail() : 'Unknown') . "\n\n";
        $content .= $originalMessage->getText() . "\n\n";
        $content .= "----------\n\n";
        $content .= $forwardText;
        
        return $content;
    }

    private function buildForwardSubject(string $originalSubject): string
    {
        return "Fwd: " . $originalSubject;
    }

    private function sendEmailToReceiver(Message $message, MailerInterface $mailer): void
    {
        // Don't send emails for drafts
        if ($message->isDraft()) {
            error_log("🚫 NOT SENDING EMAIL - THIS IS A DRAFT");
            return;
        }
        
        try {
            error_log("📧📧📧 SEND EMAIL DEBUG START 📧📧📧");
            error_log("📧 Message ID: " . $message->getId());
            error_log("📧 Attachment from message: " . ($message->getAttachment() ?: 'NONE'));
            
            $receiver = $message->getReceiver();
            $sender = $message->getSender();
            
            if (!$receiver || !$receiver->getEmail()) {
                error_log("❌ No email found for receiver: " . ($receiver ? $receiver->getId() : 'null'));
                return;
            }
            
            $senderName = $sender->getName() ?: $sender->getEmail();
            $senderEmail = $sender->getEmail();
            
            if (!$senderEmail) {
                error_log("❌ Provider has no email: " . $sender->getId());
                return;
            }
            
            $subject = $message->getSubject() ?: "New message from {$senderName}";
            
            // Create the email
            $email = (new Email())
                ->from('notifications@locumlancer.com')
                ->replyTo($senderEmail)
                ->to($receiver->getEmail())
                ->subject($subject . ' - LocumLancer')
                ->html($this->getProviderMessageTemplate($message, $senderName, $senderEmail));

            // Attachment handling
            if ($message->getAttachment()) {
                $uploadDirectory = $this->getParameter('messages_attachments_directory');
                $filePath = $uploadDirectory . '/' . $message->getAttachment();
                $originalFilename = $this->getOriginalFilename($message->getAttachment());
                
                error_log("📧 ATTACHMENT DEBUG IN EMAIL SEND:");
                error_log("  - Attachment: " . $message->getAttachment());
                error_log("  - Full path: " . $filePath);
                error_log("  - File exists: " . (file_exists($filePath) ? 'YES' : 'NO'));
                error_log("  - File readable: " . (is_readable($filePath) ? 'YES' : 'NO'));
                
                if (file_exists($filePath) && is_readable($filePath)) {
                    try {
                        $email->attachFromPath($filePath, $originalFilename);
                        error_log("✅ FILE ATTACHED TO EMAIL: " . $originalFilename);
                    } catch (\Exception $e) {
                        error_log("❌ FAILED TO ATTACH FILE: " . $e->getMessage());
                    }
                } else {
                    error_log("❌ ATTACHMENT FILE NOT FOUND OR NOT READABLE: " . $filePath);
                }
            } else {
                error_log("ℹ️ NO ATTACHMENT TO ATTACH IN EMAIL SEND");
            }

            // Send the email
            $mailer->send($email);
            error_log("✅ EMAIL SENT TO: " . $receiver->getEmail());
            error_log("📧📧📧 SEND EMAIL DEBUG END 📧📧📧");
            
        } catch (\Exception $e) {
            error_log("❌ EMAIL SENDING FAILED: " . $e->getMessage());
        }
    }

    // Add this helper method to extract original filename
    private function getOriginalFilename(string $storedFilename): string
    {
        // Remove the unique ID part to get original filename
        // Format: original-name-uniqid.extension
        $parts = explode('-', $storedFilename);
        $extension = pathinfo($storedFilename, PATHINFO_EXTENSION);
        
        // Remove the last part (uniqid) and reconstruct
        array_pop($parts);
        $originalName = implode('-', $parts) . '.' . $extension;
        
        return $originalName;
    }

  private function getProviderMessageTemplate(Message $message, string $senderName, string $senderEmail): string
{
    $subject = $message->getSubject() ?: "New message";
    
    $attachmentHtml = '';
    $attachment = $message->getAttachment();
    
    // FORCE CHECK - Always show if file exists on disk
    if ($attachment) {
        $uploadDirectory = $this->getParameter('messages_attachments_directory');
        $filePath = $uploadDirectory . '/' . $attachment;
        
        if (file_exists($filePath)) {
            $originalFilename = $this->getOriginalFilename($attachment);
            $attachmentHtml = "
                <div style='background: #e8f5e9; padding: 12px; border-radius: 6px; margin: 10px 0; border-left: 4px solid #85BB65;'>
                    <strong>📎 Attachment:</strong> {$originalFilename}
                    <br>
                    <small style='color: #28a745;'>
                        The file is attached to this email.
                    </small>
                </div>
            ";
            error_log("✅✅✅ FORCING ATTACHMENT DISPLAY: " . $originalFilename);
        }
    }
    
    if (empty($attachmentHtml)) {
        $attachmentHtml = "
            <div style='background: #f8f9fa; padding: 12px; border-radius: 6px; margin: 10px 0; border-left: 4px solid #6c757d;'>
                <small style='color: #6c757d;'>No attachment included with this message.</small>
            </div>
        ";
    }
    
    // Rest of template remains the same...

        // Rest of your template code remains the same...
        return "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f6f6f6; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                    .header { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 5px; border-bottom: 3px solid #007bff; }
                    .message-box { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #007bff; }
                    .sender-info { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 15px 0; font-size: 14px; }
                    .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; text-align: center; color: #6c757d; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2 style='margin: 0; color: #333;'>📬 {$subject}</h2>
                    </div>
                    
                    <div class='content'>
                        <p>Hello,</p>
                        
                        <div class='sender-info'>
                            <strong>From:</strong> {$senderName} ({$senderEmail})<br>
                            <strong>Sent via:</strong> LocumLancer Platform
                        </div>
                        
                        <div class='message-box'>
                            <strong style='display: block; margin-bottom: 10px;'>Message:</strong>
                            <p style='margin: 0; white-space: pre-wrap;'>{$message->getText()}</p>
                        </div>
                        
                        {$attachmentHtml}
                        
                        <p style='font-style: italic; color: #6c757d;'>
                            When you reply, your response will go directly to {$senderName} at {$senderEmail}
                        </p>
                    </div>
                    
                    <div class='footer'>
                        <p>This message was sent via LocumLancer Platform</p>
                        <p>&copy; " . date('Y') . " LocumLancer. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
    }

    #[Route('/test-email', name: 'test_email')]
    public function testEmail(MailerInterface $mailer): JsonResponse
    {
        try {
            $user = $this->getUser(); // Get logged in provider
            
            if (!$user) {
                return new JsonResponse(['error' => 'No user logged in'], 400);
            }
            
            $senderName = $user->getName() ?: $user->getEmail();
            $senderEmail = $user->getEmail();
            
            if (!$senderEmail) {
                return new JsonResponse(['error' => 'Logged in user has no email'], 400);
            }
            
            $email = (new Email())
                ->from('notifications@locumlancer.com') // System email
                ->replyTo($senderEmail, $senderName) // Provider's actual email for replies
                ->to('rnabin20@gmail.com') // Test receiver
                ->subject("Test message from {$senderName} - LocumLancer")
                ->text("This is a test message sent by {$senderName} ({$senderEmail}) through LocumLancer")
                ->html("<p>This is a test message sent by <strong>{$senderName}</strong> ({$senderEmail}) through LocumLancer</p>");

            $mailer->send($email);
            return new JsonResponse([
                'status' => 'Email sent successfully',
                'sent_by' => $senderName,
                'sender_email' => $senderEmail
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/debug-message/{id}', name: 'debug_message')]
    public function debugMessage(Message $message): JsonResponse
    {
        $user = $this->getUser();
        $uploadDirectory = $this->getParameter('messages_attachments_directory');
        
        $attachmentInfo = null;
        if ($message->getAttachment()) {
            $filePath = $uploadDirectory . '/' . $message->getAttachment();
            $attachmentInfo = [
                'attachment_name' => $message->getAttachment(),
                'file_path' => $filePath,
                'file_exists' => file_exists($filePath),
                'is_readable' => is_readable($filePath),
                'file_size' => file_exists($filePath) ? filesize($filePath) : 0,
                'original_filename' => $this->getOriginalFilename($message->getAttachment())
            ];
        }
        
        return new JsonResponse([
            'message_id' => $message->getId(),
            'subject' => $message->getSubject(),
            'text' => $message->getText(),
            'is_draft' => $message->isDraft(),
            'has_attachment' => (bool)$message->getAttachment(),
            'attachment_info' => $attachmentInfo,
            'sender' => $message->getSender()->getEmail(),
            'receiver' => $message->getReceiver() ? $message->getReceiver()->getEmail() : null,
            'upload_directory' => $uploadDirectory,
            'directory_exists' => is_dir($uploadDirectory)
        ]);
    }
}