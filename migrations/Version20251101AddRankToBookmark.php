<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251101AddRankToBBookmark extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add rank column to b_bookmark table';
    }

    public function up(Schema $schema): void
    {
        // Add rank column to your actual table b_bookmark
        $this->addSql('ALTER TABLE b_bookmark ADD rank INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE b_bookmark DROP rank');
    }
}
