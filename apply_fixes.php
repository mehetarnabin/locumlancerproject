<?php
$file = 'd:\xampp\htdocs\locumlancer\templates\provider\profile\profile.html.twig';
$content = file_get_contents($file);

// 1. Insert Manual Form
$searchForm = '<div class="card-body" style="padding: 24px !important;">';
$insertForm = <<<HTML
<div class="card-body" style="padding: 24px !important;">
                {# Manual Hidden Form for CV Upload #}
                <form name="provider_cv" method="post" enctype="multipart/form-data" style="display:none">
                    <input type="hidden" name="provider_cv[_token]" value="{{ csrf_token('provider_cv') }}">
                    <input type="file" name="provider_cv[cv]" id="provider_cv_cv">
                </form>
HTML;
$content = str_replace($searchForm, $insertForm, $content, $countForm);

// 2. Replace JS Logic
$searchJSStart = "// Create FormData - use same structure as the modal form";
$searchJSEnd = "fetch('{{ path(\"app_provider_documents\") }}', {";

$startPos = strpos($content, $searchJSStart);
$endPos = strpos($content, $searchJSEnd);

if ($startPos !== false && $endPos !== false) {
    $length = $endPos - $startPos + strlen($searchJSEnd);
    $replaceJS = <<<JS
      // Get the hidden form
      const cvFormElement = document.querySelector('form[name="provider_cv"]');
      if (!cvFormElement) {
        console.error('CV form not found');
        return;
      }

      // Get the file input from the form
      const formFileInput = cvFormElement.querySelector('input[name="provider_cv[cv]"]');
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
JS;
    $content = substr_replace($content, $replaceJS, $startPos, $length);
    $countJS = 1;
} else {
    $countJS = 0;
}

// 3. Add View Modal
$searchEnd = "{% endblock %}";
$insertModal = <<<HTML
<!-- View CV Modal -->
<div class="modal fade" id="viewCvModal" tabindex="-1" aria-labelledby="viewCvModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewCvModalLabel">View CV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="cvViewerIframe" src="" style="width: 100%; height: 80vh; border: none;"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle View CV Modal
        const viewCvModal = document.getElementById('viewCvModal');
        if (viewCvModal) {
            viewCvModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const cvUrl = button.getAttribute('data-cv-url');
                const cvName = button.getAttribute('data-cv-name');
                
                const modalTitle = viewCvModal.querySelector('.modal-title');
                const iframe = viewCvModal.querySelector('#cvViewerIframe');
                
                modalTitle.textContent = cvName || 'View CV';
                iframe.src = cvUrl;
            });
            
            // Clear iframe src when modal is hidden to stop loading/playing
            viewCvModal.addEventListener('hidden.bs.modal', function() {
                const iframe = viewCvModal.querySelector('#cvViewerIframe');
                iframe.src = '';
            });
        }
    });
</script>
{% endblock %}
HTML;

// Only add modal if it's not already there
if (strpos($content, 'id="viewCvModal"') === false) {
    $content = str_replace($searchEnd, $insertModal, $content, $countModal);
} else {
    $countModal = 0;
    echo "Modal already exists.\n";
}

if ($countForm > 0 && $countJS > 0) {
    file_put_contents($file, $content);
    echo "Successfully applied fixes: Form ($countForm), JS ($countJS), Modal ($countModal)\n";
} else {
    echo "Failed to apply all fixes. Form: $countForm, JS: $countJS, Modal: $countModal\n";
}
