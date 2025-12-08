<?php
// src/Repository/LinkTrackingLogRepository.php
namespace App\Repository;

use App\Entity\LinkTrackingLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LinkTrackingLog>
 */
class LinkTrackingLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LinkTrackingLog::class);
    }

    // In a repository class or custom query
    public function findActiveLinksForProvider($provider)
    {
        $qb = $this->createQueryBuilder('cl')
            ->andWhere('cl.provider = :provider')
            ->andWhere('cl.isActive = :active')
            ->setParameter('provider', $provider)
            ->setParameter('active', true);
        
        // Note: status field filtering removed as the status column is not mapped in the CredentialingLink entity
        // Status filtering will be added when the column is properly mapped via migration
        
        return $qb->orderBy('cl.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
