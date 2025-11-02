<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251102AddRankToBApplication extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add rank column to b_application table';
    }

    public function up(Schema $schema): void
    {
        // Add rank column to b_application table
        $this->addSql('ALTER TABLE b_application ADD rank INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove rank column if rolling back
        $this->addSql('ALTER TABLE b_application DROP rank');
    }
}
