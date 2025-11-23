<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250131000000AddSubjectToMessage extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add subject column to b_message table';
    }

    public function up(Schema $schema): void
    {
        // Check if column already exists before adding
        $this->addSql('SET @dbname = DATABASE();
            SET @tablename = "b_message";
            SET @columnname = "subject";
            SET @preparedStatement = (SELECT IF(
              (
                SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                WHERE
                  (table_name = @tablename)
                  AND (table_schema = @dbname)
                  AND (column_name = @columnname)
              ) > 0,
              "SELECT 1",
              CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " VARCHAR(255) DEFAULT NULL")
            ));
            PREPARE alterIfNotExists FROM @preparedStatement;
            EXECUTE alterIfNotExists;
            DEALLOCATE PREPARE alterIfNotExists;');
        
        // Ensure the column has the correct definition if it exists but is different
        $this->addSql('SET @preparedStatement2 = (SELECT IF(
              (
                SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                WHERE
                  (table_name = "b_message")
                  AND (table_schema = DATABASE())
                  AND (column_name = "subject")
                  AND (data_type = "varchar" AND character_maximum_length = 255)
              ) > 0,
              "SELECT 1",
              "ALTER TABLE b_message MODIFY COLUMN subject VARCHAR(255) DEFAULT NULL"
            ));
            PREPARE alterIfDifferent FROM @preparedStatement2;
            EXECUTE alterIfDifferent;
            DEALLOCATE PREPARE alterIfDifferent;');
    }

    public function down(Schema $schema): void
    {
        // Remove column if rolling back
        $this->addSql('ALTER TABLE b_message DROP COLUMN IF EXISTS subject');
    }
}

