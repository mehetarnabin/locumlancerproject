<?php
// src/Repository/ApplicationNoteRepository.php

namespace App\Repository;

use App\Entity\ApplicationNote;
use App\Entity\User;
use App\Entity\Application;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApplicationNote>
 */
class ApplicationNoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApplicationNote::class);
    }

    public function findNoteByUserAndApplication(User $user, Application $application): ?ApplicationNote
    {
        try {
            error_log("🔍 Repository findNoteByUserAndApplication - Starting search");
            error_log("🔍 User ID: " . $user->getId()->toString());
            error_log("🔍 Application ID: " . $application->getId()->toString());

            // Method 1: Use the binary UUIDs directly
            $result = $this->createQueryBuilder('an')
                ->andWhere('an.user = :user')
                ->andWhere('an.application = :application')
                ->setParameter('user', $user->getId()->toBinary())
                ->setParameter('application', $application->getId()->toBinary())
                ->orderBy('an.updatedAt', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($result) {
                error_log("✅ Repository - Note FOUND via binary query - ID: " . $result->getId()->toString());
            } else {
                error_log("❌ Repository - No note found via binary query");
                
                // Fallback: Try with objects
                $result = $this->createQueryBuilder('an')
                    ->andWhere('an.user = :user')
                    ->andWhere('an.application = :application')
                    ->setParameter('user', $user)
                    ->setParameter('application', $application)
                    ->orderBy('an.updatedAt', 'DESC')
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($result) {
                    error_log("✅ Repository - Note FOUND via object query - ID: " . $result->getId()->toString());
                } else {
                    error_log("❌ Repository - No note found via any method");
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            error_log("❌ Repository error: " . $e->getMessage());
            return null;
        }
    }

    public function findUserNotes(User $user): array
    {
        return $this->createQueryBuilder('an')
            ->andWhere('an.user = :user')
            ->setParameter('user', $user)
            ->orderBy('an.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

