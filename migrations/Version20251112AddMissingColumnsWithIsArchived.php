<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251112AddMissingColumnsWithIsArchived extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing columns to b_job and b_application, including is_archived';
    }

    public function up(Schema $schema): void
    {
        // ✅ Add missing columns to b_job
        $this->addSql('
            ALTER TABLE b_job
            ADD COLUMN annual_salary DECIMAL(10,2) DEFAULT NULL,
            ADD COLUMN monthly_salary DECIMAL(10,2) DEFAULT NULL,
            ADD COLUMN salary_type VARCHAR(50) DEFAULT NULL
        ');

        // ✅ Add missing columns to b_application
        $this->addSql('
            ALTER TABLE b_application
            ADD COLUMN hired_at DATETIME DEFAULT NULL,
            ADD COLUMN archived_at DATETIME DEFAULT NULL,
            ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0
        ');
    }

    public function down(Schema $schema): void
    {
        // Rollback
        $this->addSql('
            ALTER TABLE b_job
            DROP COLUMN annual_salary,
            DROP COLUMN monthly_salary,
            DROP COLUMN salary_type
        ');
        $this->addSql('
            ALTER TABLE b_application
            DROP COLUMN hired_at,
            DROP COLUMN archived_at,
            DROP COLUMN is_archived
        ');
    }
}
