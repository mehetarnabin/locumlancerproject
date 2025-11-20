<?php

namespace App\Repository;

use App\Entity\CredentialingLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CredentialingLink>
 *
 * @method CredentialingLink|null find($id, $lockMode = null, $lockVersion = null)
 * @method CredentialingLink|null findOneBy(array $criteria, array $orderBy = null)
 * @method CredentialingLink[]    findAll()
 * @method CredentialingLink[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CredentialingLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CredentialingLink::class);
    }
}