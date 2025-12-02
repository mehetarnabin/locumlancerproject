<?php
// Script to remove the duplicate CV Upload Modal
$file = 'd:\xampp\htdocs\locumlancer\templates\provider\profile\profile.html.twig';
$content = file_get_contents($file);

// The modal to remove (lines 2146-2179)
$modalToRemove = <<<'MODAL'

<!-- CV Upload Modal -->
<div class="modal fade" id="cvUploadModal" tabindex="-1" aria-labelledby="cvUploadModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cvUploadModalLabel">Upload CV</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="cvUploadForm" method="POST" enctype="multipart/form-data" action="{{ path('app_provider_profile') }}">
        <div class="modal-body">
          <div class="mb-3">
            <label for="cvFileInput" class="form-label">Choose CV File</label>
            {{ form_widget(cvForm.cv, {
              'attr': {
                'class': 'form-control',
                'id': 'cvFileInput',
                'accept': '.pdf,.doc,.docx',
                'required': true
              }
            }) }}
            <small class="text-muted">Supported formats: PDF, DOC, DOCX (Max 25MB)</small>
            {{ form_errors(cvForm.cv) }}
          </div>
          <div id="cvUploadError" class="alert alert-danger d-none" role="alert"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" id="cvUploadSubmitBtn" class="btn btn-primary">Upload</button>
        </div>
        {{ form_rest(cvForm) }}
      </form>
    </div>
  </div>
</div>
MODAL;

// Remove the modal
$content = str_replace($modalToRemove, '', $content, $count);

if ($count > 0) {
    file_put_contents($file, $content);
    echo "Successfully removed CV Upload Modal ($count replacement)\n";
} else {
    echo "No replacement made - pattern not found\n";
}
