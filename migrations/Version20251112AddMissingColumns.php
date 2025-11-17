<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251112AddMissingColumns extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add hired_at, archived_at to b_application and annual_salary to b_job';
    }

    public function up(Schema $schema): void
    {
        // Add columns to b_application
        $this->addSql('ALTER TABLE b_application ADD COLUMN hired_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE b_application ADD COLUMN archived_at DATETIME DEFAULT NULL');

        // Add column to b_job
        $this->addSql('ALTER TABLE b_job ADD COLUMN annual_salary DECIMAL(10,2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove columns if rolling back
        $this->addSql('ALTER TABLE b_application DROP COLUMN hired_at');
        $this->addSql('ALTER TABLE b_application DROP COLUMN archived_at');
        $this->addSql('ALTER TABLE b_job DROP COLUMN annual_salary');
    }
}
