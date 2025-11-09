<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251107AddArchivedToBApplication extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add archived column to b_application table';
    }

    public function up(Schema $schema): void
    {
        // Add archived column to b_application table
        $this->addSql('ALTER TABLE b_application ADD archived TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove archived column in case of rollback
        $this->addSql('ALTER TABLE b_application DROP COLUMN archived');
    }
}

