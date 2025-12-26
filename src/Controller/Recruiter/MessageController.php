<?php

namespace App\Controller\Recruiter;

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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[Route('/recruiter')]
class MessageController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    private function getUserOrDeny(): User
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }
        return $user;
    }

    #[Route('/messages', name: 'app_recruiter_messages')]
    public function index(Request $request, EntityManagerInterface $em)
    {
        $user = $this->getUserOrDeny();
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

        // Get thread-based messages with pagination
        $messageThreads = $em->getRepository(Message::class)->getThreadBasedMessages($offset, $perPage, $filters);

        // Get total count for pagination
        $totalMessages = $em->getRepository(Message::class)->getCount($filters);
        $totalPages = ceil($totalMessages / $perPage);

        // Get counts for badges
        $draftCount = $em->getRepository(Message::class)->getDraftCount($user);
        $trashCount = $em->getRepository(Message::class)->getTrashCount($user);

        // Get providers for compose modal
        $recruiterId = $user->getRecruiter()->getId();
        $providers = $em->getRepository(User::class)->getProvidersForRecruiter($recruiterId);

        return $this->render('recruiter/message/index.html.twig', [
            'message_threads' => $messageThreads,
            'messages' => array_map(function ($thread) {
                return $thread['root'];
            }, $messageThreads), // For backward compatibility
            'draft_count' => $draftCount,
            'trash_count' => $trashCount,
            'current_type' => $type,
            'providers' => $providers,
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

    #[Route('/messages/new', name: 'app_recruiter_messages_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        EventDispatcherInterface $dispatcher,
        MailerInterface $mailer,
        SluggerInterface $slugger,
        #[Autowire('%messages_attachments_directory%')] string $uploadDirectory
    ) {
        $user = $this->getUserOrDeny();

        // Find recruiter by user relationship
        $recruiter = $user->getRecruiter();

        if (!$recruiter) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'No recruiter profile found'], 400);
            }
            $this->addFlash('error', 'Please complete your recruiter profile before sending messages.');
            return $this->redirectToRoute('app_recruiter_profile');
        }

        // Get providers
        try {
            $providers = $em->getRepository(User::class)->getProvidersForRecruiter($recruiter->getId());
        } catch (\Exception $e) {
            $providers = []; // If error, empty list
        }

        // Handle POST requests
        if ($request->isMethod('POST')) {
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
                    $this->addFlash('error', 'Receiver and message are required to send');
                    return $this->redirectToRoute('app_recruiter_messages');
                }
            } else {
                // For drafts, only text is required
                if (empty(trim($text))) {
                    $this->addFlash('error', 'Message text is required for draft');
                    return $this->redirectToRoute('app_recruiter_messages');
                }
            }

            // Find existing draft or create new message
            if ($draftId) {
                $message = $em->getRepository(Message::class)->findDraft($draftId, $user);
                if (!$message) {
                    $this->addFlash('error', 'Draft not found');
                    return $this->redirectToRoute('app_recruiter_messages');
                }
            } else {
                $message = new Message();
                $message->setSender($user);
            }

            // Set receiver if provided (can be empty for drafts)
            if ($receiverId) {
                $providerUser = $em->getRepository(User::class)->find($receiverId);
                if ($providerUser) {
                    $message->setReceiver($providerUser);
                } else {
                    if (!$isDraftAction) {
                        $this->addFlash('error', 'Receiver not found');
                        return $this->redirectToRoute('app_recruiter_messages');
                    }
                }
            }

            // Set subject and text
            $message->setSubject($subject);
            $message->setText($text);
            $message->setRecruiter($recruiter);

            // Handle file upload BEFORE deciding to send or save draft
            if ($request->files->get('attachment')) {
                $file = $request->files->get('attachment');
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

                try {
                    // Ensure directory exists
                    if (!is_dir($uploadDirectory)) {
                        mkdir($uploadDirectory, 0755, true);
                    }

                    $file->move($uploadDirectory, $newFilename);
                    $message->setAttachment($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('warning', 'Message saved but file upload failed');
                }
            }

            // CRITICAL FIX: Clear draft/sent logic
            if ($isDraftAction) {
                // SAVE AS DRAFT
                $message->setIsDraft(true);
                $message->setSavedAt(new \DateTime());
                $message->setSeen(true); // Drafts are always "seen" by sender
                $successMessage = 'Message saved as draft successfully';
                $redirectParams = ['type' => 'drafts'];
            } else {
                // SEND MESSAGE
                $message->setIsDraft(false);
                $message->setSentAt(new \DateTime());
                $message->setSeen(false); // Sent messages start as unread for receiver
                $successMessage = 'Message has been sent successfully';
                $redirectParams = ['type' => 'sent'];

                // Only dispatch event and send email for actual sent messages
                if ($message->getReceiver()) {
                    $dispatcher->dispatch(new MessageEvent($message), MessageEvent::MESSAGE_CREATED);
                    $this->sendEmailToReceiver($message, $mailer);
                }
            }

            $em->persist($message);
            $em->flush();

            $this->addFlash('success', $successMessage);
            return $this->redirectToRoute('app_recruiter_messages', $redirectParams);
        }

        return $this->render('recruiter/message/new.html.twig', [
            'providers' => $providers,
        ]);
    }

    #[Route('/messages/{id}/delete', name: 'app_recruiter_message_delete', methods: ['POST', 'DELETE'])]
    public function delete(Message $message, EntityManagerInterface $entityManager, Request $request): JsonResponse
    {
        $user = $this->getUserOrDeny();

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

    // Save draft via AJAX (manual saving only)
    #[Route('/messages/draft/save', name: 'app_recruiter_draft_save', methods: ['POST'])]
    public function saveDraft(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUserOrDeny();

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
            $message->setRecruiter($user->getRecruiter());
        }

        // Set receiver if provided
        if ($receiverId) {
            $receiver = $em->getRepository(User::class)->find($receiverId);
            if ($receiver) {
                $message->setReceiver($receiver ?? null);
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

    // Load draft via AJAX - ENHANCED WITH ATTACHMENT SUPPORT
    #[Route('/messages/draft/{id}', name: 'app_recruiter_draft_load', methods: ['GET'])]
    public function loadDraft(Message $message, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUserOrDeny();

        // Security check - user can only load their own drafts
        if ($message->getSender()->getId() !== $user->getId() || !$message->isDraft()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        // Get upload directory for attachment path
        $uploadDirectory = $this->getParameter('messages_attachments_directory');
        $attachmentInfo = null;

        if ($message->getAttachment()) {
            $filePath = $uploadDirectory . '/' . $message->getAttachment();
            $fileExists = file_exists($filePath);

            $attachmentInfo = [
                'filename' => $message->getAttachment(),
                'original_filename' => $this->getOriginalFilename($message->getAttachment()),
                'file_exists' => $fileExists,
                'file_path' => $filePath,
                'file_size' => $fileExists ? filesize($filePath) : 0,
                'download_url' => $this->generateUrl('app_recruiter_message_attachment', ['filename' => $message->getAttachment()])
            ];
        }

        return new JsonResponse([
            'success' => true,
            'draft' => [
                'id' => $message->getId(),
                'receiver_id' => $message->getReceiver() ? $message->getReceiver()->getId() : null,
                'subject' => $message->getSubject(),
                'message' => $message->getText(),
                'attachment' => $message->getAttachment(),
                'attachment_info' => $attachmentInfo,
                'savedAt' => $message->getSavedAt()->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    // Delete draft - ENHANCED WITH TRASH FUNCTIONALITY
    #[Route('/messages/draft/{id}', name: 'app_recruiter_draft_delete', methods: ['DELETE'])]
    public function deleteDraft(Message $message, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUserOrDeny();

        // Security check
        if ($message->getSender()->getId() !== $user->getId() || !$message->isDraft()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        try {
            // Instead of removing, move to trash
            $message->setDeleted(true);
            $message->setDeletedAt(new \DateTime());

            $em->persist($message);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Draft moved to trash successfully'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to delete draft: ' . $e->getMessage()
            ], 500);
        }
    }

    // Get draft count for badge
    #[Route('/messages/drafts/count', name: 'app_recruiter_drafts_count', methods: ['GET'])]
    public function getDraftCount(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUserOrDeny();
        $count = $em->getRepository(Message::class)->getDraftCount($user);

        return new JsonResponse(['count' => $count]);
    }

    // Get message data for forwarding (JSON endpoint)
    #[Route('/messages/{id}/forward-data', name: 'app_recruiter_message_forward_data', methods: ['GET'])]
    public function getMessageForwardData(Message $message, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUserOrDeny();

        // Security check - user must be sender or receiver
        $isSender = $message->getSender() && $message->getSender()->getId() === $user->getId();
        $isReceiver = $message->getReceiver() && $message->getReceiver()->getId() === $user->getId();

        if (!$isSender && !$isReceiver) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        // Get root message for thread context
        $rootMessage = $em->getRepository(Message::class)->getRootMessage($message);

        $senderName = $message->getSender() ? ($message->getSender()->getName() ?: $message->getSender()->getEmail()) : 'Unknown';
        $createdAt = $message->getCreatedAt() ? $message->getCreatedAt()->format('F j, Y \\a\\t g:i A') : '';

        return new JsonResponse([
            'success' => true,
            'message' => [
                'id' => $message->getId(),
                'subject' => $message->getSubject() ?: 'No subject',
                'text' => $message->getText(),
                'sender_name' => $senderName,
                'created_at' => $createdAt,
                'root_id' => $rootMessage->getId(),
            ]
        ]);
    }

    #[Route('/messages/{id}/show', name: 'app_recruiter_message_show', methods: ['GET'])]
    public function show(Message $message, EntityManagerInterface $em)
    {
        $user = $this->getUserOrDeny();

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

        // Get counts for badges
        $draftCount = $em->getRepository(Message::class)->getDraftCount($user);
        $trashCount = $em->getRepository(Message::class)->getTrashCount($user);

        // Get providers for compose modal
        $recruiterId = $user->getRecruiter()->getId();
        $providers = $em->getRepository(User::class)->getProvidersForRecruiter($recruiterId);

        // Get root message (thread starter)
        $rootMessage = $em->getRepository(Message::class)->getRootMessage($message);

        // Get all messages in the thread
        $threadMessages = $em->getRepository(Message::class)->getThreadMessages($rootMessage);

        return $this->render('recruiter/message/show.html.twig', [
            'message' => $message,
            'root_message' => $rootMessage,
            'thread_messages' => $threadMessages,
            'replies' => $em->getRepository(Message::class)->findBy(['parent' => $rootMessage], ['createdAt' => 'ASC']),
            'draft_count' => $draftCount,
            'trash_count' => $trashCount,
            'providers' => $providers,
        ]);
    }

    #[Route('/messages/{id}/reply', name: 'app_recruiter_message_reply_ajax', methods: ['POST'])]
    public function ajaxReply(
        Message $message,
        Request $request,
        EntityManagerInterface $em,
        EventDispatcherInterface $dispatcher,
        SluggerInterface $slugger,
        #[Autowire('%messages_attachments_directory%')] string $uploadDirectory
    ): JsonResponse {
        $user = $this->getUserOrDeny();

        // Security check
        if ($message->getSender()->getId() !== $user->getId() && $message->getReceiver()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $reply = new Message();
        $reply->setParent($message);
        $reply->setRecruiter($user->getRecruiter());
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
            'replyHtml' => $this->renderView('recruiter/message/_reply_item.html.twig', ['reply' => $reply])
        ]);
    }

    // Get trash count for badge
    #[Route('/messages/trash/count', name: 'app_recruiter_trash_count', methods: ['GET'])]
    public function getTrashCount(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUserOrDeny();
        $count = $em->getRepository(Message::class)->getTrashCount($user);

        return new JsonResponse(['count' => $count]);
    }

    // Restore message from trash
    #[Route('/messages/restore/{id}', name: 'app_recruiter_message_restore', methods: ['POST'])]
    public function restoreMessage(Message $message, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUserOrDeny();

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
                'message' => 'Message restored successfully',
                'was_draft' => $message->isDraft()
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to restore message: ' . $e->getMessage()
            ], 500);
        }
    }

    // Permanently delete message from trash
    #[Route('/messages/permanent-delete/{id}', name: 'app_recruiter_message_permanent_delete', methods: ['DELETE'])]
    public function permanentDelete(Message $message, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUserOrDeny();

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
    #[Route('/messages/empty-trash', name: 'app_recruiter_empty_trash', methods: ['DELETE'])]
    public function emptyTrash(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUserOrDeny();

        // Find all deleted messages for this user
        $messages = $em->getRepository(Message::class)->createQueryBuilder('m')
            ->where('m.deleted = true')
            ->andWhere('(m.sender = :user OR m.receiver = :user)')
            ->setParameter('user', $user->getId()->toBinary())
            ->getQuery()
            ->getResult();

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
    #[Route('/message/{id}/mark-read', name: 'app_recruiter_message_mark_read', methods: ['POST'])]
    public function markAsRead(Message $message, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUserOrDeny();

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

    // FORWARD ENTIRE THREAD
    #[Route('/messages/{id}/forward-thread', name: 'app_recruiter_message_forward_thread', methods: ['GET', 'POST'])]
    public function forwardThread(
        Message $message,
        Request $request,
        EntityManagerInterface $em,
        EventDispatcherInterface $dispatcher,
        MailerInterface $mailer,
        SluggerInterface $slugger,
        #[Autowire('%messages_attachments_directory%')] string $uploadDirectory
    ) {
        $user = $this->getUserOrDeny();

        // Get root message (thread starter)
        $rootMessage = $em->getRepository(Message::class)->getRootMessage($message);

        // Security check
        $isSender = $rootMessage->getSender() && $rootMessage->getSender()->getId() === $user->getId();
        $isReceiver = $rootMessage->getReceiver() && $rootMessage->getReceiver()->getId() === $user->getId();

        if (!$isSender && !$isReceiver) {
            $this->addFlash('error', 'You cannot forward this thread.');
            return $this->redirectToRoute('app_recruiter_messages');
        }

        // Get all messages in thread
        $threadMessages = $em->getRepository(Message::class)->getThreadMessages($rootMessage);

        // Get providers for the forward modal
        $recruiterId = $user->getRecruiter()->getId();
        $providers = $em->getRepository(User::class)->getProvidersForRecruiter($recruiterId);

        // Handle POST request (actual forwarding)
        if ($request->isMethod('POST')) {
            $receiverId = $request->get('receiver');
            $forwardText = $request->get('forward_message');
            $subject = $request->get('subject');

            // Validation
            if (empty($receiverId) || empty(trim($forwardText))) {
                $this->addFlash('error', 'Receiver and message are required to forward');
                return $this->redirectToRoute('app_recruiter_messages');
            }

            // Build forwarded content with entire thread
            $forwardedContent = $this->buildForwardedThreadContent($threadMessages, $forwardText);

            // Create forwarded message
            $forwardedMessage = new Message();
            $forwardedMessage->setSender($user);
            $forwardedMessage->setIsForwarded(true);
            $forwardedMessage->setOriginalSubject($rootMessage->getSubject());
            $forwardedMessage->setRecruiter($user->getRecruiter());

            // Set receiver
            $receiverUser = $em->getRepository(User::class)->find($receiverId);
            if ($receiverUser) {
                $forwardedMessage->setReceiver($receiverUser);
            } else {
                $this->addFlash('error', 'Receiver not found');
                return $this->redirectToRoute('app_recruiter_messages');
            }

            $forwardedMessage->setText($forwardedContent);
            $forwardedMessage->setSubject($subject ?: $this->buildForwardSubject($rootMessage->getSubject()));
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

            $this->addFlash('success', 'Thread has been forwarded successfully');
            return $this->redirectToRoute('app_recruiter_messages', ['type' => 'sent']);
        }

        // For GET request, show the forward form
        return $this->render('recruiter/message/forward.html.twig', [
            'message' => $rootMessage,
            'thread_messages' => $threadMessages,
            'forward_thread' => true,
            'providers' => $providers,
        ]);
    }

    // FORWARD FUNCTIONALITY - RENAMED TO AVOID CONFLICT
    #[Route('/messages/{id}/forward-message', name: 'app_recruiter_message_forward', methods: ['GET', 'POST'])]
    public function forwardMessage(
        Message $message,
        Request $request,
        EntityManagerInterface $em,
        EventDispatcherInterface $dispatcher,
        MailerInterface $mailer,
        SluggerInterface $slugger,
        #[Autowire('%messages_attachments_directory%')] string $uploadDirectory
    ) {
        $user = $this->getUserOrDeny();

        // Get root message (thread starter)
        $rootMessage = $em->getRepository(Message::class)->getRootMessage($message);

        // Security check
        $isSender = $rootMessage->getSender() && $rootMessage->getSender()->getId() === $user->getId();
        $isReceiver = $rootMessage->getReceiver() && $rootMessage->getReceiver()->getId() === $user->getId();

        if (!$isSender && !$isReceiver) {
            $this->addFlash('error', 'You cannot forward this message.');
            return $this->redirectToRoute('app_recruiter_messages');
        }

        // Get providers for the forward modal
        $recruiterId = $user->getRecruiter()->getId();
        $providers = $em->getRepository(User::class)->getProvidersForRecruiter($recruiterId);

        // Handle POST request (actual forwarding)
        if ($request->isMethod('POST')) {
            $receiverId = $request->get('receiver');
            $forwardText = $request->get('forward_message');
            $subject = $request->get('subject');

            // Validation
            if (empty($receiverId) || empty(trim($forwardText))) {
                $this->addFlash('error', 'Receiver and message are required to forward');
                return $this->redirectToRoute('app_recruiter_messages');
            }

            // Create forwarded message
            $forwardedMessage = new Message();
            $forwardedMessage->setSender($user);
            $forwardedMessage->setIsForwarded(true);
            $forwardedMessage->setOriginalSubject($message->getSubject());
            $forwardedMessage->setEmployer($user->getEmployer());

            // Set receiver
            $receiverUser = $em->getRepository(User::class)->find($receiverId);
            if ($receiverUser) {
                $forwardedMessage->setReceiver($receiverUser);
            } else {
                $this->addFlash('error', 'Receiver not found');
                return $this->redirectToRoute('app_recruiter_messages');
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
            return $this->redirectToRoute('app_recruiter_messages', ['type' => 'sent']);
        }

        // For GET request, show the forward form
        return $this->render('recruiter/message/forward.html.twig', [
            'message' => $message,
            'providers' => $providers,
        ]);
    }

    // Download attachment
    #[Route('/messages/attachment/{filename}', name: 'app_recruiter_message_attachment', methods: ['GET'])]
    public function downloadAttachment(string $filename, #[Autowire('%messages_attachments_directory%')] string $uploadDirectory): Response
    {
        $user = $this->getUserOrDeny();

        // Security: Find the message that contains this attachment
        $message = $this->entityManager->getRepository(Message::class)
            ->findOneBy(['attachment' => $filename]);

        if (!$message) {
            throw $this->createNotFoundException('Attachment not found');
        }

        // Security: User must be sender or receiver of the message
        if (
            $message->getSender()->getId() !== $user->getId() &&
            (!$message->getReceiver() || $message->getReceiver()->getId() !== $user->getId())
        ) {
            throw $this->createAccessDeniedException('You cannot access this attachment');
        }

        $filePath = $uploadDirectory . '/' . $filename;

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('File not found');
        }

        // Get original filename
        $originalFilename = $this->getOriginalFilename($filename);

        return $this->file($filePath, $originalFilename);
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

    private function buildForwardedThreadContent(array $threadMessages, string $forwardText): string
    {
        $content = "---------- Forwarded conversation ---------\n";
        $content .= "Total messages: " . count($threadMessages) . "\n\n";

        foreach ($threadMessages as $msg) {
            $sender = $msg->getSender()->getName() ?: $msg->getSender()->getEmail();
            $receiver = $msg->getReceiver() ? ($msg->getReceiver()->getName() ?: $msg->getReceiver()->getEmail()) : 'Unknown';
            $date = $msg->getCreatedAt()->format('F j, Y \\a\\t g:i A');

            $content .= "----------\n";
            $content .= "From: {$sender}\n";
            $content .= "To: {$receiver}\n";
            $content .= "Date: {$date}\n";
            $content .= "Subject: " . ($msg->getSubject() ?: 'No subject') . "\n\n";
            $content .= $msg->getText() . "\n\n";
        }

        $content .= "----------\n\n";
        $content .= $forwardText;

        return $content;
    }

    private function sendEmailToReceiver(Message $message, MailerInterface $mailer): void
    {
        // Don't send emails for drafts
        if ($message->isDraft()) {
            return;
        }

        try {
            $receiver = $message->getReceiver();
            $sender = $message->getSender();

            if (!$receiver || !$receiver->getEmail()) {
                return;
            }

            $senderName = $sender->getName() ?: $sender->getEmail();
            $senderEmail = $sender->getEmail();

            if (!$senderEmail) {
                return;
            }

            $subject = $message->getSubject() ?: "New message from {$senderName}";

            // Create the email
            $email = (new Email())
                ->from('notifications@locumlancer.com')
                ->replyTo($senderEmail)
                ->to($receiver->getEmail())
                ->subject($subject . ' - LocumLancer')
                ->html($this->getEmployerMessageTemplate($message, $senderName, $senderEmail));

            // Attachment handling
            if ($message->getAttachment()) {
                $uploadDirectory = $this->getParameter('messages_attachments_directory');
                $filePath = $uploadDirectory . '/' . $message->getAttachment();
                $originalFilename = $this->getOriginalFilename($message->getAttachment());

                if (file_exists($filePath) && is_readable($filePath)) {
                    try {
                        $email->attachFromPath($filePath, $originalFilename);
                    } catch (\Exception $e) {
                        // Attachment failed, continue without it
                    }
                }
            }

            // Send the email
            $mailer->send($email);
        } catch (\Exception $e) {
            // Email sending failed, log but don't break the flow
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

    private function getEmployerMessageTemplate(Message $message, string $senderName, string $senderEmail): string
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
            }
        }

        if (empty($attachmentHtml)) {
            $attachmentHtml = "
                <div style='background: #f8f9fa; padding: 12px; border-radius: 6px; margin: 10px 0; border-left: 4px solid #6c757d;'>
                    <small style='color: #6c757d;'>No attachment included with this message.</small>
                </div>
            ";
        }

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
}
