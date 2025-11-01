<?php

namespace App\Repository;

use App\Entity\DocumentRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends ServiceEntityRepository<DocumentRequest>
 */
class DocumentRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentRequest::class);
    }

    public function getDocumentRequests($providerId)
    {
        return $this->createQueryBuilder('d')
            ->join('d.application', 'a')
            ->join('a.job', 'j')
            ->andWhere('d.provider = :providerId')
            ->setParameter('providerId', $providerId, UuidType::NAME)
            ->andWhere('a.status in (:status)')
            ->setParameter('status', ['applied', 'in_review', 'interview', 'offered', 'hired'])
            ->andWhere('j.expirationDate IS NULL OR j.expirationDate > :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->andWhere('j.verified = :verified')
            ->setParameter('verified', true)
            ->andWhere('j.blocked = :blocked')
            ->setParameter('blocked', false)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
