# Notify Hire Functionality

This module handles the "Notify of Hire" feature for saved jobs. It allows users to mark selected jobs as hired, which moves them to "Accepted" status and notifies the admin.

## Features

- ✅ Select multiple jobs and notify hire in batch
- ✅ Confirmation modal before processing
- ✅ Results modal showing successful, failed, and not-applied jobs
- ✅ Automatic UI updates after processing
- ✅ Apply to remaining jobs directly from results
- ✅ Toast notifications for user feedback
- ✅ Loading states during processing

## Setup

### 1. Include the Script

Add the script to your Twig template after Bootstrap:

```twig
<script src="{{ asset('assets/js/notify-hire.js') }}"></script>
```

### 2. Required HTML Elements

Ensure your template has these elements:

#### Buttons
```html
<!-- Main notify hire button -->
<button id="save-btn" class="action-btn" title="Notify of Hire">
    <!-- Button content -->
</button>

<!-- Optional: Bottom button (for sticky footer) -->
<button id="save-btn-bottom" class="action-btn" title="Notify of Hire">
    <!-- Button content -->
</button>
```

#### Job Checkboxes
```html
<!-- Each job should have a checkbox with these attributes -->
<input type="checkbox" 
       class="job-checkbox" 
       data-bookmark-id="{{ bookmark.id }}" 
       data-job-id="{{ job.id }}" />
```

#### Status Card (for counter updates)
```html
<div class="is-card is-saved">
    <div class="card-body">
        <p class="card-text">{{ count }}</p>
    </div>
</div>
```

### 3. Configure Endpoints

Update the endpoint URLs in `notify-hire.js`:

```javascript
// At the top of the file
const NOTIFY_HIRE_ENDPOINT = '{{ path("app_provider_saved_jobs_notify_hire") }}';
const APPLY_JOB_ENDPOINT = '/provider/jobs/apply';
const REDIRECT_ROUTE = 'app_provider_jobs_saved';
```

Or pass them as data attributes:

```html
<div id="notify-hire-config" 
     data-notify-hire-endpoint="{{ path('app_provider_saved_jobs_notify_hire') }}"
     data-apply-endpoint="/provider/jobs/apply"
     data-redirect-route="app_provider_jobs_saved">
</div>
```

## API Requirements

### Backend Endpoint: Notify Hire

**Route:** `POST /provider/saved-jobs/notify-hire`

**Request Body:**
```json
{
    "jobIds": [1, 2, 3]
}
```

**Response:**
```json
{
    "success": true,
    "results": {
        "successful": [
            {
                "jobId": 1,
                "jobTitle": "Job Title 1"
            }
        ],
        "notApplied": [
            {
                "jobId": 2,
                "jobTitle": "Job Title 2"
            }
        ],
        "failed": [
            {
                "jobId": 3,
                "jobTitle": "Job Title 3",
                "reason": "Error message"
            }
        ]
    }
}
```

### Backend Endpoint: Apply to Job

**Route:** `GET /provider/jobs/apply/{jobId}?redirect_route={route}`

**Response:** Redirect or JSON response indicating success/failure

## Usage

### Basic Usage

The script auto-initializes when the DOM is ready. No additional code needed if you have the required HTML elements.

### Manual Initialization

If you need to initialize manually:

```javascript
// Initialize after DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initNotifyHire();
});
```

### Programmatic Usage

You can also trigger the functionality programmatically:

```javascript
// Get selected job IDs
const selectedJobIds = Array.from(document.querySelectorAll('.job-checkbox:checked'))
    .map(cb => cb.dataset.jobId);

// Show the confirmation modal
showNotifyHireModal(selectedJobIds);

// Or process directly (bypasses confirmation)
processNotifyHire(selectedJobIds);
```

## Functions Reference

### `initNotifyHire()`
Initializes event listeners and button states.

### `handleNotifyHireFromSaved()`
Main handler for the notify hire button click. Validates selection and shows confirmation modal.

### `showNotifyHireModal(jobIds)`
Displays the confirmation modal with job details.

### `processNotifyHire(jobIds)`
Processes the notify hire request via AJAX.

### `showResultsModal(results)`
Displays the results modal with successful, failed, and not-applied jobs.

### `removeSuccessfulJobsFromUI(successfulJobs)`
Removes successfully processed jobs from the UI.

### `uncheckAllCheckboxes()`
Unchecks all job checkboxes.

### `updateJobCounters()`
Updates the job count in status cards.

### `applyToSelectedJobs(jobIds)`
Applies to multiple jobs in batch.

### `applyToJob(jobId)`
Applies to a single job.

### `showToast(message, type)`
Shows a toast notification.

### `createToastContainer()`
Creates the toast container if it doesn't exist.

## Dependencies

- **Bootstrap 5**: Required for modals and toasts
- **Modern Browser**: Requires ES6+ support (fetch API, arrow functions, etc.)

## Styling

The script uses Bootstrap classes. Ensure you have Bootstrap CSS included:

```html
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
```

Custom styles may be needed for:
- `.action-btn` - Action button styling
- `.job-checkbox` - Checkbox styling
- `.job-lists` - Job list container
- `.split-job-card` - Split view job cards

## Error Handling

The script includes error handling for:
- Network errors
- Invalid responses
- Missing elements
- Empty selections

All errors are displayed via toast notifications.

## Browser Support

- Chrome/Edge: Latest 2 versions
- Firefox: Latest 2 versions
- Safari: Latest 2 versions
- Mobile browsers: iOS Safari, Chrome Mobile

## Troubleshooting

### Buttons not working
- Ensure Bootstrap JS is loaded before this script
- Check that button IDs match (`#save-btn`, `#save-btn-bottom`)
- Verify checkboxes have class `job-checkbox`

### Modal not showing
- Check Bootstrap modal JavaScript is loaded
- Verify no JavaScript errors in console
- Ensure modal HTML is being inserted correctly

### API errors
- Check endpoint URLs are correct
- Verify CSRF token is included if required
- Check server logs for backend errors

### UI not updating
- Verify job elements have correct data attributes
- Check that selectors match your HTML structure
- Ensure `.job-lists` and `.split-job-card` classes exist

## Example Integration

```twig
{# In your Twig template #}
{% block footer_scripts %}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Configure endpoints
        window.NOTIFY_HIRE_CONFIG = {
            endpoint: '{{ path("app_provider_saved_jobs_notify_hire") }}',
            applyEndpoint: '/provider/jobs/apply',
            redirectRoute: 'app_provider_jobs_saved'
        };
    </script>
    <script src="{{ asset('assets/js/notify-hire.js') }}"></script>
{% endblock %}
```

