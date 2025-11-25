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
        // Handle trash first - this takes precedence over other filters
        if (isset($filters['deleted']) && $filters['deleted'] === true) {
            // TRASH: Show all deleted messages where user is either sender or receiver
            // This includes both regular messages AND drafts that were deleted
            $queryBuilder->andWhere('m.deleted = true')
                ->andWhere('(m.sender = :user OR m.receiver = :user)')
                ->setParameter('user', Uuid::fromString($filters['user'])->toBinary());
        } else {
            // For non-trash views, exclude deleted messages
            $queryBuilder->andWhere('m.deleted = false');

            // Handle different message types for non-trash
            if (isset($filters['receiver'])) {
                // INBOX: Messages where user is receiver, not drafts
                $queryBuilder->andWhere('m.receiver = :receiver')
                    ->andWhere('m.isDraft = false')
                    ->setParameter('receiver', Uuid::fromString($filters['receiver'])->toBinary());
            }

            if (isset($filters['sender'])) {
                if (isset($filters['drafts_only']) && $filters['drafts_only']) {
                    // DRAFTS: Only show draft messages from sender
                    $queryBuilder->andWhere('m.sender = :sender')
                        ->andWhere('m.isDraft = true')
                        ->setParameter('sender', Uuid::fromString($filters['sender'])->toBinary());
                } else {
                    // SENT: Show non-draft sent messages
                    $queryBuilder->andWhere('m.sender = :sender')
                        ->andWhere('m.isDraft = false')
                        ->setParameter('sender', Uuid::fromString($filters['sender'])->toBinary());
                }
            }
        }

        // Handle search (applies to all views including trash)
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
            ->andWhere('m.deleted = false') // Only count non-deleted drafts
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
            ->andWhere('m.deleted = false') // Only find non-deleted drafts
            ->andWhere('m.parent IS NULL')
            ->setParameter('id', Uuid::fromString($draftId)->toBinary())
            ->setParameter('user', $user->getId()->toBinary())
            ->getQuery()
            ->getOneOrNullResult();
    }

    // Find a message in trash (for restoration)
    public function findInTrash(string $messageId, User $user): ?Message
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.id = :id')
            ->andWhere('m.deleted = true')
            ->andWhere('(m.sender = :user OR m.receiver = :user)')
            ->setParameter('id', Uuid::fromString($messageId)->toBinary())
            ->setParameter('user', $user->getId()->toBinary())
            ->getQuery()
            ->getOneOrNullResult();
    }

    // Get all trash messages for a user
    public function findTrashMessages(User $user, int $offset = 0, int $limit = 10): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.sender', 'sender')
            ->leftJoin('m.receiver', 'receiver')
            ->leftJoin('m.employer', 'employer')
            ->andWhere('m.deleted = true')
            ->andWhere('(m.sender = :user OR m.receiver = :user)')
            ->andWhere('m.parent IS NULL')
            ->orderBy('m.deletedAt', 'DESC')
            ->setParameter('user', $user->getId()->toBinary())
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
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
}