<?php

namespace App\Repository;

use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function getAll(int $offset = 0, int $limit = 10, array $filters = [])
    {
        $queryBuilder = $this->createQueryBuilder('m')
            ->leftJoin('m.sender', 'sender')
            ->leftJoin('m.receiver', 'receiver')
            ->leftJoin('m.employer', 'employer')
            ->orderBy('m.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $this->applyFilters($queryBuilder, $filters);

        return $queryBuilder->getQuery()->getResult();
    }

    public function getCount(array $filters = []): int
    {
        $queryBuilder = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)');

        $this->applyFilters($queryBuilder, $filters);

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    private function applyFilters($queryBuilder, array $filters): void
    {
        // Handle different message types
        if (isset($filters['receiver'])) {
            // INBOX: Messages where user is receiver, not drafts, not deleted
            $queryBuilder->andWhere('m.receiver = :receiver')
                ->andWhere('m.deleted = false')
                ->andWhere('m.isDraft = false')
                ->setParameter('receiver', Uuid::fromString($filters['receiver'])->toBinary());
        }

        if (isset($filters['sender'])) {
            if (isset($filters['drafts_only']) && $filters['drafts_only']) {
                // DRAFTS: Only show draft messages from sender
                $queryBuilder->andWhere('m.sender = :sender')
                    ->andWhere('m.isDraft = true')
                    ->andWhere('m.deleted = false')
                    ->setParameter('sender', Uuid::fromString($filters['sender'])->toBinary());
            } else {
                // SENT: Show non-draft sent messages
                $queryBuilder->andWhere('m.sender = :sender')
                    ->andWhere('m.isDraft = false')
                    ->andWhere('m.deleted = false')
                    ->setParameter('sender', Uuid::fromString($filters['sender'])->toBinary());
            }
        }

        // Handle trash - messages that are deleted and user is either sender or receiver
        if (isset($filters['deleted']) && $filters['deleted']) {
            $queryBuilder->andWhere('m.deleted = true')
                ->andWhere('(m.sender = :user OR m.receiver = :user)')
                ->setParameter('user', Uuid::fromString($filters['user'])->toBinary());
        }

        // Handle search
        if (isset($filters['keyword']) && $filters['keyword']) {
            $queryBuilder->andWhere('m.text LIKE :keyword OR m.subject LIKE :keyword')
                ->setParameter('keyword', '%' . $filters['keyword'] . '%');
        }

        // Always exclude parent messages (only show main messages)
        $queryBuilder->andWhere('m.parent IS NULL');
    }

    public function getDraftCount(User $user): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.sender = :user')
            ->andWhere('m.isDraft = true')
            ->andWhere('m.deleted = false')
            ->andWhere('m.parent IS NULL')
            ->setParameter('user', $user->getId()->toBinary())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTrashCount(User $user): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.deleted = true')
            ->andWhere('(m.sender = :user OR m.receiver = :user)')
            ->andWhere('m.parent IS NULL')
            ->setParameter('user', $user->getId()->toBinary())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findDraft(string $draftId, User $user): ?Message
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.id = :id')
            ->andWhere('m.sender = :user')
            ->andWhere('m.isDraft = true')
            ->andWhere('m.deleted = false')
            ->andWhere('m.parent IS NULL')
            ->setParameter('id', Uuid::fromString($draftId)->toBinary())
            ->setParameter('user', $user->getId()->toBinary())
            ->getQuery()
            ->getOneOrNullResult();
    }

    // In MessageRepository
    public function getForwardedCount(User $user): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.sender = :user')
            ->andWhere('m.isForwarded = true')
            ->andWhere('m.deleted = false')
            ->setParameter('user', $user->getId()->toBinary())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get root message (thread starter) for a given message
     */
    public function getRootMessage(Message $message): Message
    {
        $current = $message;
        $visited = [];
        $maxDepth = 100; // Protection against infinite loops
        $depth = 0;
        
        while ($current->getParent() !== null && $depth < $maxDepth) {
            $currentId = $current->getId()->toString();
            if (isset($visited[$currentId])) {
                // Circular reference detected, break
                error_log('Circular reference detected in message parent chain');
                break;
            }
            $visited[$currentId] = true;
            $current = $current->getParent();
            $depth++;
        }
        
        return $current;
    }

    /**
     * Get all messages in a thread (root + all replies)
     */
    public function getThreadMessages(Message $rootMessage): array
    {
        $messages = [$rootMessage];
        
        // Recursively get all replies with proper eager loading
        $this->getRepliesRecursive($rootMessage, $messages);
        
        // Sort by creation date
        usort($messages, function($a, $b) {
            $aDate = $a->getCreatedAt();
            $bDate = $b->getCreatedAt();
            if (!$aDate || !$bDate) {
                return 0;
            }
            return $aDate <=> $bDate;
        });
        
        return $messages;
    }
    
    /**
     * Recursively get all replies for a message with eager loading of relationships
     */
    private function getRepliesRecursive(Message $message, array &$messages): void
    {
        // Use query builder to ensure sender and receiver are loaded
        $replies = $this->createQueryBuilder('m')
            ->leftJoin('m.sender', 'sender')
            ->leftJoin('m.receiver', 'receiver')
            ->addSelect('sender', 'receiver')
            ->where('m.parent = :parent')
            ->setParameter('parent', $message->getId())
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
        
        foreach ($replies as $reply) {
            // Avoid duplicates
            $replyId = $reply->getId()->toString();
            $exists = false;
            foreach ($messages as $existing) {
                if ($existing->getId()->toString() === $replyId) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                $messages[] = $reply;
                $this->getRepliesRecursive($reply, $messages);
            }
        }
    }

    /**
     * Get thread-based messages (grouped by root parent)
     * Returns only root messages with metadata about thread
     */
    public function getThreadBasedMessages(int $offset = 0, int $limit = 10, array $filters = []): array
    {
        $queryBuilder = $this->createQueryBuilder('m')
            ->leftJoin('m.sender', 'sender')
            ->leftJoin('m.receiver', 'receiver')
            ->leftJoin('m.employer', 'employer')
            ->addSelect('sender', 'receiver', 'employer')
            ->orderBy('m.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $this->applyFilters($queryBuilder, $filters);

        $rootMessages = $queryBuilder->getQuery()->getResult();
        
        // For each root message, get reply count and latest reply info
        $threads = [];
        foreach ($rootMessages as $rootMessage) {
            $replyCount = $this->createQueryBuilder('m')
                ->select('COUNT(m.id)')
                ->where('m.parent = :root')
                ->setParameter('root', $rootMessage->getId()->toBinary())
                ->getQuery()
                ->getSingleScalarResult();
            
            $latestReply = $this->createQueryBuilder('m')
                ->where('m.parent = :root')
                ->setParameter('root', $rootMessage->getId()->toBinary())
                ->orderBy('m.createdAt', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            
            $threads[] = [
                'root' => $rootMessage,
                'reply_count' => (int)$replyCount,
                'latest_reply' => $latestReply,
                'has_replies' => $replyCount > 0
            ];
        }
        
        return $threads;
    }
}