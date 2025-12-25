<?php
// Upload this file to your server (e.g. public_html/fix_schema.php) and visit it in your browser.
// Example: http://bill.flexzob.com/fix_schema.php

require __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

// Boot Symfony
(new Dotenv())->bootEnv(__DIR__ . '/.env');
$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$em = $kernel->getContainer()->get('doctrine')->getManager();
$connection = $em->getConnection();

$sqls = [
    "ALTER TABLE b_message ADD application_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'",
    "CREATE INDEX IDX_B_MESSAGE_APPLICATION_ID ON b_message (application_id)",
    "ALTER TABLE b_message ADD CONSTRAINT FK_B_MESSAGE_APPLICATION_ID FOREIGN KEY (application_id) REFERENCES b_application (id)",
    // Fix for missing notification_preferences in b_employer
    "ALTER TABLE b_employer ADD notification_preferences JSON DEFAULT NULL COMMENT '(DC2Type:json)'"
];

echo "<h1>Applying Database Fix...</h1>";

foreach ($sqls as $sql) {
    echo "<p>Executing: " . htmlspecialchars($sql) . "...<br>";
    try {
        $connection->executeStatement($sql);
        echo "<span style='color:green; font-weight:bold;'>SUCCESS</span></p>";
    } catch (\Exception $e) {
        // If column already exists to prevent duplicate error
        if (strpos($e->getMessage(), "Duplicate column") !== false) {
            echo "<span style='color:orange;'>Column already exists (Skipped)</span></p>";
        } else {
            echo "<span style='color:red;'>ERROR: " . htmlspecialchars($e->getMessage()) . "</span></p>";
        }
    }
}

echo "<h2>Done! Please delete this file from your server.</h2>";
