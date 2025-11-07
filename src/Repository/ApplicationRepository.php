<?php

namespace App\Repository;

use App\Entity\Application;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends ServiceEntityRepository<Application>
 */
class ApplicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Application::class);
    }

    public function getAll($offset, $perPage, $filters)
{
    $qb = $this->createQueryBuilder('a')
        ->join('a.job', 'j')                 // ✅ join related Job
        ->where('1 = 1')
        ->andWhere('j.archived = 0');        // ✅ exclude archived jobs

    if (!empty($filters['status'])) {
        $qb->andWhere('a.status IN (:status)')
           ->setParameter('status', $filters['status']);
    }

    if (!empty($filters['provider'])) {
        $qb->andWhere('a.provider = :provider')
           ->setParameter('provider', $filters['provider'], UuidType::NAME);
    }

    if (!empty($filters['employer'])) {
        $qb->andWhere('a.employer = :employer')
           ->setParameter('employer', $filters['employer'], UuidType::NAME);
    }

    $qb->orderBy('a.id', 'DESC');

    $pagerfanta = new Pagerfanta(new QueryAdapter($qb));
    $pagerfanta->setMaxPerPage($perPage);
    $pagerfanta->setCurrentPage($offset);

    return $pagerfanta;
}


    public function getProviderApplicationStatusCounts($provider): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.status, COUNT(a.id) as count')
            ->where('a.provider = :provider')->setParameter('provider', $provider, UuidType::NAME)
            ->groupBy('a.status')
            ->getQuery()
            ->getResult();
    }

    public function getEmployerApplicationStatusCounts($employer): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.status, COUNT(a.id) as count')
            ->where('a.employer = :employer')->setParameter('employer', $employer, UuidType::NAME)
            ->groupBy('a.status')
            ->getQuery()
            ->getResult();
    }

    public function getApplicationStatusCounts(): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.status, COUNT(a.id) as count')
            ->groupBy('a.status')
            ->getQuery()
            ->getResult();
    }
}
