<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251216AddDocumentFields extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add application, provider, filePath and description fields to Document entity';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE b_document ADD application_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD provider_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD file_path VARCHAR(255) DEFAULT NULL, ADD description LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE b_document ADD CONSTRAINT FK_1520DF103E030ACD FOREIGN KEY (application_id) REFERENCES b_application (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE b_document ADD CONSTRAINT FK_6CD4C1FA6DCFD9E FOREIGN KEY (provider_id) REFERENCES b_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_1520DF103E030ACD ON b_document (application_id)');
        $this->addSql('CREATE INDEX IDX_6CD4C1FA6DCFD9E ON b_document (provider_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE b_document DROP FOREIGN KEY FK_1520DF103E030ACD');
        $this->addSql('ALTER TABLE b_document DROP FOREIGN KEY FK_6CD4C1FA6DCFD9E');
        $this->addSql('DROP INDEX IDX_1520DF103E030ACD ON b_document');
        $this->addSql('DROP INDEX IDX_6CD4C1FA6DCFD9E ON b_document');
        $this->addSql('ALTER TABLE b_document DROP application_id, DROP provider_id, DROP file_path, DROP description');
    }
}
