<?php
// Script to update hidden form to include form_rest for CSRF token
$file = 'd:\xampp\htdocs\locumlancer\templates\provider\profile\profile.html.twig';
$content = file_get_contents($file);

// Find the hidden form
$old = <<<'OLD'
                {# Hidden form for CSRF token #}
                {{ form_start(cvForm, {'attr': {'style': 'display:none;'}}) }}
                  {{ form_widget(cvForm.cv) }}
                {{ form_end(cvForm) }}
OLD;

// Replace with version that includes form_rest
$new = <<<'NEW'
                {# Hidden form for CSRF token #}
                {{ form_start(cvForm, {'attr': {'style': 'display:none;'}}) }}
                  {{ form_widget(cvForm.cv, {'attr': {'style': 'display:none;'}}) }}
                  {{ form_rest(cvForm) }}
                {{ form_end(cvForm) }}
NEW;

// Replace
$content = str_replace($old, $new, $content, $count);

if ($count > 0) {
    file_put_contents($file, $content);
    echo "Successfully updated hidden form to include form_rest ($count replacement)\n";
} else {
    echo "No replacement made - pattern not found\n";
}
