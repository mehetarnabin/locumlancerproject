<?php

namespace App\Repository;

use App\Entity\Package;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PackageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Package::class);
    }

    public function findActivePackages(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.target', 'ASC')
            ->addOrderBy('p.price', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActivePackagesByTarget(string $target): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.target = :target')
            ->andWhere('p.isActive = :active')
            ->setParameter('target', $target)
            ->setParameter('active', true)
            ->orderBy('p.price', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findDefaultPackage(): ?Package
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isDefault = :default')
            ->andWhere('p.isActive = :active')
            ->setParameter('default', true)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findDefaultPackageByTarget(string $target): ?Package
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.target = :target')
            ->andWhere('p.isDefault = :default')
            ->andWhere('p.isActive = :active')
            ->setParameter('target', $target)
            ->setParameter('default', true)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByType(string $type): ?Package
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.type = :type')
            ->andWhere('p.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByTypeAndTarget(string $type, string $target): ?Package
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.type = :type')
            ->andWhere('p.target = :target')
            ->andWhere('p.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('target', $target)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findSilverPackageByTarget(string $target): ?Package
    {
        return $this->findByTypeAndTarget(Package::TYPE_SILVER, $target);
    }

    public function getPackageStats(): array
    {
        $stats = [];
        $targets = [Package::TARGET_PROVIDER, Package::TARGET_EMPLOYER, Package::TARGET_RECRUITER];
        
        foreach ($targets as $target) {
            $stats[$target] = [
                'total' => $this->count(['target' => $target]),
                'active' => $this->count(['target' => $target, 'isActive' => true]),
                'default' => $this->findOneBy(['target' => $target, 'isDefault' => true, 'isActive' => true])
            ];
        }
        
        return $stats;
    }
}