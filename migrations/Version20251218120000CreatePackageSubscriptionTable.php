<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251218120000CreatePackageSubscriptionTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create b_package_subscription table for package subscriptions';
    }

    public function up(Schema $schema): void
    {
        $connection = $this->connection;
        
        // Check if table exists
        $tableExists = (int) $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.TABLES 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'b_package_subscription'"
        )->fetchOne();

        if ($tableExists === 0) {
            // Create the b_package_subscription table
            $connection->executeStatement("
                CREATE TABLE b_package_subscription (
                    id BINARY(16) NOT NULL,
                    user_id BINARY(16) NOT NULL,
                    package_id BINARY(16) NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'pending',
                    start_date DATETIME NOT NULL,
                    end_date DATETIME NOT NULL,
                    paid_amount DECIMAL(10,2) NOT NULL,
                    transaction_id VARCHAR(255) DEFAULT NULL,
                    stripe_subscription_id VARCHAR(255) DEFAULT NULL,
                    used_job_posts INT NOT NULL DEFAULT 0,
                    used_applications INT NOT NULL DEFAULT 0,
                    remaining_job_posts INT NOT NULL DEFAULT 0,
                    remaining_applications INT NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    PRIMARY KEY (id),
                    INDEX IDX_package_subscription_user_id (user_id),
                    INDEX IDX_package_subscription_package_id (package_id),
                    INDEX idx_user_status (user_id, status),
                    INDEX idx_end_date (end_date)
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
             AND TABLE_NAME = 'b_package_subscription'"
        )->fetchOne();

        if ($tableExists > 0) {
            $connection->executeStatement("DROP TABLE b_package_subscription");
        }
    }
}
