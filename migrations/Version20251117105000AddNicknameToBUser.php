<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251117105000AddNicknameToBUser extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nickname column to b_user table';
    }

    public function up(Schema $schema): void
    {
        // Add nickname column to b_user table
        $this->addSql('
            ALTER TABLE b_user
            ADD COLUMN nickname VARCHAR(255) DEFAULT NULL
        ');
    }

    public function down(Schema $schema): void
    {
        // Rollback
        $this->addSql('
            ALTER TABLE b_user
            DROP COLUMN nickname
        ');
    }
}

