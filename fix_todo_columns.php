<?php

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

$kernel = new App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$container = $kernel->getContainer();
$connection = $container->get('doctrine.dbal.default_connection');

try {
    // Check if bookmark_id exists
    $bookmarkExists = $connection->executeQuery(
        "SELECT COUNT(*) FROM information_schema.COLUMNS 
         WHERE TABLE_SCHEMA = DATABASE() 
         AND TABLE_NAME = 'to_do' 
         AND COLUMN_NAME = 'bookmark_id'"
    )->fetchOne();

    if ($bookmarkExists == 0) {
        echo "Adding bookmark_id column...\n";
        $connection->executeStatement("
            ALTER TABLE to_do 
            ADD COLUMN bookmark_id BINARY(16) DEFAULT NULL,
            ADD INDEX IDX_to_do_bookmark_id (bookmark_id)
        ");
        echo "bookmark_id column added.\n";
    } else {
        echo "bookmark_id column already exists.\n";
    }

    // Check if job_id exists
    $jobExists = $connection->executeQuery(
        "SELECT COUNT(*) FROM information_schema.COLUMNS 
         WHERE TABLE_SCHEMA = DATABASE() 
         AND TABLE_NAME = 'to_do' 
         AND COLUMN_NAME = 'job_id'"
    )->fetchOne();

    if ($jobExists == 0) {
        echo "Adding job_id column...\n";
        $connection->executeStatement("
            ALTER TABLE to_do 
            ADD COLUMN job_id BINARY(16) DEFAULT NULL,
            ADD INDEX IDX_to_do_job_id (job_id)
        ");
        echo "job_id column added.\n";
    } else {
        echo "job_id column already exists.\n";
    }

    echo "Done!\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

