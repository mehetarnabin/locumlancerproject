<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251109180620 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create provider_speciality join table for ManyToMany relationship';
    }

    public function up(Schema $schema): void
    {
        // Check if table already exists
        $this->addSql('CREATE TABLE IF NOT EXISTS provider_speciality (
            provider_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\',
            speciality_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\',
            INDEX IDX_PROVIDER_SPECIALITY_PROVIDER (provider_id),
            INDEX IDX_PROVIDER_SPECIALITY_SPECIALITY (speciality_id),
            PRIMARY KEY (provider_id, speciality_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Add foreign key constraints only if they don't exist
        // Check and add provider foreign key
        $this->addSql('SET @dbname = DATABASE();
            SET @tablename = "provider_speciality";
            SET @constraintname = "FK_PROVIDER_SPECIALITY_PROVIDER";
            SET @preparedStatement = (SELECT IF(
              (
                SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                WHERE
                  (table_name = @tablename)
                  AND (table_schema = @dbname)
                  AND (constraint_name = @constraintname)
              ) > 0,
              "SELECT 1",
              CONCAT("ALTER TABLE ", @tablename, " ADD CONSTRAINT ", @constraintname, " FOREIGN KEY (provider_id) REFERENCES b_provider (id) ON DELETE CASCADE")
            ));
            PREPARE alterIfNotExists FROM @preparedStatement;
            EXECUTE alterIfNotExists;
            DEALLOCATE PREPARE alterIfNotExists;');
            
        // Check and add speciality foreign key
        $this->addSql('SET @dbname = DATABASE();
            SET @tablename = "provider_speciality";
            SET @constraintname = "FK_PROVIDER_SPECIALITY_SPECIALITY";
            SET @preparedStatement = (SELECT IF(
              (
                SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                WHERE
                  (table_name = @tablename)
                  AND (table_schema = @dbname)
                  AND (constraint_name = @constraintname)
              ) > 0,
              "SELECT 1",
              CONCAT("ALTER TABLE ", @tablename, " ADD CONSTRAINT ", @constraintname, " FOREIGN KEY (speciality_id) REFERENCES b_speciality (id) ON DELETE CASCADE")
            ));
            PREPARE alterIfNotExists FROM @preparedStatement;
            EXECUTE alterIfNotExists;
            DEALLOCATE PREPARE alterIfNotExists;');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign keys first
        $this->addSql('ALTER TABLE provider_speciality DROP FOREIGN KEY FK_PROVIDER_SPECIALITY_PROVIDER');
        $this->addSql('ALTER TABLE provider_speciality DROP FOREIGN KEY FK_PROVIDER_SPECIALITY_SPECIALITY');
        
        // Drop the join table
        $this->addSql('DROP TABLE IF EXISTS provider_speciality');
    }
}
