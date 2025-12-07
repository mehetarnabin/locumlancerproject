<?php

use App\Kernel;
use App\Entity\DocumentRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

require_once __DIR__ . '/vendor/autoload_runtime.php';

return function (array $context) {
    $kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
    $kernel->boot();
    $container = $kernel->getContainer();
    $em = $container->get('doctrine')->getManager();

    echo "Searching for applications with status 'completed' AND > 0 document requests...\n";
    $query = $em->createQuery("
        SELECT a 
        FROM App\Entity\Application a 
        JOIN a.documentRequests dr
        WHERE a.status = 'completed'
    ");
    $query->setMaxResults(5);
    $applications = $query->getResult();

    echo "Found " . count($applications) . " applications with requests.\n";

    foreach ($applications as $app) {
        echo "App ID: " . $app->getId() . " | Job: " . $app->getJob()->getTitle() . "\n";
        echo "  App Provider: " . ($app->getProvider() ? $app->getProvider()->getId() : 'NULL') . "\n";

        $requests = $app->getDocumentRequests();
        echo "  Document Requests (" . count($requests) . "):\n";

        $hasPending = false;
        foreach ($requests as $dr) {
            $status = $dr->getProvidedAt() ? "Fulfilled" : "Pending";
            $drProvider = $dr->getProvider();
            $drProviderId = $drProvider ? $drProvider->getId() : 'NULL';

            echo "    - DR ID: " . $dr->getId() . " | Name: " . $dr->getName() . " | Status: " . $status . "\n";

            if ($status === 'Pending') $hasPending = true;
        }

        if ($hasPending) echo "  => Has Pending Requests (Icon SHOULD show)\n";
        else echo "  => No Pending Requests (Icon SHOULD NOT show)\n";

        echo "  App Status: " . $app->getStatus() . "\n";
        echo "------------------------------------------------\n";
    }
};
