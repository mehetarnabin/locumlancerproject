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
            ->leftJoin('dr.application', 'a')
            ->leftJoin('a.job', 'j')
            ->leftJoin('a.employer', 'e')
            ->addSelect('dr', 'a', 'j', 'e')
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

    public function findPendingDocumentRequestsByJob(Provider $provider, $jobId): array
{
    return $this->createQueryBuilder('t')
        ->leftJoin('t.documentRequest', 'dr')
        ->leftJoin('dr.application', 'a')
        ->leftJoin('a.job', 'j')
        ->where('t.provider = :provider')
        ->andWhere('t.isCompleted = :completed')
        ->andWhere('t.type = :type')
        ->andWhere('j.id = :jobId')
        ->setParameter('provider', $provider)
        ->setParameter('completed', false)
        ->setParameter('type', 'document_request')
        ->setParameter('jobId', $jobId)
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
