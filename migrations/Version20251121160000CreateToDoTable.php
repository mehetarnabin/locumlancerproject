<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251121160000CreateToDoTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create to_do table for todo items';
    }

    public function up(Schema $schema): void
    {
        $connection = $this->connection;
        
        // Check if table exists
        $tableExists = (int) $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.TABLES 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'to_do'"
        )->fetchOne();

        if ($tableExists === 0) {
            // Create the to_do table
            $connection->executeStatement("
                CREATE TABLE to_do (
                    id BINARY(16) NOT NULL,
                    provider_id BINARY(16) NOT NULL,
                    employer_id BINARY(16) NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    description TEXT DEFAULT NULL,
                    type VARCHAR(50) NOT NULL,
                    document_request_id BINARY(16) DEFAULT NULL,
                    is_completed TINYINT(1) NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL,
                    completed_at DATETIME DEFAULT NULL,
                    PRIMARY KEY (id),
                    INDEX IDX_to_do_provider_id (provider_id),
                    INDEX IDX_to_do_employer_id (employer_id),
                    INDEX IDX_to_do_document_request_id (document_request_id),
                    INDEX IDX_to_do_is_completed (is_completed)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }

    public function down(Schema $schema): void
    {
        $connection = $this->connection;
        
        // Check if table exists
        $tableExists = (int) $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.TABLES 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'to_do'"
        )->fetchOne();

        if ($tableExists > 0) {
            $connection->executeStatement("DROP TABLE to_do");
        }
    }
}

