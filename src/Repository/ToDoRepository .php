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

    public function findPendingByProvider(Provider $provider): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.provider = :provider')
            ->andWhere('t.isCompleted = :isCompleted')
            ->setParameter('provider', $provider)
            ->setParameter('isCompleted', false)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countPendingByProvider(Provider $provider): int
    {
        return $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.provider = :provider')
            ->andWhere('t.isCompleted = :isCompleted')
            ->setParameter('provider', $provider)
            ->setParameter('isCompleted', false)
            ->getQuery()
            ->getSingleScalarResult();
    }
}