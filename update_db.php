<?php
require __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();
$connection = $em->getConnection();

$sql = "ALTER TABLE b_interview ADD end_date DATETIME DEFAULT NULL";
try {
    $connection->executeStatement($sql);
    echo "Successfully added end_date column.\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
