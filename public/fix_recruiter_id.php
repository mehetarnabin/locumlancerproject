<?php

// This file can be accessed via web browser to fix the recruiter_id issue
// Access it at: http://your-domain/fix_recruiter_id.php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Kernel;
use App\Entity\ToDo;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$em = $kernel->getContainer()->get('doctrine')->getManager();
$conn = $em->getConnection();

header('Content-Type: text/plain');
echo "=== FIXING recruiter_id ISSUE (Web Context) ===\n\n";

// 1. Verify column exists
echo "1. Checking database...\n";
$columnExists = $conn->executeQuery(
    "SELECT COUNT(*) as cnt FROM information_schema.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'to_do' 
     AND COLUMN_NAME = 'recruiter_id'"
)->fetchAssociative();

if ($columnExists['cnt'] == 0) {
    echo "   ❌ Column missing! Adding...\n";
    try {
        $conn->executeStatement("
            ALTER TABLE to_do 
            ADD COLUMN recruiter_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            ADD INDEX IDX_to_do_recruiter_id (recruiter_id)
        ");
        echo "   ✓ Column added\n";
    } catch (\Exception $e) {
        echo "   ❌ Error: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "   ✓ Column exists\n";
}

// 2. Clear ALL caches in web context
echo "\n2. Clearing Doctrine caches (web context)...\n";
$config = $em->getConfiguration();

if ($config->getMetadataCache()) {
    $config->getMetadataCache()->clear();
    echo "   ✓ Metadata cache cleared\n";
}

if ($config->getQueryCache()) {
    $config->getQueryCache()->clear();
    echo "   ✓ Query cache cleared\n";
}

if ($config->getResultCache()) {
    $config->getResultCache()->clear();
    echo "   ✓ Result cache cleared\n";
}

// 3. Force metadata reload
echo "\n3. Forcing metadata reload...\n";
try {
    $metadataFactory = $em->getMetadataFactory();
    // Force reload by getting metadata
    $metadata = $em->getClassMetadata(ToDo::class);
    
    // Clear and reload
    $metadataFactory->evictEntityMetadata(ToDo::class);
    $metadata = $em->getClassMetadata(ToDo::class);
    
    echo "   ✓ Metadata reloaded\n";
    echo "   - Has recruiter: " . ($metadata->hasAssociation('recruiter') ? 'YES' : 'NO') . "\n";
    
    if ($metadata->hasAssociation('recruiter')) {
        $mapping = $metadata->getAssociationMapping('recruiter');
        echo "   - Join column: " . ($mapping['joinColumns'][0]['name'] ?? 'N/A') . "\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 4. Test the query
echo "\n4. Testing ToDo query...\n";
try {
    $todos = $em->getRepository(ToDo::class)->findBy(
        ['isCompleted' => false],
        ['createdAt' => 'DESC'],
        5
    );
    echo "   ✓ Query SUCCESS! Found " . count($todos) . " todos\n";
} catch (\Exception $e) {
    echo "   ❌ Query FAILED: " . $e->getMessage() . "\n";
    if ($e->getPrevious()) {
        echo "   Previous: " . $e->getPrevious()->getMessage() . "\n";
    }
}

echo "\n✅ Fix complete! Please delete this file after use for security.\n";

