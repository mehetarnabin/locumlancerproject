<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function getAll($offset, $perPage, $filters)
    {
        $qb = $this->createQueryBuilder('u')
            ->where('1 = 1');

        if (!empty($filters['userType'])) {
            $qb->andWhere('u.userType = :userType')
                ->setParameter('userType', $filters['userType']);
        }

        $qb->orderBy('u.id', 'DESC');

        $pagerfanta = new Pagerfanta(new QueryAdapter($qb));
        $pagerfanta->setMaxPerPage($perPage);
        $pagerfanta->setCurrentPage($offset);

        return $pagerfanta;
    }

    public function getProvidersForMessage($employerId)
    {
        $qb = $this->createQueryBuilder('u')
            ->where('1 = 1')
            ->join('u.provider', 'p')
            ->join('p.applications', 'a')
            ->andWhere('u.userType = :userType')
            ->setParameter('userType', User::TYPE_PROVIDER)
            ->andWhere('a.employer = :employerId')
            ->setParameter('employerId', $employerId, UuidType::NAME);
        ;

        $qb->orderBy('u.id', 'DESC');

        return $qb->getQuery()->getResult();
    }

    public function getEmployersForMessage($providerId)
    {
        $qb = $this->createQueryBuilder('u')
            ->where('1 = 1')
            ->join('u.employer', 'e')
            ->join('e.applications', 'a')
            ->andWhere('u.userType = :userType')
            ->setParameter('userType', User::TYPE_EMPLOYER)
            ->andWhere('a.provider = :providerId')
            ->setParameter('providerId', $providerId, UuidType::NAME);
        ;

        $qb->orderBy('u.id', 'DESC');

        return $qb->getQuery()->getResult();
    }

    public function getUsersForMessage()
    {
        $qb = $this->createQueryBuilder('u')
            ->where('1 = 1')
            ->andWhere('u.blocked = :blocked')
            ->setParameter('blocked', false)
        ;

        $qb->orderBy('u.name', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
