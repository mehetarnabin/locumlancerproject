<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251112AddHiredAtAndArchivedAtToBApplication extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add hired_at and archived_at columns to b_application table';
    }

    public function up(Schema $schema): void
    {
        // Check if columns exist before adding them using raw SQL query
        $connection = $this->connection;
        
        // Check if hired_at column exists
        $hiredAtExists = $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'b_application' 
             AND COLUMN_NAME = 'hired_at'"
        )->fetchOne();
        
        // Check if archived_at column exists
        $archivedAtExists = $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'b_application' 
             AND COLUMN_NAME = 'archived_at'"
        )->fetchOne();

        // Add hired_at column if it doesn't exist - execute directly
        if ($hiredAtExists == 0) {
            $connection->executeStatement('ALTER TABLE b_application ADD COLUMN hired_at DATETIME DEFAULT NULL');
        }

        // Add archived_at column if it doesn't exist - execute directly
        if ($archivedAtExists == 0) {
            $connection->executeStatement('ALTER TABLE b_application ADD COLUMN archived_at DATETIME DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        // Check if columns exist before dropping them using raw SQL query
        $connection = $this->connection;
        
        // Check if hired_at column exists
        $hiredAtExists = $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'b_application' 
             AND COLUMN_NAME = 'hired_at'"
        )->fetchOne();
        
        // Check if archived_at column exists
        $archivedAtExists = $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'b_application' 
             AND COLUMN_NAME = 'archived_at'"
        )->fetchOne();

        // Drop columns in rollback - execute directly
        if ($hiredAtExists > 0) {
            $connection->executeStatement('ALTER TABLE b_application DROP COLUMN hired_at');
        }

        if ($archivedAtExists > 0) {
            $connection->executeStatement('ALTER TABLE b_application DROP COLUMN archived_at');
        }
    }
}
