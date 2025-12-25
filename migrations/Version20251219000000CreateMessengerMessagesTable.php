<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251219000000CreateMessengerMessagesTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create messenger_messages table for Symfony Messenger with queue_name column';
    }

    public function up(Schema $schema): void
    {
        $connection = $this->connection;
        
        // Check if table exists
        $tableExists = (int) $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.TABLES 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'messenger_messages'"
        )->fetchOne();

        if ($tableExists === 0) {
            // Create the messenger_messages table with the standard Symfony Messenger structure
            $connection->executeStatement("
                CREATE TABLE messenger_messages (
                    id BIGINT AUTO_INCREMENT NOT NULL,
                    body LONGTEXT NOT NULL,
                    headers LONGTEXT NOT NULL,
                    queue_name VARCHAR(190) NOT NULL,
                    created_at DATETIME NOT NULL,
                    available_at DATETIME NOT NULL,
                    delivered_at DATETIME DEFAULT NULL,
                    PRIMARY KEY (id),
                    INDEX IDX_75EA56E0FB7336F0 (queue_name),
                    INDEX IDX_75EA56E0E3BD61CE (available_at),
                    INDEX IDX_75EA56E016BA31DB (delivered_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            // Table exists, check if queue_name column exists
            $columnExists = (int) $connection->executeQuery(
                "SELECT COUNT(*) FROM information_schema.COLUMNS 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = 'messenger_messages' 
                 AND COLUMN_NAME = 'queue_name'"
            )->fetchOne();

            if ($columnExists === 0) {
                // Add the missing queue_name column
                $connection->executeStatement("
                    ALTER TABLE messenger_messages 
                    ADD COLUMN queue_name VARCHAR(190) NOT NULL DEFAULT 'default' AFTER headers,
                    ADD INDEX IDX_75EA56E0FB7336F0 (queue_name)
                ");
            }

            // Check if queue column exists and has a default value
            $queueColumnExists = (int) $connection->executeQuery(
                "SELECT COUNT(*) FROM information_schema.COLUMNS 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = 'messenger_messages' 
                 AND COLUMN_NAME = 'queue'"
            )->fetchOne();

            if ($queueColumnExists > 0) {
                // Check if queue column has a default value
                $queueHasDefault = (int) $connection->executeQuery(
                    "SELECT COUNT(*) FROM information_schema.COLUMNS 
                     WHERE TABLE_SCHEMA = DATABASE() 
                     AND TABLE_NAME = 'messenger_messages' 
                     AND COLUMN_NAME = 'queue'
                     AND COLUMN_DEFAULT IS NOT NULL"
                )->fetchOne();

                if ($queueHasDefault === 0) {
                    // Add default value to queue column
                    $connection->executeStatement("
                        ALTER TABLE messenger_messages 
                        MODIFY COLUMN queue VARCHAR(190) NOT NULL DEFAULT 'default'
                    ");
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        $connection = $this->connection;
        
        // Check if table exists
        $tableExists = (int) $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.TABLES 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'messenger_messages'"
        )->fetchOne();

        if ($tableExists > 0) {
            $connection->executeStatement("DROP TABLE messenger_messages");
        }
    }
}

