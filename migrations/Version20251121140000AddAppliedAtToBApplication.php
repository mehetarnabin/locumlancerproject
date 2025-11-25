<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251121140000AddAppliedAtToBApplication extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add applied_at column to b_application table';
    }

    public function up(Schema $schema): void
    {
        // Check if column exists before adding it using raw SQL query
        $connection = $this->connection;
        
        // Check if applied_at column exists
        $appliedAtExists = $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'b_application' 
             AND COLUMN_NAME = 'applied_at'"
        )->fetchOne();

        // Add applied_at column if it doesn't exist - execute directly
        if ($appliedAtExists == 0) {
            $connection->executeStatement('ALTER TABLE b_application ADD COLUMN applied_at DATETIME DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        // Check if column exists before dropping it using raw SQL query
        $connection = $this->connection;
        
        // Check if applied_at column exists
        $appliedAtExists = $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'b_application' 
             AND COLUMN_NAME = 'applied_at'"
        )->fetchOne();

        // Drop column in rollback - execute directly
        if ($appliedAtExists > 0) {
            $connection->executeStatement('ALTER TABLE b_application DROP COLUMN applied_at');
        }
    }
}

