<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates Recruiter tables and relationships
 */
final class Version20251225_RecruiterModule extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates Recruiter tables and relationships';
    }

    public function up(Schema $schema): void
    {
        // 1. Create Recruiter Profile Table
        $this->addSql("CREATE TABLE IF NOT EXISTS `b_recruiter` (
          `id` binary(16) NOT NULL COMMENT '(DC2Type:uuid)',
          `user_id` binary(16) NOT NULL COMMENT '(DC2Type:uuid)',
          `company_name` varchar(255) DEFAULT NULL,
          `speciality` varchar(255) DEFAULT NULL COMMENT 'e.g., Locum Agency, Freelancer',
          `membership_level` varchar(50) DEFAULT 'Silver' COMMENT 'Silver, Gold, Diamond',
          `rating` decimal(3,2) DEFAULT 0.00,
          `is_verified` tinyint(1) DEFAULT 0,
          `created_at` datetime NOT NULL,
          `updated_at` datetime NOT NULL,
          PRIMARY KEY (`id`),
          CONSTRAINT `FK_RECRUITER_USER` FOREIGN KEY (`user_id`) REFERENCES `b_user` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 2. Create Job Assignment Table (The link between Employer Jobs and Recruiters)
        $this->addSql("CREATE TABLE IF NOT EXISTS `b_job_recruiter` (
          `id` binary(16) NOT NULL COMMENT '(DC2Type:uuid)',
          `job_id` binary(16) NOT NULL COMMENT '(DC2Type:uuid)',
          `recruiter_id` binary(16) NOT NULL COMMENT '(DC2Type:uuid)',
          `status` varchar(50) DEFAULT 'Assigned' COMMENT 'Assigned, Accepted, Rejected, Closed',
          `commission_rate` decimal(5,2) DEFAULT NULL,
          `assigned_at` datetime NOT NULL,
          PRIMARY KEY (`id`),
          CONSTRAINT `FK_JR_JOB` FOREIGN KEY (`job_id`) REFERENCES `b_job` (`id`) ON DELETE CASCADE,
          CONSTRAINT `FK_JR_RECRUITER` FOREIGN KEY (`recruiter_id`) REFERENCES `b_recruiter` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 3. Add Integration Columns to Existing Tables (Nullable to prevent breakage)
        // Using a stored procedure approach to check if column exists
        $this->addSql("
            SET @col_exists = 0;
            SELECT COUNT(*) INTO @col_exists 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'b_user' 
            AND COLUMN_NAME = 'recruiter_id';
            
            SET @sql = IF(@col_exists = 0,
                'ALTER TABLE `b_user` ADD COLUMN `recruiter_id` binary(16) DEFAULT NULL COMMENT ''(DC2Type:uuid)''',
                'SELECT 1');
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        $this->addSql("
            SET @col_exists = 0;
            SELECT COUNT(*) INTO @col_exists 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'b_application' 
            AND COLUMN_NAME = 'recruiter_id';
            
            SET @sql = IF(@col_exists = 0,
                'ALTER TABLE `b_application` ADD COLUMN `recruiter_id` binary(16) DEFAULT NULL COMMENT ''(DC2Type:uuid)''',
                'SELECT 1');
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        $this->addSql("
            SET @col_exists = 0;
            SELECT COUNT(*) INTO @col_exists 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'b_invoice' 
            AND COLUMN_NAME = 'recruiter_id';
            
            SET @sql = IF(@col_exists = 0,
                'ALTER TABLE `b_invoice` ADD COLUMN `recruiter_id` binary(16) DEFAULT NULL COMMENT ''(DC2Type:uuid)''',
                'SELECT 1');
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        $this->addSql("
            SET @col_exists = 0;
            SELECT COUNT(*) INTO @col_exists 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'b_document_request' 
            AND COLUMN_NAME = 'requested_by_id';
            
            SET @sql = IF(@col_exists = 0,
                'ALTER TABLE `b_document_request` ADD COLUMN `requested_by_id` binary(16) DEFAULT NULL COMMENT ''(DC2Type:uuid)''',
                'SELECT 1');
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");

        // 4. Add Constraints for Integrity
        $this->addSql("
            SET @fk_exists = 0;
            SELECT COUNT(*) INTO @fk_exists 
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'b_user' 
            AND CONSTRAINT_NAME = 'FK_USER_RECRUITER';
            
            SET @sql = IF(@fk_exists = 0,
                'ALTER TABLE `b_user` ADD CONSTRAINT `FK_USER_RECRUITER` FOREIGN KEY (`recruiter_id`) REFERENCES `b_recruiter` (`id`)',
                'SELECT 1');
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        $this->addSql("
            SET @fk_exists = 0;
            SELECT COUNT(*) INTO @fk_exists 
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'b_application' 
            AND CONSTRAINT_NAME = 'FK_APP_RECRUITER';
            
            SET @sql = IF(@fk_exists = 0,
                'ALTER TABLE `b_application` ADD CONSTRAINT `FK_APP_RECRUITER` FOREIGN KEY (`recruiter_id`) REFERENCES `b_recruiter` (`id`)',
                'SELECT 1');
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        $this->addSql("
            SET @fk_exists = 0;
            SELECT COUNT(*) INTO @fk_exists 
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'b_invoice' 
            AND CONSTRAINT_NAME = 'FK_INV_RECRUITER';
            
            SET @sql = IF(@fk_exists = 0,
                'ALTER TABLE `b_invoice` ADD CONSTRAINT `FK_INV_RECRUITER` FOREIGN KEY (`recruiter_id`) REFERENCES `b_recruiter` (`id`)',
                'SELECT 1');
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
    }

    public function down(Schema $schema): void
    {
        // Drop foreign keys first
        $this->addSql("ALTER TABLE `b_user` DROP FOREIGN KEY IF EXISTS `FK_USER_RECRUITER`");
        $this->addSql("ALTER TABLE `b_application` DROP FOREIGN KEY IF EXISTS `FK_APP_RECRUITER`");
        $this->addSql("ALTER TABLE `b_invoice` DROP FOREIGN KEY IF EXISTS `FK_INV_RECRUITER`");

        // Drop columns
        $this->addSql("ALTER TABLE `b_user` DROP COLUMN IF EXISTS `recruiter_id`");
        $this->addSql("ALTER TABLE `b_application` DROP COLUMN IF EXISTS `recruiter_id`");
        $this->addSql("ALTER TABLE `b_invoice` DROP COLUMN IF EXISTS `recruiter_id`");
        $this->addSql("ALTER TABLE `b_document_request` DROP COLUMN IF EXISTS `requested_by_id`");

        // Drop tables
        $this->addSql("DROP TABLE IF EXISTS `b_job_recruiter`");
        $this->addSql("DROP TABLE IF EXISTS `b_recruiter`");
    }
}
