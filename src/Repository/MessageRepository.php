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
}