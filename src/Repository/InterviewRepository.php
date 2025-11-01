<?php

namespace App\Repository;

use App\Entity\Interview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends ServiceEntityRepository<Interview>
 */
class InterviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Interview::class);
    }

    public function getEmployerInterviews($employer)
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.application', 'a')
            ->andWhere('a.employer = :employer')
            ->setParameter('employer', $employer, UuidType::NAME)
            ->getQuery()
            ->getResult()
        ;
    }

    public function getProviderInterviews($provider)
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.application', 'a')
            ->andWhere('a.provider = :provider')
            ->setParameter('provider', $provider, UuidType::NAME)
            ->getQuery()
            ->getResult()
            ;
    }

    //    /**
    //     * @return Interview[] Returns an array of Interview objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('i.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Interview
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
