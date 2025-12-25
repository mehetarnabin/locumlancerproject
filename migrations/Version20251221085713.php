<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251221085713 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notification_preferences column to b_employer table';
    }

    public function up(Schema $schema): void
    {
        $connection = $this->connection;
        $sm = $connection->createSchemaManager();
        $columns = $sm->listTableColumns('b_employer');
        $columnNames = array_map(fn($col) => $col->getName(), $columns);
        
        // Add notification_preferences if it doesn't exist
        if (!in_array('notification_preferences', $columnNames)) {
            $this->addSql('ALTER TABLE b_employer ADD notification_preferences JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        }
    }

    public function down(Schema $schema): void
    {
        $connection = $this->connection;
        $sm = $connection->createSchemaManager();
        $columns = $sm->listTableColumns('b_employer');
        $columnNames = array_map(fn($col) => $col->getName(), $columns);
        
        // Remove notification_preferences if it exists
        if (in_array('notification_preferences', $columnNames)) {
            $this->addSql('ALTER TABLE b_employer DROP notification_preferences');
        }
    }
}
