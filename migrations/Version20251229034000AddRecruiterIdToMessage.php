<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add recruiter_id column to b_message table if it doesn't exist
 */
final class Version20251229034000AddRecruiterIdToMessage extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add recruiter_id column to b_message table';
    }

    public function up(Schema $schema): void
    {
        $connection = $this->connection;
        
        // Check if recruiter_id column exists in b_message
        $recruiterIdExists = $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'b_message' 
             AND COLUMN_NAME = 'recruiter_id'"
        )->fetchOne();

        // Add recruiter_id column if it doesn't exist
        if ($recruiterIdExists == 0) {
            $connection->executeStatement("
                ALTER TABLE b_message 
                ADD COLUMN recruiter_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'
            ");
            
            // Add index
            $connection->executeStatement("
                ALTER TABLE b_message 
                ADD INDEX IDX_b_message_recruiter_id (recruiter_id)
            ");
            
            // Add foreign key constraint if b_recruiter table exists
            $recruiterTableExists = $connection->executeQuery(
                "SELECT COUNT(*) FROM information_schema.TABLES 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = 'b_recruiter'"
            )->fetchOne();
            
            if ($recruiterTableExists > 0) {
                // Check if foreign key already exists
                $fkExists = $connection->executeQuery(
                    "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
                     WHERE TABLE_SCHEMA = DATABASE() 
                     AND TABLE_NAME = 'b_message' 
                     AND CONSTRAINT_NAME = 'FK_b_message_recruiter_id'"
                )->fetchOne();
                
                if ($fkExists == 0) {
                    $connection->executeStatement("
                        ALTER TABLE b_message 
                        ADD CONSTRAINT FK_b_message_recruiter_id 
                        FOREIGN KEY (recruiter_id) REFERENCES b_recruiter (id)
                    ");
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        $connection = $this->connection;
        
        // Check if recruiter_id column exists
        $recruiterIdExists = $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'b_message' 
             AND COLUMN_NAME = 'recruiter_id'"
        )->fetchOne();

        // Drop foreign key first if it exists
        if ($recruiterIdExists > 0) {
            $fkExists = $connection->executeQuery(
                "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = 'b_message' 
                 AND CONSTRAINT_NAME = 'FK_b_message_recruiter_id'"
            )->fetchOne();
            
            if ($fkExists > 0) {
                $connection->executeStatement('ALTER TABLE b_message DROP FOREIGN KEY FK_b_message_recruiter_id');
            }
            
            // Drop index if it exists
            $idxExists = $connection->executeQuery(
                "SELECT COUNT(*) FROM information_schema.STATISTICS 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = 'b_message' 
                 AND INDEX_NAME = 'IDX_b_message_recruiter_id'"
            )->fetchOne();
            
            if ($idxExists > 0) {
                $connection->executeStatement('ALTER TABLE b_message DROP INDEX IDX_b_message_recruiter_id');
            }
            
            // Drop column
            $connection->executeStatement('ALTER TABLE b_message DROP COLUMN recruiter_id');
        }
    }
}

