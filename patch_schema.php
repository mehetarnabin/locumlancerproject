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
$conn = $em->getConnection();

try {
    echo "Adding recruiter_id column...\n";
    $sql = "ALTER TABLE b_message ADD recruiter_id binary(16) DEFAULT NULL";
    $conn->executeStatement($sql);
    echo "Column added.\n";

    echo "Adding Foreign Key constraint...\n";
    // We need to know the exact constraint name Doctrine expects if we want strict compatibility,
    // but for now any unique index name works for functionality.
    // Doctrine usually validates by column name primarily.

    // Check if recruiter table exists first
    $check = $conn->executeQuery("SHOW TABLES LIKE 'recruiter'")->fetchOne();
    if ($check) {
        $sqlFK = "ALTER TABLE b_message ADD CONSTRAINT FK_D0C7F4D156B7A66 FOREIGN KEY (recruiter_id) REFERENCES recruiter (id)";
        $conn->executeStatement($sqlFK);
        echo "FK added.\n";
    } else {
        echo "Recruiter table not found, skipping FK.\n";
    }

    echo "Success!\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
