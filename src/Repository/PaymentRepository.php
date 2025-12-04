<?php

// src/Repository/PaymentRepository.php

namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function findByUser(int $userId, array $criteria = [], array $orderBy = ['createdAt' => 'DESC']): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->andWhere('u.id = :userId')
            ->setParameter('userId', $userId);
            
        foreach ($criteria as $field => $value) {
            $qb->andWhere("p.$field = :$field")
               ->setParameter($field, $value);
        }
        
        foreach ($orderBy as $field => $direction) {
            $qb->addOrderBy("p.$field", $direction);
        }
        
        return $qb->getQuery()->getResult();
    }

    public function findByProvider(int $providerId): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.provider', 'pr')
            ->andWhere('pr.id = :providerId')
            ->setParameter('providerId', $providerId)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getTotalRevenue(): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount) as total')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();
            
        return (float) $result;
    }

    public function getPlatformEarnings(): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.platformFee) as total')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();
            
        return (float) $result;
    }

    public function getProviderEarnings(int $providerId): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.providerAmount) as total')
            ->leftJoin('p.provider', 'pr')
            ->andWhere('pr.id = :providerId')
            ->andWhere('p.status = :status')
            ->setParameter('providerId', $providerId)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();
            
        return (float) $result;
    }
}