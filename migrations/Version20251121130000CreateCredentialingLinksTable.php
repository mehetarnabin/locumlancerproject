<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251121130000CreateCredentialingLinksTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create credentialing_links table for storing external credentialing links';
    }

    public function up(Schema $schema): void
    {
        // Create table using addSql for proper migration tracking
        $this->addSql('CREATE TABLE IF NOT EXISTS credentialing_links (
            id INT AUTO_INCREMENT NOT NULL,
            provider_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\',
            title VARCHAR(255) NOT NULL,
            url LONGTEXT NOT NULL,
            description LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            sender VARCHAR(50) DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            INDEX IDX_provider_id (provider_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Add foreign key constraint if it doesn't exist
        $this->addSql('SET @dbname = DATABASE();
            SET @tablename = "credentialing_links";
            SET @constraintname = "FK_credentialing_links_provider";
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
    }

    public function down(Schema $schema): void
    {
        $connection = $this->connection;
        
        // Check if table exists
        $tableExists = (int) $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'credentialing_links'"
        )->fetchOne();

        if ($tableExists > 0) {
            // Drop foreign key first
            $fkExists = (int) $connection->executeQuery(
                "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'credentialing_links'
                 AND CONSTRAINT_NAME = 'FK_credentialing_links_provider'"
            )->fetchOne();

            if ($fkExists > 0) {
                $connection->executeStatement("
                    ALTER TABLE credentialing_links 
                    DROP FOREIGN KEY FK_credentialing_links_provider
                ");
            }

            $connection->executeStatement("DROP TABLE credentialing_links");
        }
    }
}

