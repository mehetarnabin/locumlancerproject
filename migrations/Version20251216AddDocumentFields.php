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
        $connection = $this->connection;
        $sm = $connection->createSchemaManager();
        $columns = $sm->listTableColumns('b_document');
        $columnNames = array_map(fn($col) => $col->getName(), $columns);
        
        // Add application_id if it doesn't exist
        if (!in_array('application_id', $columnNames)) {
            $this->addSql('ALTER TABLE b_document ADD application_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        }
        
        // Add provider_id if it doesn't exist
        if (!in_array('provider_id', $columnNames)) {
            $this->addSql('ALTER TABLE b_document ADD provider_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        }
        
        // Add file_path if it doesn't exist
        if (!in_array('file_path', $columnNames)) {
            $this->addSql('ALTER TABLE b_document ADD file_path VARCHAR(255) DEFAULT NULL');
        }
        
        // Add description if it doesn't exist
        if (!in_array('description', $columnNames)) {
            $this->addSql('ALTER TABLE b_document ADD description LONGTEXT DEFAULT NULL');
        }
        
        // Check for indexes
        $indexes = $sm->listTableIndexes('b_document');
        $indexNames = array_map(fn($idx) => $idx->getName(), $indexes);
        
        // Add indexes if they don't exist (for performance, foreign keys can be added separately if needed)
        if (in_array('application_id', $columnNames) && !in_array('IDX_1520DF103E030ACD', $indexNames)) {
            $this->addSql('CREATE INDEX IDX_1520DF103E030ACD ON b_document (application_id)');
        }
        
        if (in_array('provider_id', $columnNames) && !in_array('IDX_6CD4C1FA6DCFD9E', $indexNames)) {
            $this->addSql('CREATE INDEX IDX_6CD4C1FA6DCFD9E ON b_document (provider_id)');
        }
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
