<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$em = $kernel->getContainer()->get('doctrine')->getManager();
$conn = $em->getConnection();

$sqls = [
    "CREATE TABLE IF NOT EXISTS `b_recruiter` (
      `id` binary(16) NOT NULL COMMENT '(DC2Type:uuid)',
      `user_id` binary(16) NOT NULL COMMENT '(DC2Type:uuid)',
      `company_name` varchar(255) DEFAULT NULL,
      `speciality` varchar(255) DEFAULT NULL COMMENT 'e.g., Locum Agency, Freelancer',
      `membership_level` varchar(50) DEFAULT 'Silver' COMMENT 'Silver, Gold, Diamond',
      `rating` decimal(3,2) DEFAULT 0.00,
      `is_verified` tinyint(1) DEFAULT 0,
      `created_at` datetime NOT NULL,
      `updated_at` datetime NOT NULL,
      PRIMARY KEY (`id`),
      CONSTRAINT `FK_RECRUITER_USER` FOREIGN KEY (`user_id`) REFERENCES `b_user` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `b_job_recruiter` (
      `id` binary(16) NOT NULL COMMENT '(DC2Type:uuid)',
      `job_id` binary(16) NOT NULL COMMENT '(DC2Type:uuid)',
      `recruiter_id` binary(16) NOT NULL COMMENT '(DC2Type:uuid)',
      `status` varchar(50) DEFAULT 'Assigned' COMMENT 'Assigned, Accepted, Rejected, Closed',
      `commission_rate` decimal(5,2) DEFAULT NULL,
      `assigned_at` datetime NOT NULL,
      PRIMARY KEY (`id`),
      CONSTRAINT `FK_JR_JOB` FOREIGN KEY (`job_id`) REFERENCES `b_job` (`id`) ON DELETE CASCADE,
      CONSTRAINT `FK_JR_RECRUITER` FOREIGN KEY (`recruiter_id`) REFERENCES `b_recruiter` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

// Columns (Check if exist before adding to avoid errors)
$columns = [
    ['b_user', 'recruiter_id', "ALTER TABLE `b_user` ADD COLUMN `recruiter_id` binary(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'"],
    ['b_application', 'recruiter_id', "ALTER TABLE `b_application` ADD COLUMN `recruiter_id` binary(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'"],
    ['b_invoice', 'recruiter_id', "ALTER TABLE `b_invoice` ADD COLUMN `recruiter_id` binary(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'"],
    ['b_document_request', 'requested_by_id', "ALTER TABLE `b_document_request` ADD COLUMN `requested_by_id` binary(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'"],
];

// Constraints (Blindly try or check? Valid constraints usually throw error if exists, so we catch)
$constraints = [
    "ALTER TABLE `b_user` ADD CONSTRAINT `FK_USER_RECRUITER` FOREIGN KEY (`recruiter_id`) REFERENCES `b_recruiter` (`id`)",
    "ALTER TABLE `b_application` ADD CONSTRAINT `FK_APP_RECRUITER` FOREIGN KEY (`recruiter_id`) REFERENCES `b_recruiter` (`id`)",
    "ALTER TABLE `b_invoice` ADD CONSTRAINT `FK_INV_RECRUITER` FOREIGN KEY (`recruiter_id`) REFERENCES `b_recruiter` (`id`)",
];

foreach ($sqls as $sql) {
    try {
        $conn->executeStatement($sql);
        echo "Executed Table creation.\n";
    } catch (\Throwable $e) {
        echo "Error creating table: " . $e->getMessage() . "\n";
    }
}

foreach ($columns as $col) {
    $schema = $conn->createSchemaManager();
    $tableColumns = $schema->listTableColumns($col[0]);
    $exists = false;
    foreach ($tableColumns as $c) {
        if ($c->getName() === $col[1]) {
            $exists = true;
            break;
        }
    }

    if (!$exists) {
        try {
            $conn->executeStatement($col[2]);
            echo "Added column {$col[1]} to {$col[0]}.\n";
        } catch (\Throwable $e) {
            echo "Error adding column {$col[1]}: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Column {$col[1]} already exists in {$col[0]}.\n";
    }
}

foreach ($constraints as $sql) {
    try {
        $conn->executeStatement($sql);
        echo "Executed constraint.\n";
    } catch (\Throwable $e) {
        // Ignore duplicate constraint errors usually
        echo "Note on constraint: " . $e->getMessage() . "\n";
    }
}

echo "Database setup complete.\n";
