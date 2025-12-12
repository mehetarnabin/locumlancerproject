<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to add missing columns to b_document table
 * Safely checks for existing columns before adding them
 */
final class Version20251212062500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add application_id, provider_id, file_path and description columns to b_document table if they do not exist';
    }

    public function up(Schema $schema): void
    {
        $connection = $this->connection;
        $tableName = 'b_document';
        
        // Check if columns exist and add them if they don't
        $columnsToAdd = [];
        
        // Check for application_id
        $checkApplicationId = $connection->executeQuery("
            SELECT COUNT(*) as count 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = '{$tableName}' 
            AND COLUMN_NAME = 'application_id'
        ")->fetchAssociative();
        
        if ($checkApplicationId['count'] == 0) {
            $columnsToAdd[] = "ADD application_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'";
        }
        
        // Check for provider_id
        $checkProviderId = $connection->executeQuery("
            SELECT COUNT(*) as count 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = '{$tableName}' 
            AND COLUMN_NAME = 'provider_id'
        ")->fetchAssociative();
        
        if ($checkProviderId['count'] == 0) {
            $columnsToAdd[] = "ADD provider_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'";
        }
        
        // Check for file_path
        $checkFilePath = $connection->executeQuery("
            SELECT COUNT(*) as count 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = '{$tableName}' 
            AND COLUMN_NAME = 'file_path'
        ")->fetchAssociative();
        
        if ($checkFilePath['count'] == 0) {
            $columnsToAdd[] = "ADD file_path VARCHAR(255) DEFAULT NULL";
        }
        
        // Check for description
        $checkDescription = $connection->executeQuery("
            SELECT COUNT(*) as count 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = '{$tableName}' 
            AND COLUMN_NAME = 'description'
        ")->fetchAssociative();
        
        if ($checkDescription['count'] == 0) {
            $columnsToAdd[] = "ADD description LONGTEXT DEFAULT NULL";
        }
        
        // Add columns if any are missing
        if (!empty($columnsToAdd)) {
            $this->addSql("ALTER TABLE {$tableName} " . implode(', ', $columnsToAdd));
        }
        
        // Note: Foreign keys are skipped for now as they require proper indexes on referenced tables
        // The columns and indexes are the most important parts for the application to work
        // Foreign keys can be added manually later if needed
        
        // Add indexes if they don't exist and columns exist
        // Check for application_id index
        $checkIdxApplication = $connection->executeQuery("
            SELECT COUNT(*) as count 
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = '{$tableName}' 
            AND INDEX_NAME = 'IDX_1520DF103E030ACD'
        ")->fetchAssociative();
        
        if ($checkIdxApplication['count'] == 0) {
            // Re-check if column exists (might have been added above)
            $checkApplicationIdAgain = $connection->executeQuery("
                SELECT COUNT(*) as count 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = '{$tableName}' 
                AND COLUMN_NAME = 'application_id'
            ")->fetchAssociative();
            
            if ($checkApplicationIdAgain['count'] > 0) {
                $this->addSql("CREATE INDEX IDX_1520DF103E030ACD ON {$tableName} (application_id)");
            }
        }
        
        // Check for provider_id index
        $checkIdxProvider = $connection->executeQuery("
            SELECT COUNT(*) as count 
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = '{$tableName}' 
            AND INDEX_NAME = 'IDX_6CD4C1FA6DCFD9E'
        ")->fetchAssociative();
        
        if ($checkIdxProvider['count'] == 0) {
            // Re-check if column exists (might have been added above)
            $checkProviderIdAgain = $connection->executeQuery("
                SELECT COUNT(*) as count 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = '{$tableName}' 
                AND COLUMN_NAME = 'provider_id'
            ")->fetchAssociative();
            
            if ($checkProviderIdAgain['count'] > 0) {
                $this->addSql("CREATE INDEX IDX_6CD4C1FA6DCFD9E ON {$tableName} (provider_id)");
            }
        }
    }

    public function down(Schema $schema): void
    {
        $connection = $this->connection;
        $tableName = 'b_document';
        
        // Drop foreign keys if they exist
        $checkFkApplication = $connection->executeQuery("
            SELECT COUNT(*) as count 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = '{$tableName}' 
            AND CONSTRAINT_NAME = 'FK_1520DF103E030ACD'
        ")->fetchAssociative();
        
        if ($checkFkApplication['count'] > 0) {
            $this->addSql("ALTER TABLE {$tableName} DROP FOREIGN KEY FK_1520DF103E030ACD");
        }
        
        $checkFkProvider = $connection->executeQuery("
            SELECT COUNT(*) as count 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = '{$tableName}' 
            AND CONSTRAINT_NAME = 'FK_6CD4C1FA6DCFD9E'
        ")->fetchAssociative();
        
        if ($checkFkProvider['count'] > 0) {
            $this->addSql("ALTER TABLE {$tableName} DROP FOREIGN KEY FK_6CD4C1FA6DCFD9E");
        }
        
        // Drop indexes if they exist
        $checkIdxApplication = $connection->executeQuery("
            SELECT COUNT(*) as count 
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = '{$tableName}' 
            AND INDEX_NAME = 'IDX_1520DF103E030ACD'
        ")->fetchAssociative();
        
        if ($checkIdxApplication['count'] > 0) {
            $this->addSql("DROP INDEX IDX_1520DF103E030ACD ON {$tableName}");
        }
        
        $checkIdxProvider = $connection->executeQuery("
            SELECT COUNT(*) as count 
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = '{$tableName}' 
            AND INDEX_NAME = 'IDX_6CD4C1FA6DCFD9E'
        ")->fetchAssociative();
        
        if ($checkIdxProvider['count'] > 0) {
            $this->addSql("DROP INDEX IDX_6CD4C1FA6DCFD9E ON {$tableName}");
        }
        
        // Drop columns if they exist
        $columnsToDrop = [];
        
        $checkApplicationId = $connection->executeQuery("
            SELECT COUNT(*) as count 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = '{$tableName}' 
            AND COLUMN_NAME = 'application_id'
        ")->fetchAssociative();
        
        if ($checkApplicationId['count'] > 0) {
            $columnsToDrop[] = "DROP application_id";
        }
        
        $checkProviderId = $connection->executeQuery("
            SELECT COUNT(*) as count 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = '{$tableName}' 
            AND COLUMN_NAME = 'provider_id'
        ")->fetchAssociative();
        
        if ($checkProviderId['count'] > 0) {
            $columnsToDrop[] = "DROP provider_id";
        }
        
        $checkFilePath = $connection->executeQuery("
            SELECT COUNT(*) as count 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = '{$tableName}' 
            AND COLUMN_NAME = 'file_path'
        ")->fetchAssociative();
        
        if ($checkFilePath['count'] > 0) {
            $columnsToDrop[] = "DROP file_path";
        }
        
        $checkDescription = $connection->executeQuery("
            SELECT COUNT(*) as count 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = '{$tableName}' 
            AND COLUMN_NAME = 'description'
        ")->fetchAssociative();
        
        if ($checkDescription['count'] > 0) {
            $columnsToDrop[] = "DROP description";
        }
        
        if (!empty($columnsToDrop)) {
            $this->addSql("ALTER TABLE {$tableName} " . implode(', ', $columnsToDrop));
        }
    }
}
