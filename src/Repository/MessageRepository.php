<?php

namespace App\Repository;

use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

        public function getAll($offset, $perPage, $filters)
    {
        $qb = $this->createQueryBuilder('m')
            ->orderBy('m.createdAt', 'DESC');

        $qb->andWhere('m.parent IS NULL');

        // Handle different filter types
        if (isset($filters['receiver'])) {
            $qb->andWhere('m.receiver = :receiver')
            ->setParameter('receiver', $filters['receiver'], UuidType::NAME);
        }

        if (isset($filters['sender'])) {
            $qb->andWhere('m.sender = :sender')
            ->setParameter('sender', $filters['sender'], UuidType::NAME);
        }

        // Handle draft filtering
        if (isset($filters['drafts_only']) && $filters['drafts_only']) {
            $qb->andWhere('m.isDraft = true');
        } else {
            // For non-draft views, exclude drafts by default
            $qb->andWhere('m.isDraft = false');
        }

        // Handle trash filter
        if (isset($filters['deleted']) && $filters['deleted']) {
            $qb->andWhere('m.deleted = true')
            ->andWhere('(m.sender = :user OR m.receiver = :user)')
            ->setParameter('user', $filters['user'], UuidType::NAME);
        } else {
            // For non-trash views, exclude deleted messages
            $qb->andWhere('m.deleted = false OR m.deleted IS NULL');
        }

        if (isset($filters['keyword']) && !empty($filters['keyword'])) {
            $qb->andWhere('m.text LIKE :keyword')
            ->setParameter('keyword', '%' . $filters['keyword'] . '%');
        }

        $pagerfanta = new Pagerfanta(new QueryAdapter($qb));
        $pagerfanta->setMaxPerPage($perPage);
        $pagerfanta->setCurrentPage($offset);

        return $pagerfanta;
    }
    // NEW: Get user drafts (without pagination for sidebar/draft list)
    public function findUserDrafts(User $user)
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.sender = :user')
            ->andWhere('m.isDraft = true')
            ->andWhere('m.parent IS NULL') // Only main messages, not replies
            ->setParameter('user', $user->getId(), UuidType::NAME)
            ->orderBy('m.savedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // NEW: Get draft count for user
    public function getDraftCount(User $user): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.sender = :user')
            ->andWhere('m.isDraft = true')
            ->andWhere('m.parent IS NULL') // Only main messages, not replies
            ->setParameter('user', $user->getId(), UuidType::NAME)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // NEW: Find a specific draft
    public function findDraft($id, User $user): ?Message
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.id = :id')
            ->andWhere('m.sender = :user')
            ->andWhere('m.isDraft = true')
            ->setParameter('id', $id, UuidType::NAME)
            ->setParameter('user', $user->getId(), UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // NEW: Find drafts by receiver and text (for auto-saving)
    public function findSimilarDraft(User $user, ?User $receiver = null, string $text = ''): ?Message
    {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.sender = :user')
            ->andWhere('m.isDraft = true')
            ->setParameter('user', $user->getId(), UuidType::NAME);

        if ($receiver) {
            $qb->andWhere('m.receiver = :receiver')
               ->setParameter('receiver', $receiver->getId(), UuidType::NAME);
        }

        if (!empty($text)) {
            $qb->andWhere('m.text = :text')
               ->setParameter('text', $text);
        }

        return $qb->orderBy('m.savedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // Get trash count for user
    public function getTrashCount(User $user): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.deleted = true')
            ->andWhere('(m.sender = :user OR m.receiver = :user)')
            ->setParameter('user', $user->getId(), UuidType::NAME)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // Helper method to create paginator (if needed)
    private function createPaginator($query, $page, $perPage)
    {
        $pagerfanta = new Pagerfanta(new QueryAdapter($query));
        $pagerfanta->setMaxPerPage($perPage);
        $pagerfanta->setCurrentPage($page);

        return $pagerfanta;
    }
}

    
