<?php

require __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__ . '/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$container = $kernel->getContainer();
/** @var EntityManagerInterface $em */
$em = $container->get('doctrine')->getManager();

try {
    $conn = $em->getConnection();
    $sql = "DESCRIBE b_user";
    $stmt = $conn->prepare($sql);
    $result = $stmt->executeQuery()->fetchAllAssociative();

    echo "Columns in b_message:\n";
    foreach ($result as $column) {
        echo $column['Field'] . " - " . $column['Type'] . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
