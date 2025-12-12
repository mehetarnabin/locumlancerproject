<?php

// Simple script to update b_document table with new columns
require 'vendor/autoload.php';

try {
    $dsn = 'mysql:host=localhost;dbname=locumlancer';
    $pdo = new PDO($dsn, 'root', '');
    
    // Check if columns exist and add if not
    $columns_sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='b_document' AND TABLE_SCHEMA='locumlancer'";
    $stmt = $pdo->query($columns_sql);
    $existing_columns = array_map(fn($row) => $row['COLUMN_NAME'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    $alterStatements = [];
    
    if (!in_array('application_id', $existing_columns)) {
        $alterStatements[] = "ADD COLUMN application_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'";
    }
    
    if (!in_array('provider_id', $existing_columns)) {
        $alterStatements[] = "ADD COLUMN provider_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'";
    }
    
    if (!in_array('file_path', $existing_columns)) {
        $alterStatements[] = "ADD COLUMN file_path VARCHAR(255) DEFAULT NULL";
    }
    
    if (!in_array('description', $existing_columns)) {
        $alterStatements[] = "ADD COLUMN description LONGTEXT DEFAULT NULL";
    }
    
    if (!empty($alterStatements)) {
        $sql = "ALTER TABLE b_document " . implode(", ", $alterStatements);
        $pdo->exec($sql);
        echo "✓ Columns added to b_document table\n";
    } else {
        echo "✓ All columns already exist in b_document table\n";
    }
    
    // Add indexes
    $indexCheckSql = "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='b_document' AND COLUMN_NAME IN ('application_id', 'provider_id') AND TABLE_SCHEMA='locumlancer'";
    $stmt = $pdo->query($indexCheckSql);
    $existing_indexes = array_map(fn($row) => $row['CONSTRAINT_NAME'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    if (!in_array('FK_1520DF103E030ACD', $existing_indexes)) {
        try {
            $pdo->exec("ALTER TABLE b_document ADD CONSTRAINT FK_1520DF103E030ACD FOREIGN KEY (application_id) REFERENCES b_application (id) ON DELETE SET NULL");
            echo "✓ Added foreign key FK_1520DF103E030ACD\n";
        } catch (Exception $e) {
            echo "! FK_1520DF103E030ACD already exists or error: " . $e->getMessage() . "\n";
        }
    }
    
    if (!in_array('FK_6CD4C1FA6DCFD9E', $existing_indexes)) {
        try {
            $pdo->exec("ALTER TABLE b_document ADD CONSTRAINT FK_6CD4C1FA6DCFD9E FOREIGN KEY (provider_id) REFERENCES b_user (id) ON DELETE SET NULL");
            echo "✓ Added foreign key FK_6CD4C1FA6DCFD9E\n";
        } catch (Exception $e) {
            echo "! FK_6CD4C1FA6DCFD9E already exists or error: " . $e->getMessage() . "\n";
        }
    }
    
    // Add indexes
    $sql = "SHOW INDEXES FROM b_document WHERE Column_name IN ('application_id', 'provider_id')";
    $stmt = $pdo->query($sql);
    $existing_idx = array_map(fn($row) => $row['Column_name'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    if (!in_array('application_id', $existing_idx)) {
        $pdo->exec("CREATE INDEX IDX_1520DF103E030ACD ON b_document (application_id)");
        echo "✓ Added index IDX_1520DF103E030ACD\n";
    }
    
    if (!in_array('provider_id', $existing_idx)) {
        $pdo->exec("CREATE INDEX IDX_6CD4C1FA6DCFD9E ON b_document (provider_id)");
        echo "✓ Added index IDX_6CD4C1FA6DCFD9E\n";
    }
    
    echo "\n✓ Database migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
