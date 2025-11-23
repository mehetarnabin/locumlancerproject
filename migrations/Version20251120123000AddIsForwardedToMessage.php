<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251120123000AddIsForwardedToMessage extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure is_forwarded column exists on b_message table for forwarded message tracking';
    }

    public function up(Schema $schema): void
    {
        $connection = $this->connection;
        $columnExists = (int) $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'b_message' 
             AND COLUMN_NAME = 'is_forwarded'"
        )->fetchOne();

        if ($columnExists === 0) {
            $connection->executeStatement(
                "ALTER TABLE b_message ADD COLUMN is_forwarded TINYINT(1) NOT NULL DEFAULT 0"
            );
        }
    }

    public function down(Schema $schema): void
    {
        $connection = $this->connection;
        $columnExists = (int) $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'b_message' 
             AND COLUMN_NAME = 'is_forwarded'"
        )->fetchOne();

        if ($columnExists > 0) {
            $connection->executeStatement(
                "ALTER TABLE b_message DROP COLUMN is_forwarded"
            );
        }
    }
}

