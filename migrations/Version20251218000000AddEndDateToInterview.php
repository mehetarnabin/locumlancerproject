<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251218000000AddEndDateToInterview extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add end_date to Interview entity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE b_interview ADD end_date DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE b_interview DROP end_date');
    }
}
