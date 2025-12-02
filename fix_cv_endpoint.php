<?php
// Script to fix AJAX endpoint for CV upload
$file = 'd:\xampp\htdocs\locumlancer\templates\provider\profile\profile.html.twig';
$content = file_get_contents($file);

// Find and replace the fetch URL
$old = '      // Submit via AJAX
      fetch(\'{{ path("app_provider_documents") }}\', {';

$new = '      // Submit via AJAX
      fetch(\'{{ path("app_provider_profile") }}\', {';

// Replace
$content = str_replace($old, $new, $content, $count);

if ($count > 0) {
    file_put_contents($file, $content);
    echo "Successfully fixed AJAX endpoint ($count replacement)\n";
} else {
    echo "No replacement made - pattern not found\n";
}
