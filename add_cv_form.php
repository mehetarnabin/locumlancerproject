<?php
// Script to add hidden CV form with CSRF token
$file = 'd:\xampp\htdocs\locumlancer\templates\provider\profile\profile.html.twig';
$content = file_get_contents($file);

// Find the location right after the CV Upload card-body starts
$searchFor = '              <div class="card-body" style="padding: 24px !important;">
                <!-- Drag and Drop Area -->';

$replacement = '              <div class="card-body" style="padding: 24px !important;">
                {# Hidden form for CSRF token #}
                {{ form_start(cvForm, {\'attr\': {\'style\': \'display:none;\'}}) }}
                  {{ form_widget(cvForm.cv) }}
                {{ form_end(cvForm) }}
                
                <!-- Drag and Drop Area -->';

// Replace
$content = str_replace($searchFor, $replacement, $content, $count);

if ($count > 0) {
    file_put_contents($file, $content);
    echo "Successfully added hidden CV form with CSRF token ($count replacement)\n";
} else {
    echo "No replacement made - pattern not found\n";
}
