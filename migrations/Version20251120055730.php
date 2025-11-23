<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251120055730 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make receiver_id nullable in b_message table to allow drafts without receiver';
    }

    public function up(Schema $schema): void
    {
        // Make receiver_id nullable to allow drafts without a receiver
        // Note: Column type is binary(16) for UUID storage
        $this->addSql('SET @dbname = DATABASE();
            SET @tablename = "b_message";
            SET @columnname = "receiver_id";
            SET @preparedStatement = (SELECT IF(
              (
                SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                WHERE
                  (table_name = @tablename)
                  AND (table_schema = @dbname)
                  AND (column_name = @columnname)
                  AND (is_nullable = "YES")
              ) > 0,
              "SELECT 1",
              CONCAT("ALTER TABLE ", @tablename, " MODIFY COLUMN ", @columnname, " BINARY(16) DEFAULT NULL")
            ));
            PREPARE alterIfNotExists FROM @preparedStatement;
            EXECUTE alterIfNotExists;
            DEALLOCATE PREPARE alterIfNotExists;');
    }

    public function down(Schema $schema): void
    {
        // Revert receiver_id to NOT NULL (only if no null values exist)
        // Note: Column type is binary(16) for UUID storage
        $this->addSql('SET @dbname = DATABASE();
            SET @tablename = "b_message";
            SET @columnname = "receiver_id";
            SET @preparedStatement = (SELECT IF(
              (
                SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                WHERE
                  (table_name = @tablename)
                  AND (table_schema = @dbname)
                  AND (column_name = @columnname)
                  AND (is_nullable = "NO")
              ) > 0,
              "SELECT 1",
              CONCAT("ALTER TABLE ", @tablename, " MODIFY COLUMN ", @columnname, " BINARY(16) NOT NULL")
            ));
            PREPARE alterIfNotExists FROM @preparedStatement;
            EXECUTE alterIfNotExists;
            DEALLOCATE PREPARE alterIfNotExists;');
    }
}
