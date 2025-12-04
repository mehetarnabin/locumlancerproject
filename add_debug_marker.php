<?php
// Script to add visible debug marker and ensure form is visible for debugging
$file = 'd:\xampp\htdocs\locumlancer\templates\provider\profile\profile.html.twig';
$content = file_get_contents($file);

// Find the hidden form section
$search = <<<'SEARCH'
                {# Hidden form for CSRF token #}
                {{ form_start(cvForm, {'attr': {'style': 'display:none;'}}) }}
                  {{ form_widget(cvForm.cv, {'attr': {'style': 'display:none;'}}) }}
                  {{ form_rest(cvForm) }}
                {{ form_end(cvForm) }}
SEARCH;

// Replace with visible debug version
$replace = <<<'REPLACE'
                <div style="border: 2px solid red; padding: 10px; margin: 10px 0;">
                    <strong>DEBUG: Form Section</strong>
                    {# Hidden form for CSRF token #}
                    {{ form_start(cvForm, {'attr': {'id': 'debug_cv_form'}}) }}
                      {{ form_widget(cvForm.cv) }}
                      {{ form_rest(cvForm) }}
                    {{ form_end(cvForm) }}
                </div>
REPLACE;

$content = str_replace($search, $replace, $content, $count);

if ($count > 0) {
    file_put_contents($file, $content);
    echo "Added debug marker and removed display:none from form ($count replacement)\n";
} else {
    echo "Could not find form section to add debug marker\n";
}
