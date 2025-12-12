<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fix: Ensure b_document_request table doesn't have status column
 * This migration ensures the table structure matches the entity definition
 */
final class Version20250131000003FixDocumentRequestStatusIssue extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure b_document_request table structure is correct (no status column)';
    }

    public function up(Schema $schema): void
    {
        $connection = $this->connection;
        
        // Check if status column exists in b_document_request
        $statusExists = (int) $connection->executeQuery(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'b_document_request' 
             AND COLUMN_NAME = 'status'"
        )->fetchOne();

        // If status column exists, drop it (it shouldn't exist based on entity definition)
        if ($statusExists > 0) {
            $this->addSql('ALTER TABLE b_document_request DROP COLUMN status');
            $this->write('Dropped status column from b_document_request table');
        } else {
            $this->write('b_document_request table structure is correct (no status column found)');
        }
    }

    public function down(Schema $schema): void
    {
        // We don't want to add the status column back as it shouldn't exist
        // This migration is idempotent and safe to run multiple times
    }
}

