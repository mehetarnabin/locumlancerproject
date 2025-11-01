<?php

namespace App\Repository;

use App\Entity\Cashback;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends ServiceEntityRepository<Cashback>
 */
class CashbackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cashback::class);
    }

    public function getAll($offset, $perPage, $filters)
    {
        $qb = $this->createQueryBuilder('c')
            ->where('1 = 1');

        if(!empty($filters['status'])){
            $qb->andWhere('a.status IN (:status)')->setParameter('status', $filters['status']);
        }
        if(!empty($filters['provider'])){
            $qb->andWhere('a.provider = :provider')->setParameter('provider', $filters['provider'], UuidType::NAME);
        }
        if(!empty($filters['employer'])){
            $qb->andWhere('a.employer = :employer')->setParameter('employer', $filters['employer'], UuidType::NAME);
        }

        $qb->orderBy('c.id', 'DESC');

        $pagerfanta = new Pagerfanta(new QueryAdapter($qb));
        $pagerfanta->setMaxPerPage($perPage);
        $pagerfanta->setCurrentPage($offset);

        return $pagerfanta;
    }

    //    /**
    //     * @return Cashback[] Returns an array of Cashback objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Cashback
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
