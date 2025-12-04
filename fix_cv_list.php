<?php
// Script to replace inline CV list with include statement
$file = 'd:\xampp\htdocs\locumlancer\templates\provider\profile\profile.html.twig';
$content = file_get_contents($file);

// The old inline CV list code
$old = <<<'OLD'
                <!-- Uploaded CVs List -->
                <div id="cvDocumentsList" class="mt-4">
                  {% if cvDocuments is not empty %}
                    <div>
                      <h6 class="fw-semibold mb-3" style="font-size: 1rem;">Uploaded CVs</h6>
                      <ul class="list-group list-group-flush">
                        {% for cv in cvDocuments %}
                          <li class="list-group-item px-0 py-3 d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #e5e7eb;">
                            <div class="d-flex align-items-center gap-3">
                              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#85BB65" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                              </svg>
                              <div>
                                <div class="fw-semibold text-dark">{{ cv.name }}</div>
                                <small class="text-muted">Uploaded {{ cv.createdAt ? cv.createdAt|date('M d, Y h:i A') : '' }}</small>
                              </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                              <a href="{{ asset('uploads/' ~ app.user.id ~ '/' ~ cv.fileName) }}" target="_blank" class="btn btn-link btn-sm text-decoration-none" style="color: #85BB65;">Download</a>
                              <form action="{{ path('app_provider_profile_cv_delete', {id: cv.id}) }}" method="post" onsubmit="return confirm('Remove this CV?');" class="d-inline">
                                <input type="hidden" name="_token" value="{{ csrf_token('delete_cv_' ~ cv.id) }}">
                                <button type="submit" class="btn btn-link btn-sm text-danger p-0">Delete</button>
                              </form>
                            </div>
                          </li>
                        {% endfor %}
                      </ul>
                    </div>
                  {% endif %}
                </div>
OLD;

// The new include statement
$new = <<<'NEW'
                <!-- Uploaded CVs List -->
                <div id="cvDocumentsList" class="mt-4">
                  {% include 'provider/profile/_cv_list.html.twig' with { cvDocuments: cvDocuments } %}
                </div>
NEW;

// Replace
$content = str_replace($old, $new, $content, $count);

if ($count > 0) {
    file_put_contents($file, $content);
    echo "Successfully replaced CV list with include statement ($count replacement)\n";
} else {
    echo "No replacement made - pattern not found\n";
}
