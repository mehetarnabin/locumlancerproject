<?php

namespace App\Repository;

use App\Entity\Bookmark;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends ServiceEntityRepository<Bookmark>
 */
class BookmarkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bookmark::class);
    }

    //    /**
    //     * @return Bookmark[] Returns an array of Bookmark objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('b.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Bookmark
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    public function findFilteredJobs($user, $location = null, $salaryMin = null, $salaryMax = null, $category = null, $days = null)
    {
        $qb = $this->createQueryBuilder('b')
            ->join('b.job', 'j')
            ->andWhere('b.user = :user')
            ->setParameter('user', $user, UuidType::NAME)
            ->orderBy('b.id', 'DESC');

        // Location filter (city or state)
        if ($location) {
            $qb->andWhere('LOWER(j.city) LIKE :location OR LOWER(j.state) LIKE :location')
            ->setParameter('location', '%' . strtolower($location) . '%');
        }

        // Salary range filters
        if ($salaryMin) {
            $qb->andWhere('j.payRateHourly >= :salaryMin')
            ->setParameter('salaryMin', $salaryMin);
        }

        if ($salaryMax) {
            $qb->andWhere('j.payRateHourly <= :salaryMax')
            ->setParameter('salaryMax', $salaryMax);
        }

        // Category filter (workType)
        if ($category) {
            $qb->andWhere('j.workType = :category')
            ->setParameter('category', $category);
        }

        // Date posted filter (days)
        if ($days) {
            $date = new \DateTime();
            $date->modify('-' . $days . ' days');
            $qb->andWhere('j.createdAt >= :date')
            ->setParameter('date', $date);
        }

        return $qb->getQuery()->getResult();
    }

}
