<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251112AddHiredAtToBApplication extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing hired_at column to b_application table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE b_application ADD hired_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE b_application DROP COLUMN hired_at');
    }
}
