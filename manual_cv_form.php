<?php
// Script to replace Twig form rendering with manual HTML to bypass rendering issues
$file = 'd:\xampp\htdocs\locumlancer\templates\provider\profile\profile.html.twig';
$content = file_get_contents($file);

// Find the debug form section
$search = <<<'SEARCH'
                <div style="border: 2px solid red; padding: 10px; margin: 10px 0;">
                    <strong>DEBUG: Form Section</strong>
                    {# Hidden form for CSRF token #}
                    {{ form_start(cvForm, {'attr': {'id': 'debug_cv_form'}}) }}
                      {{ form_widget(cvForm.cv) }}
                      {{ form_rest(cvForm) }}
                    {{ form_end(cvForm) }}
                </div>
SEARCH;

// Replace with manual HTML form
$replace = <<<'REPLACE'
                {# Manual Hidden Form for CV Upload #}
                <form name="provider_cv_type" method="post" enctype="multipart/form-data" style="display:none">
                    <input type="hidden" name="provider_cv_type[_token]" value="{{ csrf_token('provider_cv_type') }}">
                    <input type="file" name="provider_cv_type[cv]" id="provider_cv_type_cv">
                </form>
REPLACE;

$content = str_replace($search, $replace, $content, $count);

if ($count > 0) {
    file_put_contents($file, $content);
    echo "Successfully replaced Twig form with manual HTML ($count replacement)\n";
} else {
    echo "Could not find debug form section to replace\n";
}
