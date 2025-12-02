<?php
// Script to update form name from provider_cv_type to provider_cv
$file = 'd:\xampp\htdocs\locumlancer\templates\provider\profile\profile.html.twig';
$content = file_get_contents($file);

// 1. Update Manual Form HTML
$searchForm = <<<'SEARCH'
                {# Manual Hidden Form for CV Upload #}
                <form name="provider_cv_type" method="post" enctype="multipart/form-data" style="display:none">
                    <input type="hidden" name="provider_cv_type[_token]" value="{{ csrf_token('provider_cv_type') }}">
                    <input type="file" name="provider_cv_type[cv]" id="provider_cv_type_cv">
                </form>
SEARCH;

$replaceForm = <<<'REPLACE'
                {# Manual Hidden Form for CV Upload #}
                <form name="provider_cv" method="post" enctype="multipart/form-data" style="display:none">
                    <input type="hidden" name="provider_cv[_token]" value="{{ csrf_token('provider_cv') }}">
                    <input type="file" name="provider_cv[cv]" id="provider_cv_cv">
                </form>
REPLACE;

$content = str_replace($searchForm, $replaceForm, $content, $countForm);

// 2. Update JavaScript Selector and Keys
$searchJS = <<<'SEARCH'
      // Get the hidden form
      const cvFormElement = document.querySelector('form[name="provider_cv_type"]');
      if (!cvFormElement) {
        console.error('CV form not found');
        return;
      }

      // Get the file input from the form
      const formFileInput = cvFormElement.querySelector('input[name="provider_cv_type[cv]"]');
SEARCH;

$replaceJS = <<<'REPLACE'
      // Get the hidden form
      const cvFormElement = document.querySelector('form[name="provider_cv"]');
      if (!cvFormElement) {
        console.error('CV form not found');
        return;
      }

      // Get the file input from the form
      const formFileInput = cvFormElement.querySelector('input[name="provider_cv[cv]"]');
REPLACE;

$content = str_replace($searchJS, $replaceJS, $content, $countJS);

if ($countForm > 0 && $countJS > 0) {
    file_put_contents($file, $content);
    echo "Successfully updated form name to provider_cv ($countForm form, $countJS JS replacements)\n";
} else {
    echo "Failed to update form name. Form: $countForm, JS: $countJS\n";
}
