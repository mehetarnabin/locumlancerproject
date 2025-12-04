<?php
// Script to simplify CV upload by directly submitting the form
$file = 'd:\xampp\htdocs\locumlancer\templates\provider\profile\profile.html.twig';
$content = file_get_contents($file);

// Find the current complex FormData construction
$old = <<<'OLD'
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

      // Submit via AJAX
      fetch('{{ path("app_provider_profile") }}', {
OLD;

// Replace with simpler approach - set the file on the form and submit it
$new = <<<'NEW'
      // Get the hidden form
      const cvFormElement = document.querySelector('form[name="provider_cv_type"]');
      if (!cvFormElement) {
        console.error('CV form not found');
        return;
      }

      // Get the file input from the form
      const formFileInput = cvFormElement.querySelector('input[name="provider_cv_type[cv]"]');
      if (!formFileInput) {
        console.error('CV file input not found in form');
        return;
      }

      // Create a DataTransfer to set the file on the form's input
      const dataTransfer = new DataTransfer();
      dataTransfer.items.add(file);
      formFileInput.files = dataTransfer.files;

      // Create FormData from the form (includes all fields and CSRF token)
      const formData = new FormData(cvFormElement);

      // Submit via AJAX
      fetch('{{ path("app_provider_profile") }}', {
NEW;

// Replace
$content = str_replace($old, $new, $content, $count);

if ($count > 0) {
    file_put_contents($file, $content);
    echo "Successfully simplified FormData construction ($count replacement)\n";
} else {
    echo "No replacement made - pattern not found\n";
}
