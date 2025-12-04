<?php
// Script to fix CV upload FormData to match ProviderCvType form structure
$file = 'd:\xampp\htdocs\locumlancer\templates\provider\profile\profile.html.twig';
$content = file_get_contents($file);

// Find and replace the FormData construction
$old = <<<'OLD'
      // Create FormData - use same structure as the modal form
      const formData = new FormData();
      formData.append('fileName', file);
      formData.append('category', 'CV');
      formData.append('name', 'CV');
      formData.append('documentType', 'CV');
      
      // Try to get CSRF token from the modal form if it exists
      const modalForm = document.querySelector('#uploadModal form');
      if (modalForm) {
        const csrfInput = modalForm.querySelector('input[name*="_token"]');
        if (csrfInput) {
          formData.append(csrfInput.name, csrfInput.value);
        }
      }
OLD;

$new = <<<'NEW'
      // Create FormData with proper Symfony form structure
      const formData = new FormData();
      formData.append('provider_cv_type[cv]', file);
      
      // Get CSRF token from the cvForm
      const cvFormElement = document.querySelector('form[name="provider_cv_type"]');
      if (cvFormElement) {
        const csrfInput = cvFormElement.querySelector('input[name="provider_cv_type[_token]"]');
        if (csrfInput) {
          formData.append('provider_cv_type[_token]', csrfInput.value);
        }
      }
NEW;

// Replace
$content = str_replace($old, $new, $content, $count);

if ($count > 0) {
    file_put_contents($file, $content);
    echo "Successfully fixed FormData construction ($count replacement)\n";
} else {
    echo "No replacement made - pattern not found\n";
}
