<?php
// src/Service/JobNoteService.php

namespace App\Service;

use App\Entity\JobNote;
use App\Entity\User;
use App\Entity\Job;
use App\Repository\JobNoteRepository;
use Doctrine\ORM\EntityManagerInterface;

class JobNoteService
{
    public function __construct(
        private JobNoteRepository $jobNoteRepository,
        private EntityManagerInterface $entityManager
    ) {}

    public function saveNote(User $user, Job $job, string $content): JobNote
{
    // Use a transaction to handle race conditions
    $this->entityManager->beginTransaction();
    
    try {
        error_log("💾 Starting saveNote - User: " . $user->getId() . ", Job: " . $job->getId());
        
        // Find existing note or create new one
        $note = $this->jobNoteRepository->findNoteByUserAndJob($user, $job);
        
        if (!$note) {
            error_log("📝 No existing note found - creating new one");
            $note = new JobNote();
            $note->setUser($user);
            $note->setJob($job);
        } else {
            error_log("📝 Existing note found - ID: " . $note->getId() . ", updating");
        }
        
        $note->setContent($content);
        $note->setUpdatedAt(new \DateTime());
        
        $this->entityManager->persist($note);
        $this->entityManager->flush();
        $this->entityManager->commit();
        
        error_log("✅ Note saved successfully - ID: " . $note->getId());
        
        return $note;
        
    } catch (\Exception $e) {
        $this->entityManager->rollback();
        error_log("❌ Error saving note: " . $e->getMessage());
        throw $e;
    }
}
   // src/Service/JobNoteService.php

public function getNote(User $user, Job $job): ?JobNote
{
    error_log("🔍 JobNoteService::getNote - Starting search");
    error_log("🔍 User ID: " . $user->getId()->toString());
    error_log("🔍 Job ID: " . $job->getId()->toString());
    error_log("🔍 Job Title: " . $job->getTitle());
    
    $note = $this->jobNoteRepository->findNoteByUserAndJob($user, $job);
    
    if ($note) {
        error_log("✅ JobNoteService::getNote - NOTE FOUND");
        error_log("✅ Note ID: " . $note->getId()->toString());
        error_log("✅ Note Content: '" . ($note->getContent() ?? 'NULL') . "'");
        error_log("✅ Content Length: " . strlen($note->getContent() ?? ''));
        error_log("✅ Updated At: " . ($note->getUpdatedAt() ? $note->getUpdatedAt()->format('Y-m-d H:i:s') : 'NULL'));
    } else {
        error_log("❌ JobNoteService::getNote - NO NOTE FOUND");
        // Let's try a direct approach as fallback
        $directNote = $this->entityManager->getRepository(JobNote::class)
            ->findOneBy([
                'user' => $user,
                'job' => $job
            ]);
            
        if ($directNote) {
            error_log("🔄 Fallback direct query FOUND note: " . $directNote->getId());
            $note = $directNote;
        } else {
            error_log("❌ Fallback direct query also found NO note");
        }
    }
    
    return $note;
}

public function getNoteContent(User $user, Job $job): string
{
    $note = $this->getNote($user, $job);
    
    $content = $note ? ($note->getContent() ?? '') : '';
    
    error_log("📝 JobNoteService::getNoteContent - Final content: '" . $content . "'");
    error_log("📝 Content length: " . strlen($content));
    
    return $content;
}

    public function deleteNote(User $user, Job $job): bool
    {
        $note = $this->getNote($user, $job);
        
        if ($note) {
            $this->entityManager->remove($note);
            $this->entityManager->flush();
            return true;
        }
        
        return false;
    }

    public function getUserNotes(User $user): array
    {
        return $this->jobNoteRepository->findUserNotes($user);
    }
}
