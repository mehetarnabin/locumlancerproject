<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250131000001AddDraftFieldsToMessage extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_draft, saved_at, deleted, and deleted_at columns to b_message table';
    }

    public function up(Schema $schema): void
    {
        // Add is_draft column
        $this->addSql('SET @dbname = DATABASE();
            SET @tablename = "b_message";
            SET @columnname = "is_draft";
            SET @preparedStatement = (SELECT IF(
              (
                SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                WHERE
                  (table_name = @tablename)
                  AND (table_schema = @dbname)
                  AND (column_name = @columnname)
              ) > 0,
              "SELECT 1",
              CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " TINYINT(1) DEFAULT 0 NOT NULL")
            ));
            PREPARE alterIfNotExists FROM @preparedStatement;
            EXECUTE alterIfNotExists;
            DEALLOCATE PREPARE alterIfNotExists;');

        // Add saved_at column
        $this->addSql('SET @columnname = "saved_at";
            SET @preparedStatement = (SELECT IF(
              (
                SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                WHERE
                  (table_name = "b_message")
                  AND (table_schema = DATABASE())
                  AND (column_name = "saved_at")
              ) > 0,
              "SELECT 1",
              "ALTER TABLE b_message ADD COLUMN saved_at DATETIME DEFAULT NULL"
            ));
            PREPARE alterIfNotExists FROM @preparedStatement;
            EXECUTE alterIfNotExists;
            DEALLOCATE PREPARE alterIfNotExists;');

        // Add deleted column
        $this->addSql('SET @columnname = "deleted";
            SET @preparedStatement = (SELECT IF(
              (
                SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                WHERE
                  (table_name = "b_message")
                  AND (table_schema = DATABASE())
                  AND (column_name = "deleted")
              ) > 0,
              "SELECT 1",
              "ALTER TABLE b_message ADD COLUMN deleted TINYINT(1) DEFAULT 0 NULL"
            ));
            PREPARE alterIfNotExists FROM @preparedStatement;
            EXECUTE alterIfNotExists;
            DEALLOCATE PREPARE alterIfNotExists;');

        // Add deleted_at column
        $this->addSql('SET @columnname = "deleted_at";
            SET @preparedStatement = (SELECT IF(
              (
                SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                WHERE
                  (table_name = "b_message")
                  AND (table_schema = DATABASE())
                  AND (column_name = "deleted_at")
              ) > 0,
              "SELECT 1",
              "ALTER TABLE b_message ADD COLUMN deleted_at DATETIME DEFAULT NULL"
            ));
            PREPARE alterIfNotExists FROM @preparedStatement;
            EXECUTE alterIfNotExists;
            DEALLOCATE PREPARE alterIfNotExists;');
    }

    public function down(Schema $schema): void
    {
        // Remove columns if rolling back
        $this->addSql('ALTER TABLE b_message DROP COLUMN IF EXISTS is_draft');
        $this->addSql('ALTER TABLE b_message DROP COLUMN IF EXISTS saved_at');
        $this->addSql('ALTER TABLE b_message DROP COLUMN IF EXISTS deleted');
        $this->addSql('ALTER TABLE b_message DROP COLUMN IF EXISTS deleted_at');
    }
}

