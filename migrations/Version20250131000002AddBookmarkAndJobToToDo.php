<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250131000002AddBookmarkAndJobToToDo extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add bookmark_id and job_id columns to to_do table';
    }

    public function up(Schema $schema): void
    {
        $connection = $this->connection;
        
        // Check if bookmark_id column exists
        $bookmarkColumnExists = (int) $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'to_do' 
             AND COLUMN_NAME = 'bookmark_id'"
        )->fetchOne();

        if ($bookmarkColumnExists === 0) {
            $connection->executeStatement("
                ALTER TABLE to_do 
                ADD COLUMN bookmark_id BINARY(16) DEFAULT NULL,
                ADD INDEX IDX_to_do_bookmark_id (bookmark_id)
            ");
        }

        // Check if job_id column exists
        $jobColumnExists = (int) $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'to_do' 
             AND COLUMN_NAME = 'job_id'"
        )->fetchOne();

        if ($jobColumnExists === 0) {
            $connection->executeStatement("
                ALTER TABLE to_do 
                ADD COLUMN job_id BINARY(16) DEFAULT NULL,
                ADD INDEX IDX_to_do_job_id (job_id)
            ");
        }
    }

    public function down(Schema $schema): void
    {
        $connection = $this->connection;
        
        // Check if bookmark_id column exists before dropping
        $bookmarkColumnExists = (int) $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'to_do' 
             AND COLUMN_NAME = 'bookmark_id'"
        )->fetchOne();

        if ($bookmarkColumnExists > 0) {
            $connection->executeStatement("
                ALTER TABLE to_do 
                DROP INDEX IDX_to_do_bookmark_id,
                DROP COLUMN bookmark_id
            ");
        }

        // Check if job_id column exists before dropping
        $jobColumnExists = (int) $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'to_do' 
             AND COLUMN_NAME = 'job_id'"
        )->fetchOne();

        if ($jobColumnExists > 0) {
            $connection->executeStatement("
                ALTER TABLE to_do 
                DROP INDEX IDX_to_do_job_id,
                DROP COLUMN job_id
            ");
        }
    }
}

