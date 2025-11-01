<?php

namespace App\Repository;

use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function getAll($offset, $perPage, $filters)
    {
        $qb = $this->createQueryBuilder('m');

        $qb
            ->where('1 = 1')
            ->andWhere('m.parent IS NULL')
        ;

        if(!empty($filters)){
            if(array_key_exists('receiver', $filters)){
                $qb->andWhere('m.receiver = :receiver')->setParameter('receiver', $filters['receiver'], UuidType::NAME);
            }
            if(array_key_exists('sender', $filters)){
                $qb->andWhere('m.sender = :sender')->setParameter('sender', $filters['sender'], UuidType::NAME);
            }
            if(array_key_exists('keyword', $filters)){
                $qb->andWhere('m.message LIKE :keyword')->setParameter('keyword', '%'.$filters['keyword'].'%');
            }
        }

        $qb->orderBy('m.id', 'DESC');

        $pagerfanta = new Pagerfanta(new QueryAdapter($qb));
        $pagerfanta->setMaxPerPage($perPage);
        $pagerfanta->setCurrentPage($offset);

        return $pagerfanta;
    }

    //    /**
    //     * @return Message[] Returns an array of Message objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('m.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Message
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
