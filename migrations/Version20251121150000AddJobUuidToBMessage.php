<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251121150000AddJobUuidToBMessage extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add job_uuid column to b_message table for job relationship';
    }

    public function up(Schema $schema): void
    {
        $connection = $this->connection;
        
        // Check if job_uuid column exists
        $jobUuidExists = (int) $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'b_message' 
             AND COLUMN_NAME = 'job_uuid'"
        )->fetchOne();

        // Add job_uuid column if it doesn't exist
        if ($jobUuidExists === 0) {
            $connection->executeStatement(
                "ALTER TABLE b_message ADD COLUMN job_uuid BINARY(16) DEFAULT NULL"
            );
            
            // Add index for better performance
            $connection->executeStatement(
                "CREATE INDEX IDX_b_message_job_uuid ON b_message (job_uuid)"
            );
        }
    }

    public function down(Schema $schema): void
    {
        $connection = $this->connection;
        
        // Check if job_uuid column exists
        $jobUuidExists = (int) $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'b_message' 
             AND COLUMN_NAME = 'job_uuid'"
        )->fetchOne();

        // Drop index and column
        if ($jobUuidExists > 0) {
            // Check if index exists before dropping
            $indexExists = (int) $connection->executeQuery(
                "SELECT COUNT(*) FROM information_schema.STATISTICS 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = 'b_message' 
                 AND INDEX_NAME = 'IDX_b_message_job_uuid'"
            )->fetchOne();
            
            if ($indexExists > 0) {
                $connection->executeStatement(
                    "ALTER TABLE b_message DROP INDEX IDX_b_message_job_uuid"
                );
            }
            
            // Drop column
            $connection->executeStatement(
                "ALTER TABLE b_message DROP COLUMN job_uuid"
            );
        }
    }
}

