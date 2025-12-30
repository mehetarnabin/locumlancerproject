<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251218000000AddEndDateToInterview extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add end_date to Interview entity';
    }

    public function up(Schema $schema): void
    {
        $connection = $this->connection;
        
        // Check if end_date column exists
        $endDateExists = $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'b_interview' 
             AND COLUMN_NAME = 'end_date'"
        )->fetchOne();

        // Add end_date column if it doesn't exist
        if ($endDateExists == 0) {
            $connection->executeStatement('ALTER TABLE b_interview ADD COLUMN end_date DATETIME DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $connection = $this->connection;
        
        // Check if end_date column exists
        $endDateExists = $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'b_interview' 
             AND COLUMN_NAME = 'end_date'"
        )->fetchOne();

        // Drop end_date column if it exists
        if ($endDateExists > 0) {
            $connection->executeStatement('ALTER TABLE b_interview DROP COLUMN end_date');
        }
    }
}
