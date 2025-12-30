<?php
// src/Service/ApplicationNoteService.php

namespace App\Service;

use App\Entity\ApplicationNote;
use App\Entity\User;
use App\Entity\Application;
use App\Repository\ApplicationNoteRepository;
use Doctrine\ORM\EntityManagerInterface;

class ApplicationNoteService
{
    public function __construct(
        private ApplicationNoteRepository $applicationNoteRepository,
        private EntityManagerInterface $entityManager
    ) {}

    public function saveNote(User $user, Application $application, string $content): ApplicationNote
    {
        // Use a transaction to handle race conditions
        $this->entityManager->beginTransaction();
        
        try {
            error_log("💾 Starting saveNote - User: " . $user->getId() . ", Application: " . $application->getId());
            
            // Find existing note or create new one
            $note = $this->applicationNoteRepository->findNoteByUserAndApplication($user, $application);
            
            if (!$note) {
                error_log("📝 No existing note found - creating new one");
                $note = new ApplicationNote();
                $note->setUser($user);
                $note->setApplication($application);
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

    public function getNote(User $user, Application $application): ?ApplicationNote
    {
        error_log("🔍 ApplicationNoteService::getNote - Starting search");
        error_log("🔍 User ID: " . $user->getId()->toString());
        error_log("🔍 Application ID: " . $application->getId()->toString());
        
        $note = $this->applicationNoteRepository->findNoteByUserAndApplication($user, $application);
        
        if ($note) {
            error_log("✅ ApplicationNoteService::getNote - NOTE FOUND");
            error_log("✅ Note ID: " . $note->getId()->toString());
            error_log("✅ Note Content: '" . ($note->getContent() ?? 'NULL') . "'");
            error_log("✅ Content Length: " . strlen($note->getContent() ?? ''));
            error_log("✅ Updated At: " . ($note->getUpdatedAt() ? $note->getUpdatedAt()->format('Y-m-d H:i:s') : 'NULL'));
        } else {
            error_log("❌ ApplicationNoteService::getNote - NO NOTE FOUND");
            // Let's try a direct approach as fallback
            $directNote = $this->entityManager->getRepository(ApplicationNote::class)
                ->findOneBy([
                    'user' => $user,
                    'application' => $application
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

    public function getNoteContent(User $user, Application $application): string
    {
        $note = $this->getNote($user, $application);
        
        $content = $note ? ($note->getContent() ?? '') : '';
        
        error_log("📝 ApplicationNoteService::getNoteContent - Final content: '" . $content . "'");
        error_log("📝 Content length: " . strlen($content));
        
        return $content;
    }

    public function deleteNote(User $user, Application $application): bool
    {
        $note = $this->getNote($user, $application);
        
        if ($note) {
            $this->entityManager->remove($note);
            $this->entityManager->flush();
            return true;
        }
        
        return false;
    }

    public function getUserNotes(User $user): array
    {
        return $this->applicationNoteRepository->findUserNotes($user);
    }
}

