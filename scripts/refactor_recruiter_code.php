<?php

$dir = __DIR__ . '/../src/Controller/Recruiter';
$templateDir = __DIR__ . '/../templates/recruiter';

function replaceInFile($filePath, $replacements)
{
    if (!file_exists($filePath)) {
        echo "File not found: $filePath\n";
        return;
    }
    $content = file_get_contents($filePath);
    $newContent = strtr($content, $replacements);
    if ($content !== $newContent) {
        file_put_contents($filePath, $newContent);
        echo "Updated $filePath\n";
    }
}

// 1. Refactor Controllers
$controllerReplacements = [
    'namespace App\Controller\Employer;' => 'namespace App\Controller\Recruiter;',
    "#[Route('/employer'" => "#[Route('/recruiter'",
    "#[Route('/employer" => "#[Route('/recruiter", // Catch generic starts
    "name: 'employer_'" => "name: 'recruiter_'",
    "name: 'employer_" => "name: 'recruiter_",
    "'employer/" => "'recruiter/", // Template paths
    "'employer_" => "'recruiter_", // Route names in code
    'ROLE_EMPLOYER' => 'ROLE_RECRUITER',
    '$this->render(\'employer' => '$this->render(\'recruiter',
    'redirectToRoute(\'employer' => 'redirectToRoute(\'recruiter',
];

$files = glob($dir . '/*.php');
foreach ($files as $file) {
    replaceInFile($file, $controllerReplacements);
}

// 2. Refactor Templates
// Need to iterate recursively for templates
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($templateDir));
$templateReplacements = [
    'path(\'employer_' => 'path(\'recruiter_',
    'url(\'employer_' => 'url(\'recruiter_',
    'employer/base.html.twig' => 'recruiter/base.html.twig',
    'include \'employer/' => 'include \'recruiter/',
];

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'twig') {
        replaceInFile($file->getPathname(), $templateReplacements);
    }
}

echo "Refactoring complete.\n";
