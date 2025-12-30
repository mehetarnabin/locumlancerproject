<?php
// src/Repository/ToDoRepository.php

namespace App\Repository;

use App\Entity\ToDo;
use App\Entity\Provider;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ToDo>
 */
class ToDoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ToDo::class);
    }

    /**
     * Find all pending to-do items for a provider related to document requests
     */
    public function findPendingByProvider(Provider $provider): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.documentRequest', 'dr')
            ->leftJoin('t.employer', 'e')
            ->leftJoin('t.job', 'j')
            ->addSelect('dr', 'e', 'j')
            ->where('t.provider = :provider')
            ->andWhere('t.isCompleted = :completed')
            ->andWhere('t.type = :type')
            ->andWhere('(dr IS NULL OR dr.providedAt IS NULL)')
            ->setParameter('provider', $provider)
            ->setParameter('completed', false)
            ->setParameter('type', 'document_request')
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all pending to-do items assigned to the provider (by employer or system)
     */
    public function findAllAssignedToProvider(Provider $provider): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.documentRequest', 'dr')
            ->leftJoin('t.employer', 'e')
            ->addSelect('dr', 'e')
            ->where('t.provider = :provider')
            ->andWhere('t.isCompleted = :completed')
            ->andWhere('t.employer IS NOT NULL OR t.documentRequest IS NOT NULL OR t.type = :docType')
            ->setParameter('provider', $provider)
            ->setParameter('completed', false)
            ->setParameter('docType', 'document_request')
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingDocumentRequestsByJob(Provider $provider, $jobId): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.documentRequest', 'dr')
            ->addSelect('dr')
            ->where('t.provider = :provider')
            ->andWhere('t.isCompleted = :completed')
            ->andWhere('t.type = :type')
            ->setParameter('provider', $provider)
            ->setParameter('completed', false)
            ->setParameter('type', 'document_request')
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find todos by bookmark ID
     */
    public function findByBookmark($bookmarkId): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.bookmark = :bookmarkId')
            ->setParameter('bookmarkId', $bookmarkId)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(ToDo $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ToDo $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
