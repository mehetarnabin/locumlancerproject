<?php
// src/Repository/JobNoteRepository.php

namespace App\Repository;

use App\Entity\JobNote;
use App\Entity\User;
use App\Entity\Job;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JobNote>
 */
class JobNoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobNote::class);
    }

    // src/Repository/JobNoteRepository.php

public function findNoteByUserAndJob(User $user, Job $job): ?JobNote
{
    try {
        error_log("🔍 Repository findNoteByUserAndJob - Starting search");
        error_log("🔍 User ID: " . $user->getId()->toString());
        error_log("🔍 Job ID: " . $job->getId()->toString());

        // Method 1: Use the binary UUIDs directly (this should work)
        $result = $this->createQueryBuilder('jn')
            ->andWhere('jn.user = :user')
            ->andWhere('jn.job = :job')
            ->setParameter('user', $user->getId()->toBinary()) // Convert to binary
            ->setParameter('job', $job->getId()->toBinary())   // Convert to binary
            ->orderBy('jn.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($result) {
            error_log("✅ Repository - Note FOUND via binary query - ID: " . $result->getId()->toString());
        } else {
            error_log("❌ Repository - No note found via binary query");
            
            // Fallback: Try with objects (might work with some Doctrine configurations)
            $result = $this->createQueryBuilder('jn')
                ->andWhere('jn.user = :user')
                ->andWhere('jn.job = :job')
                ->setParameter('user', $user)  // Use object directly
                ->setParameter('job', $job)    // Use object directly
                ->orderBy('jn.updatedAt', 'DESC')
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
        return $this->createQueryBuilder('jn')
            ->andWhere('jn.user = :user')
            ->setParameter('user', $user)
            ->orderBy('jn.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
