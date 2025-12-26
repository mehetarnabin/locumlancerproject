<?php

namespace App\Repository;

use App\Entity\JobRecruiter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JobRecruiter>
 *
 * @method JobRecruiter|null find($id, $lockMode = null, $lockVersion = null)
 * @method JobRecruiter|null findOneBy(array $criteria, array $orderBy = null)
 * @method JobRecruiter[]    findAll()
 * @method JobRecruiter[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class JobRecruiterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobRecruiter::class);
    }

    public function save(JobRecruiter $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(JobRecruiter $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
