<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251226_RecruiterToDo extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds recruiter_id to to_do table';
    }

    public function up(Schema $schema): void
    {
        // Check if column exists to avoid errors if run multiple times or manually applied
        // But strictly speaking, migration should just declare state. 
        // We add "if not exists" logic implicitly by Doctrine usually, but here I'll just put standard SQL.

        $this->addSql("ALTER TABLE to_do ADD recruiter_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'");
        $this->addSql("ALTER TABLE to_do ADD CONSTRAINT FK_D20630D2156BE243 FOREIGN KEY (recruiter_id) REFERENCES b_recruiter (id)");
        $this->addSql("CREATE INDEX IDX_D20630D2156BE243 ON to_do (recruiter_id)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE to_do DROP FOREIGN KEY FK_D20630D2156BE243");
        $this->addSql("ALTER TABLE to_do DROP INDEX IDX_D20630D2156BE243");
        $this->addSql("ALTER TABLE to_do DROP recruiter_id");
    }
}
