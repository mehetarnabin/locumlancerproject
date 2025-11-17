<?php

namespace App\Repository;

use App\Entity\Job;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends ServiceEntityRepository<Job>
 */
class JobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Job::class);
    }

    public function getAll($offset, $perPage, $filters)
    {
        $qb = $this->createQueryBuilder('j')
            ->where('1 = 1');

        if (!empty($filters['employer'])) {
            $qb->andWhere('j.employer = :employer')
                ->setParameter('employer', $filters['employer'], UuidType::NAME);
        }

        if (!empty($filters['profession'])) {
            $qb->andWhere('j.profession = :profession')
                ->setParameter('profession', $filters['profession'], UuidType::NAME);
        }

        if (array_key_exists('blocked', $filters)) {
            $qb->andWhere('j.blocked = :blocked')
                ->setParameter('blocked', $filters['blocked']);
        }

        if (!empty($filters['verified'])) {
            $qb->andWhere('j.verified = :verified')
                ->setParameter('verified', $filters['verified']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('j.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['expired']) && $filters['expired'] == false) {
            $qb->andWhere('j.expirationDate IS NULL OR j.expirationDate > :now')
                ->setParameter('now', new \DateTimeImmutable());
        }

        if (!empty($filters['timeRange'])) {
            $now = new \DateTimeImmutable();
            switch ($filters['timeRange']) {
                case 'less_than_a_week':
                    $from = $now->modify('-7 days');
                    break;
                case 'less_than_a_month':
                    $from = $now->modify('-30 days');
                    break;
                case 'anytime':
                default:
                    $from = null;
                    break;
            }

            if ($from) {
                $qb->andWhere('j.createdAt >= :createdAfter')
                    ->setParameter('createdAfter', $from);
            }
        }

        $orX = $qb->expr()->orX();

        if (!empty($filters['speciality'])) {
            $orX->add($qb->expr()->in('j.speciality', ':speciality'));
            $qb->setParameter('speciality', $filters['speciality'], Connection::PARAM_STR_ARRAY);
        }

        if (!empty($filters['state'])) {
            $orX->add($qb->expr()->in('j.state', ':state'));
            $qb->setParameter('state', $filters['state'], Connection::PARAM_STR_ARRAY);
        }

        if (!empty($filters['workType'])) {
            $orX->add($qb->expr()->in('j.workType', ':workType'));
            $qb->setParameter('workType', $filters['workType'], Connection::PARAM_STR_ARRAY);
        }

        if (!empty($filters['need'])) {
            $orX->add($qb->expr()->in('j.need', ':need'));
            $qb->setParameter('need', $filters['need'], Connection::PARAM_STR_ARRAY);
        }

        if (count($orX->getParts()) > 0) {
            $qb->andWhere($orX);
        }

        $qb->orderBy('j.id', 'DESC');

        $pagerfanta = new Pagerfanta(new QueryAdapter($qb));
        $pagerfanta->setMaxPerPage($perPage);
        $pagerfanta->setCurrentPage($offset);

        return $pagerfanta;
    }

    public function getEmployerCurrentJobs($employer): array
    {
        $query = $this->createQueryBuilder('j')
            ->andWhere('j.employer = :employer')
            ->setParameter('employer', $employer, UuidType::NAME)
            ->andWhere('j.status IN (:status)')
            ->setParameter('status', [Job::JOB_STATUS_PUBLISHED])
            ->andWhere('j.expirationDate >= :expiration_date')
            ->setParameter('expiration_date', new \DateTime())
            ->orderBy('j.id', 'DESC')
            ->getQuery();

        return $query->getResult();
    }

    public function getEmployerPastJobs($employer): array
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.employer = :employer')
            ->setParameter('employer', $employer, UuidType::NAME)
            ->andWhere('j.expirationDate <= :expiration_date')
            ->setParameter('expiration_date', new \DateTime())
            ->orderBy('j.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function getProviderMatchingJobs($filters=[])
    {
        $qb = $this->createQueryBuilder('j')
            ->where('j.status = :status')
            ->setParameter('status', Job::JOB_STATUS_PUBLISHED)
            ->andWhere('j.expirationDate <= :expiration_date')
            ->setParameter('expiration_date', new \DateTime())
        ;

        $orX = $qb->expr()->orX();

        if(!empty($filters['profession'])){
            $orX->add('j.profession = :profession');
            $qb->setParameter('profession', $filters['profession'], UuidType::NAME);
        }

        if(!empty($filters['speciality_ids'])){
            $orX->add('j.speciality IN (:speciality)');
            $qb->setParameter('speciality', $filters['speciality_ids']);
        }

        if(!empty($filters['state'])){
            $orX->add('j.state IN (:state)');
            $qb->setParameter('state', $filters['state']);
        }

        if ($orX->count() > 0) {
            $qb->andWhere($orX);
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

        // Category filter (work_type)
        if (!empty($filters['category'])) {
            $qb->andWhere('j.work_type = :category')
               ->setParameter('category', $filters['category']);
        }

        // Date posted filter (days)
        if (!empty($filters['days'])) {
            $date = new \DateTime();
            $date->modify('-' . $filters['days'] . ' days');
            $qb->andWhere('j.created_at >= :date')
               ->setParameter('date', $date);
        }

        if(!empty($filters['limit'])){
            $qb->setMaxResults($filters['limit']);
        }

        $qb->orderBy('j.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $qb->getQuery()->getResult();
    }

    public function findExpiringJobs(\DateTime $date): array
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.expirationDate = :date')
            ->setParameter('date', $date->format('Y-m-d'))
            ->andWhere('j.status = :status')
            ->setParameter('status', Job::JOB_STATUS_PUBLISHED)
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Job[] Returns an array of Job objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('j')
    //            ->andWhere('j.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('j.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Job
    //    {
    //        return $this->createQueryBuilder('j')
    //            ->andWhere('j.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
