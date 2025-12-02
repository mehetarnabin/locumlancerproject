<?php
// Script to add form name to FormData for proper Symfony form submission
$file = 'd:\xampp\htdocs\locumlancer\templates\provider\profile\profile.html.twig';
$content = file_get_contents($file);

// Find the FormData construction
$old = <<<'OLD'
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
OLD;

// Add the form name to make Symfony recognize it as a form submission
$new = <<<'NEW'
      // Create FormData with proper Symfony form structure
      const formData = new FormData();
      
      // Get the actual form element and use its FormData
      const cvFormElement = document.querySelector('form[name="provider_cv_type"]');
      if (cvFormElement) {
        // Get all form data including CSRF token
        const formFormData = new FormData(cvFormElement);
        
        // Replace the cv field with our file
        formFormData.delete('provider_cv_type[cv]');
        formFormData.append('provider_cv_type[cv]', file);
        
        // Use this FormData
        Object.assign(formData, formFormData);
        for (let pair of formFormData.entries()) {
          formData.append(pair[0], pair[1]);
        }
      } else {
        // Fallback: manually construct
        formData.append('provider_cv_type[cv]', file);
      }
NEW;

// Replace
$content = str_replace($old, $new, $content, $count);

if ($count > 0) {
    file_put_contents($file, $content);
    echo "Successfully updated FormData construction ($count replacement)\n";
} else {
    echo "No replacement made - pattern not found\n";
}
