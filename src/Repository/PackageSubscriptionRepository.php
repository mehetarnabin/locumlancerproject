<?php

namespace App\Repository;

use App\Entity\PackageSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PackageSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PackageSubscription::class);
    }

    /**
     * Find active subscription for a user
     */
    public function findActiveSubscriptionByUser($user): ?PackageSubscription
    {
        return $this->createQueryBuilder('ps')
            ->andWhere('ps.user = :user')
            ->andWhere('ps.status = :status')
            ->andWhere('ps.endDate > :now')
            ->setParameter('user', $user)
            ->setParameter('status', PackageSubscription::STATUS_ACTIVE)
            ->setParameter('now', new \DateTime())
            ->orderBy('ps.endDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all subscriptions for a user
     */
    public function findByUser($user): array
    {
        return $this->createQueryBuilder('ps')
            ->andWhere('ps.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ps.endDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find subscriptions by status
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('ps')
            ->andWhere('ps.status = :status')
            ->setParameter('status', $status)
            ->orderBy('ps.endDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find expired subscriptions that should be updated
     */
    public function findExpiredSubscriptions(): array
    {
        return $this->createQueryBuilder('ps')
            ->andWhere('ps.status = :status')
            ->andWhere('ps.endDate < :now')
            ->setParameter('status', PackageSubscription::STATUS_ACTIVE)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * Find subscription by Stripe subscription ID
     */
    public function findByStripeSubscriptionId(string $stripeSubscriptionId): ?PackageSubscription
    {
        return $this->createQueryBuilder('ps')
            ->andWhere('ps.stripeSubscriptionId = :stripeId')
            ->setParameter('stripeId', $stripeSubscriptionId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
