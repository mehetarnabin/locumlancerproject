<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251105AddArchivedToBJob extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add archived column to b_job table';
    }

    public function up(Schema $schema): void
    {
        // Add archived column to b_job table
        $this->addSql('ALTER TABLE b_job ADD archived TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove archived column in case of rollback
        $this->addSql('ALTER TABLE b_job DROP COLUMN archived');
    }
}
