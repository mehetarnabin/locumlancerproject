<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to update application statuses to new workflow:
 * applied -> shortlisted -> interviewing -> negotiating -> accepted -> completed
 */
final class Version20251223000000UpdateApplicationStatuses extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update application statuses: in_review->shortlisted, interview->interviewing, offered->negotiating, hired->accepted';
    }

    public function up(Schema $schema): void
    {
        // Map old statuses to new statuses
        $this->addSql("UPDATE b_application SET status = 'shortlisted' WHERE status = 'in_review'");
        $this->addSql("UPDATE b_application SET status = 'interviewing' WHERE status = 'interview'");
        $this->addSql("UPDATE b_application SET status = 'negotiating' WHERE status = 'offered'");
        $this->addSql("UPDATE b_application SET status = 'accepted' WHERE status = 'hired'");
    }

    public function down(Schema $schema): void
    {
        // Reverse the mapping
        $this->addSql("UPDATE b_application SET status = 'in_review' WHERE status = 'shortlisted'");
        $this->addSql("UPDATE b_application SET status = 'interview' WHERE status = 'interviewing'");
        $this->addSql("UPDATE b_application SET status = 'offered' WHERE status = 'negotiating'");
        $this->addSql("UPDATE b_application SET status = 'hired' WHERE status = 'accepted'");
    }
}

