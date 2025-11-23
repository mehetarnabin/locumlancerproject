<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251120124000AddOriginalSubjectToMessage extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing original_subject column to b_message for forwarded message tracking';
    }

    public function up(Schema $schema): void
    {
        $connection = $this->connection;
        $columnExists = (int) $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'b_message'
             AND COLUMN_NAME = 'original_subject'"
        )->fetchOne();

        if ($columnExists === 0) {
            $connection->executeStatement(
                "ALTER TABLE b_message ADD COLUMN original_subject VARCHAR(255) DEFAULT NULL"
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
             AND COLUMN_NAME = 'original_subject'"
        )->fetchOne();

        if ($columnExists > 0) {
            $connection->executeStatement(
                "ALTER TABLE b_message DROP COLUMN original_subject"
            );
        }
    }
}

