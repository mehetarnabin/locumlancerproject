<?php

require __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

(new Dotenv())->bootEnv(__DIR__ . '/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$em = $kernel->getContainer()->get('doctrine')->getManager();
$connection = $em->getConnection();

$sqls = [
    // Add application_id column
    "ALTER TABLE b_message ADD application_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'",
    // Add index
    "CREATE INDEX IDX_B_MESSAGE_APPLICATION_ID ON b_message (application_id)",
    // Add foreign key
    "ALTER TABLE b_message ADD CONSTRAINT FK_B_MESSAGE_APPLICATION_ID FOREIGN KEY (application_id) REFERENCES b_application (id)"
];

foreach ($sqls as $sql) {
    try {
        $connection->executeStatement($sql);
        echo "Executed: $sql\n";
    } catch (\Exception $e) {
        echo "Error executing $sql: " . $e->getMessage() . "\n";
    }
}

echo "Done.\n";
