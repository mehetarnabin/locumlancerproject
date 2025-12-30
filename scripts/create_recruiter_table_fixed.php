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

// Step 1: Create b_recruiter table without foreign key first
$sql1 = "CREATE TABLE IF NOT EXISTS `b_recruiter` (
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
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $conn->executeStatement($sql1);
    echo "✓ Created b_recruiter table.\n";
} catch (\Throwable $e) {
    echo "Error creating b_recruiter table: " . $e->getMessage() . "\n";
}

// Step 2: Add foreign key constraint for b_recruiter
$sql2 = "ALTER TABLE `b_recruiter` 
  ADD CONSTRAINT `FK_RECRUITER_USER` 
  FOREIGN KEY (`user_id`) REFERENCES `b_user` (`id`) ON DELETE CASCADE";

try {
    // Check if constraint already exists
    $checkConstraint = "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = 'b_recruiter' 
                        AND CONSTRAINT_NAME = 'FK_RECRUITER_USER'";
    $result = $conn->fetchAssociative($checkConstraint);
    if ($result['cnt'] == 0) {
        $conn->executeStatement($sql2);
        echo "✓ Added foreign key constraint FK_RECRUITER_USER.\n";
    } else {
        echo "✓ Foreign key constraint FK_RECRUITER_USER already exists.\n";
    }
} catch (\Throwable $e) {
    echo "Note on FK_RECRUITER_USER: " . $e->getMessage() . "\n";
}

// Step 3: Create b_job_recruiter table without foreign keys first
$sql3 = "CREATE TABLE IF NOT EXISTS `b_job_recruiter` (
  `id` binary(16) NOT NULL COMMENT '(DC2Type:uuid)',
  `job_id` binary(16) NOT NULL COMMENT '(DC2Type:uuid)',
  `recruiter_id` binary(16) NOT NULL COMMENT '(DC2Type:uuid)',
  `status` varchar(50) DEFAULT 'Assigned' COMMENT 'Assigned, Accepted, Rejected, Closed',
  `commission_rate` decimal(5,2) DEFAULT NULL,
  `assigned_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_job_id` (`job_id`),
  INDEX `idx_recruiter_id` (`recruiter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $conn->executeStatement($sql3);
    echo "✓ Created b_job_recruiter table.\n";
} catch (\Throwable $e) {
    echo "Error creating b_job_recruiter table: " . $e->getMessage() . "\n";
}

// Step 4: Add foreign key constraints for b_job_recruiter
$sql4a = "ALTER TABLE `b_job_recruiter` 
  ADD CONSTRAINT `FK_JR_JOB` 
  FOREIGN KEY (`job_id`) REFERENCES `b_job` (`id`) ON DELETE CASCADE";

$sql4b = "ALTER TABLE `b_job_recruiter` 
  ADD CONSTRAINT `FK_JR_RECRUITER` 
  FOREIGN KEY (`recruiter_id`) REFERENCES `b_recruiter` (`id`) ON DELETE CASCADE";

try {
    $checkConstraint = "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = 'b_job_recruiter' 
                        AND CONSTRAINT_NAME = 'FK_JR_JOB'";
    $result = $conn->fetchAssociative($checkConstraint);
    if ($result['cnt'] == 0) {
        $conn->executeStatement($sql4a);
        echo "✓ Added foreign key constraint FK_JR_JOB.\n";
    } else {
        echo "✓ Foreign key constraint FK_JR_JOB already exists.\n";
    }
} catch (\Throwable $e) {
    echo "Note on FK_JR_JOB: " . $e->getMessage() . "\n";
}

try {
    $checkConstraint = "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = 'b_job_recruiter' 
                        AND CONSTRAINT_NAME = 'FK_JR_RECRUITER'";
    $result = $conn->fetchAssociative($checkConstraint);
    if ($result['cnt'] == 0) {
        $conn->executeStatement($sql4b);
        echo "✓ Added foreign key constraint FK_JR_RECRUITER.\n";
    } else {
        echo "✓ Foreign key constraint FK_JR_RECRUITER already exists.\n";
    }
} catch (\Throwable $e) {
    echo "Note on FK_JR_RECRUITER: " . $e->getMessage() . "\n";
}

echo "\n✓ Database setup complete. The b_recruiter table should now exist.\n";

