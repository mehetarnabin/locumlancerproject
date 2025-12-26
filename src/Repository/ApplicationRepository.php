<?php

namespace App\Repository;

use App\Entity\Application;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
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
            ->leftJoin('a.documentRequests', 'dr')
            ->leftJoin('a.reviews', 'r')
            ->addSelect('dr', 'r')
            ->where('1 = 1')
            ->addOrderBy('dr.createdAt', 'DESC'); // Explicit ordering to prevent status field access
        // Exclude archived applications
        $qb->andWhere('a.archivedAt IS NULL');

        if (!empty($filters['status'])) {
            $statuses = is_array($filters['status']) ? $filters['status'] : [$filters['status']];
            $qb->andWhere('a.status IN (:status)')
                ->setParameter('status', $statuses, Connection::PARAM_STR_ARRAY);
        }

        if (!empty($filters['provider'])) {
            $qb->andWhere('a.provider = :provider')
                ->setParameter('provider', $filters['provider'], UuidType::NAME);
        }

        if (!empty($filters['employer'])) {
            $qb->andWhere('a.employer = :employer')
                ->setParameter('employer', $filters['employer'], UuidType::NAME);
        }

        if (!empty($filters['recruiter'])) {
            $qb->andWhere('a.recruiter = :recruiter')
                ->setParameter('recruiter', $filters['recruiter'], UuidType::NAME);
        }

        // Location filter (city or state)
        if (!empty($filters['location'])) {
            $qb->andWhere('LOWER(j.city) LIKE :location OR LOWER(j.state) LIKE :location')
                ->setParameter('location', '%' . strtolower($filters['location']) . '%');
        }

        // Salary range filters
        if (!empty($filters['salaryMin'])) {
            $qb->andWhere('j.payRateHourly >= :salaryMin')
                ->setParameter('salaryMin', $filters['salaryMin']);
        }

        if (!empty($filters['salaryMax'])) {
            $qb->andWhere('j.payRateHourly <= :salaryMax')
                ->setParameter('salaryMax', $filters['salaryMax']);
        }

        // Date applied filter (days)
        if (!empty($filters['days'])) {
            $date = new \DateTime();
            $date->modify('-' . $filters['days'] . ' days');
            $qb->andWhere('a.createdAt >= :date')
                ->setParameter('date', $date);
        }

        // Job ID filter
        if (!empty($filters['jobId'])) {
            try {
                $jobIdUuid = \Symfony\Component\Uid\Uuid::fromString($filters['jobId']);
                $qb->andWhere('j.id = :jobId')
                    ->setParameter('jobId', $jobIdUuid, UuidType::NAME);
            } catch (\Exception $e) {
                // If jobId is not a valid UUID, try matching by job ID string
                $qb->andWhere('j.jobId LIKE :jobId')
                    ->setParameter('jobId', '%' . $filters['jobId'] . '%');
            }
        }

        // Category/Work Type filter
        if (!empty($filters['category'])) {
            $category = strtolower($filters['category']);
            if ($category === 'locums') {
                $qb->andWhere('j.workType = :workType')
                    ->setParameter('workType', 'locums');
            } elseif ($category === 'parttime' || $category === 'part-time') {
                $qb->andWhere('(j.workType = :workType1 OR j.workType = :workType2)')
                    ->setParameter('workType1', 'parttime')
                    ->setParameter('workType2', 'part-time');
            } elseif ($category === 'fulltime' || $category === 'full-time') {
                $qb->andWhere('(j.workType = :workType1 OR j.workType = :workType2)')
                    ->setParameter('workType1', 'fulltime')
                    ->setParameter('workType2', 'full-time');
            }
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
            ->join('a.job', 'j')
            ->where('a.provider = :provider')->setParameter('provider', $provider, UuidType::NAME)
            ->andWhere('a.archivedAt IS NULL')
            ->groupBy('a.status')
            ->getQuery()
            ->getResult();
    }

    public function getEmployerApplicationStatusCounts($employer): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.status, COUNT(a.id) as count')
            ->where('a.employer = :employer')->setParameter('employer', $employer, UuidType::NAME)
            ->andWhere('a.archivedAt IS NULL')
            ->groupBy('a.status')
            ->getQuery()
            ->getResult();
    }

    public function getRecruiterApplicationStatusCounts($recruiter): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.status, COUNT(a.id) as count')
            ->where('a.recruiter = :recruiter')->setParameter('recruiter', $recruiter, UuidType::NAME)
            ->andWhere('a.archivedAt IS NULL')
            ->groupBy('a.status')
            ->getQuery()
            ->getResult();
    }

    public function getJobApplicationStatusCounts($jobId): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.status, COUNT(a.id) as count')
            ->where('a.job = :job')->setParameter('job', $jobId, UuidType::NAME)
            ->andWhere('a.archivedAt IS NULL')
            ->groupBy('a.status')
            ->getQuery()
            ->setCacheable(false) // Disable cache to ensure fresh counts
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
