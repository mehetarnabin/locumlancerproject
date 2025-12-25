<?php

require __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__ . '/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$em = $kernel->getContainer()->get('doctrine')->getManager();
$connection = $em->getConnection();

$schemaManager = $connection->createSchemaManager();
$columns = $schemaManager->listTableColumns('b_message');

$hasColumn = false;
foreach ($columns as $column) {
    if ($column->getName() === 'application_id') {
        $hasColumn = true;
        break;
    }
}

if ($hasColumn) {
    echo "VERIFICATION SUCCESS: Column 'application_id' exists in 'b_message'.\n";
} else {
    echo "VERIFICATION FAILED: Column 'application_id' MISSING in 'b_message'.\n";
}
