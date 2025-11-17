/**
 * Notify Hire Functionality
 * Handles the "Notify of Hire" feature for saved jobs
 * 
 * Dependencies:
 * - Bootstrap 5 (for modals and toasts)
 * - jQuery (optional, but some functions may use it)
 * 
 * Usage:
 * Include this script after Bootstrap and ensure the following elements exist:
 * - #save-btn (main notify hire button)
 * - #save-btn-bottom (bottom notify hire button, optional)
 * - .job-checkbox (checkboxes for job selection)
 */

// Configuration - Can be overridden via window.NOTIFY_HIRE_CONFIG or data attributes
function getConfig() {
    // Check for global config
    if (window.NOTIFY_HIRE_CONFIG) {
        return window.NOTIFY_HIRE_CONFIG;
    }
    
    // Check for data attributes on config element
    const configEl = document.getElementById('notify-hire-config');
    if (configEl) {
        return {
            endpoint: configEl.dataset.notifyHireEndpoint || '/provider/saved-jobs/notify-hire',
            applyEndpoint: configEl.dataset.applyEndpoint || '/provider/jobs/apply',
            redirectRoute: configEl.dataset.redirectRoute || 'app_provider_jobs_saved'
        };
    }
    
    // Default fallback values
    return {
        endpoint: '/provider/saved-jobs/notify-hire',
        applyEndpoint: '/provider/jobs/apply',
        redirectRoute: 'app_provider_jobs_saved'
    };
}

/**
 * Initialize the Notify Hire functionality
 * Call this after DOM is loaded
 */
function initNotifyHire() {
    const saveBtn = document.getElementById('save-btn');
    const saveBtnBottom = document.getElementById('save-btn-bottom');
    const applyBtn = document.getElementById('apply-btn');
    
    if (saveBtn) {
        saveBtn.addEventListener('click', handleNotifyHireFromSaved);
    }
    
    if (saveBtnBottom) {
        saveBtnBottom.addEventListener('click', handleNotifyHireFromSaved);
    }
    
    // Enable/disable buttons based on selection
    function updateActionButtons() {
        const selectedCheckboxes = Array.from(document.querySelectorAll('.job-checkbox:checked'));
        const hasSelection = selectedCheckboxes.length > 0;
        
        if (applyBtn) {
            applyBtn.disabled = !hasSelection;
        }
        if (saveBtn) {
            saveBtn.disabled = !hasSelection;
        }
        if (saveBtnBottom) {
            saveBtnBottom.disabled = !hasSelection;
        }
        
        // Update tooltips
        if (hasSelection) {
            if (saveBtn) saveBtn.title = "Notify Hire for Selected Jobs";
            if (saveBtnBottom) saveBtnBottom.title = "Notify Hire for Selected Jobs";
            if (applyBtn) applyBtn.title = "Apply to selected jobs";
        } else {
            if (saveBtn) saveBtn.title = "Notify of Hire";
            if (saveBtnBottom) saveBtnBottom.title = "Notify of Hire";
            if (applyBtn) applyBtn.title = "Apply";
        }
    }
    
    // Update buttons when checkboxes change
    document.querySelectorAll('.job-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateActionButtons);
    });
    
    // Initial update
    updateActionButtons();
}

/**
 * Main handler for Notify Hire button click
 */
function handleNotifyHireFromSaved() {
    const selectedCheckboxes = Array.from(document.querySelectorAll('.job-checkbox:checked'));
    
    if (selectedCheckboxes.length === 0) {
        showToast('Please select at least one job to notify hire.', 'warning');
        return;
    }
    
    const selectedJobIds = selectedCheckboxes.map(cb => cb.dataset.jobId);
    
    // Show confirmation modal
    showNotifyHireModal(selectedJobIds);
}

/**
 * Display the confirmation modal for Notify Hire
 * @param {Array} jobIds - Array of job IDs to process
 */
function showNotifyHireModal(jobIds) {
    const modalHtml = `
        <div class="modal fade" id="notifyHireModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Notify Hire for Selected Jobs</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>This action will:</p>
                        <ul>
                            <li>Mark providers as hired for jobs you've applied to</li>
                            <li>Move successfully processed jobs to "Accepted" status</li>
                            <li>Remove them from your saved jobs</li>
                            <li>Notify the admin about the hire</li>
                        </ul>
                        <div class="alert alert-warning">
                            <strong>Note:</strong> Only jobs you have already applied to can be processed. 
                            Jobs without applications will be skipped.
                        </div>
                        <p>Are you sure you want to proceed?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" id="confirm-notify-hire-btn">
                            Yes, Notify Hire
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('notifyHireModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to DOM
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    const modal = new bootstrap.Modal(document.getElementById('notifyHireModal'));
    modal.show();
    
    // Handle confirm button
    document.getElementById('confirm-notify-hire-btn').addEventListener('click', function() {
        modal.hide();
        processNotifyHire(jobIds);
    });
}

/**
 * Process the Notify Hire request
 * @param {Array} jobIds - Array of job IDs to process
 */
function processNotifyHire(jobIds) {
    // Show loading state
    const saveBtn = document.getElementById('save-btn');
    const saveBtnBottom = document.getElementById('save-btn-bottom');
    const originalHtml = saveBtn ? saveBtn.innerHTML : '';
    
    if (saveBtn) {
        saveBtn.innerHTML = '<div class="spinner-border spinner-border-sm"></div> Processing...';
        saveBtn.disabled = true;
    }
    if (saveBtnBottom) {
        saveBtnBottom.innerHTML = '<div class="spinner-border spinner-border-sm"></div>';
        saveBtnBottom.disabled = true;
    }
    
    // Get configuration
    const config = getConfig();
    
    fetch(config.endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ jobIds: jobIds })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showResultsModal(data.results);
            // Remove successful jobs from the UI
            removeSuccessfulJobsFromUI(data.results.successful);
            // Uncheck all checkboxes
            uncheckAllCheckboxes();
        } else {
            showToast('Error: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error. Please try again.', 'error');
    })
    .finally(() => {
        // Reset button
        if (saveBtn) {
            saveBtn.innerHTML = originalHtml;
            saveBtn.disabled = false;
        }
        if (saveBtnBottom) {
            saveBtnBottom.innerHTML = '<svg class="icon-image" width="25" height="25" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M22 10.5V12C22 16.714 22 19.0711 20.5355 20.5355C19.0711 22 16.714 22 12 22C7.28595 22 4.92893 22 3.46447 20.5355C2 19.0711 2 16.714 2 12C2 7.28595 2 4.92893 3.46447 3.46447C4.92893 2 7.28595 2 12 2H13.5" stroke="currentcolor" stroke-width="1.5" stroke-linecap="round"/><circle cx="19" cy="5" r="3" stroke="#1C274C" stroke-width="1.5"/><path d="M7 14H16" stroke="currentcolor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 17.5H13" stroke="currentcolor" stroke-width="1.5" stroke-linecap="round"/></svg>';
            saveBtnBottom.disabled = false;
        }
        // Re-initialize to update button states
        if (typeof updateActionButtons === 'function') {
            updateActionButtons();
        }
    });
}

/**
 * Display the results modal after processing
 * @param {Object} results - Results object with successful, notApplied, and failed arrays
 */
function showResultsModal(results) {
    const successfulCount = results.successful ? results.successful.length : 0;
    const notAppliedCount = results.notApplied ? results.notApplied.length : 0;
    const failedCount = results.failed ? results.failed.length : 0;
    
    let resultsHtml = `
        <div class="modal fade" id="resultsModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Hire Notification Results</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
    `;
    
    // Successful results
    if (successfulCount > 0) {
        resultsHtml += `
            <div class="alert alert-success">
                <h6>✅ Successfully Hired (${successfulCount})</h6>
                <small>The following jobs have been moved to "Accepted" and removed from saved:</small>
                <ul class="mb-0 mt-2">
        `;
        results.successful.forEach(job => {
            resultsHtml += `<li>${job.jobTitle || job.title || 'Job #' + job.jobId}</li>`;
        });
        resultsHtml += `</ul></div>`;
    }
    
    // Not applied jobs
    if (notAppliedCount > 0) {
        resultsHtml += `
            <div class="alert alert-warning">
                <h6>⚠️ Not Applied (${notAppliedCount})</h6>
                <small>These jobs need to be applied to first:</small>
                <ul class="mb-0 mt-2">
        `;
        results.notApplied.forEach(job => {
            resultsHtml += `<li>${job.jobTitle || job.title || 'Job #' + job.jobId}</li>`;
        });
        resultsHtml += `</ul></div>`;
    }
    
    // Failed jobs
    if (failedCount > 0) {
        resultsHtml += `
            <div class="alert alert-danger">
                <h6>❌ Failed (${failedCount})</h6>
                <small>These jobs could not be processed:</small>
                <ul class="mb-0 mt-2">
        `;
        results.failed.forEach(job => {
            resultsHtml += `<li>${job.jobTitle || job.title || 'Job #' + job.jobId} - ${job.reason || 'Unknown error'}</li>`;
        });
        resultsHtml += `</ul></div>`;
    }
    
    resultsHtml += `
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Continue</button>
                        ${notAppliedCount > 0 ? 
                            '<button type="button" class="btn btn-success" id="apply-remaining-btn">Apply to Remaining Jobs</button>' : 
                            ''
                        }
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('resultsModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to DOM
    document.body.insertAdjacentHTML('beforeend', resultsHtml);
    
    const modal = new bootstrap.Modal(document.getElementById('resultsModal'));
    modal.show();
    
    // Handle apply remaining button
    if (notAppliedCount > 0) {
        document.getElementById('apply-remaining-btn').addEventListener('click', function() {
            modal.hide();
            // Apply to the not applied jobs
            const notAppliedJobIds = results.notApplied.map(job => job.jobId);
            applyToSelectedJobs(notAppliedJobIds);
        });
    }
}

/**
 * Remove successfully processed jobs from the UI
 * @param {Array} successfulJobs - Array of successfully processed job objects
 */
function removeSuccessfulJobsFromUI(successfulJobs) {
    successfulJobs.forEach(job => {
        // Remove from list view
        const jobRow = document.querySelector(`.job-checkbox[data-job-id="${job.jobId}"]`)?.closest('.job-lists');
        if (jobRow) {
            jobRow.remove();
        }
        
        // Remove from split view
        const splitJobCard = document.querySelector(`.split-job-card[data-job-id="${job.jobId}"]`);
        if (splitJobCard) {
            splitJobCard.remove();
        }
    });
    
    // Update counters
    updateJobCounters();
}

/**
 * Uncheck all job checkboxes
 */
function uncheckAllCheckboxes() {
    document.querySelectorAll('.job-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
}

/**
 * Update job counters in the UI
 */
function updateJobCounters() {
    // Update the saved jobs count in the status cards
    const remainingBookmarks = document.querySelectorAll('.job-checkbox').length;
    const savedCountElement = document.querySelector('.is-card.is-saved .card-text');
    if (savedCountElement) {
        savedCountElement.textContent = remainingBookmarks;
    }
}

/**
 * Apply to multiple selected jobs
 * @param {Array} jobIds - Array of job IDs to apply to
 */
function applyToSelectedJobs(jobIds) {
    if (!jobIds || jobIds.length === 0) {
        showToast('No jobs to apply to.', 'warning');
        return;
    }
    
    // Show loading state
    const applyBtn = document.getElementById('apply-btn');
    const applyBtnBottom = document.getElementById('apply-btn-bottom');
    const originalHtml = applyBtn ? applyBtn.innerHTML : '';
    
    if (applyBtn) {
        applyBtn.innerHTML = '<div class="spinner-border spinner-border-sm"></div> Applying...';
        applyBtn.disabled = true;
    }
    if (applyBtnBottom) {
        applyBtnBottom.innerHTML = '<div class="spinner-border spinner-border-sm"></div>';
        applyBtnBottom.disabled = true;
    }
    
    // Apply to each job
    const applyPromises = jobIds.map(jobId => {
        return applyToJob(jobId);
    });
    
    // Execute all apply operations
    Promise.allSettled(applyPromises).then(results => {
        const successful = results.filter(r => r.status === 'fulfilled').length;
        const failed = results.filter(r => r.status === 'rejected').length;
        
        // Reset button
        if (applyBtn) {
            applyBtn.innerHTML = originalHtml;
            applyBtn.disabled = false;
        }
        if (applyBtnBottom) {
            applyBtnBottom.innerHTML = '<svg class="icon-image" fill="currentcolor" width="25" height="25" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><g data-name="Layer 2"><g data-name="checkmark-circle"><rect width="24" height="24" opacity="0"/><path d="M9.71 11.29a1 1 0 0 0-1.42 1.42l3 3A1 1 0 0 0 12 16a1 1 0 0 0 .72-.34l7-8a1 1 0 0 0-1.5-1.32L12 13.54z"/><path d="M21 11a1 1 0 0 0-1 1 8 8 0 0 1-8 8A8 8 0 0 1 6.33 6.36 7.93 7.93 0 0 1 12 4a8.79 8.79 0 0 1 1.9.22 a1 1 0 1 0 .47-1.94A10.54 10.54 0 0 0 12 2a10 10 0 0 0-7 17.09A9.93 9.93 0 0 0 12 22a10 10 0 0 0 10-10 1 1 0 0 0-1-1z"/></g></g></svg>';
            applyBtnBottom.disabled = false;
        }
        
        // Show results
        if (failed === 0) {
            showToast(`Successfully applied to ${successful} job(s)!`, 'success');
        } else {
            showToast(`Applied to ${successful} job(s), ${failed} failed.`, 'warning');
        }
        
        // Refresh the page to update the UI
        setTimeout(() => {
            window.location.reload();
        }, 2000);
    });
}

/**
 * Apply to a single job
 * @param {string|number} jobId - The job ID to apply to
 * @returns {Promise} Promise that resolves when the application is complete
 */
function applyToJob(jobId) {
    return new Promise((resolve, reject) => {
        const config = getConfig();
        const url = `${config.applyEndpoint}/${jobId}?redirect_route=${config.redirectRoute}`;
        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (response.ok) {
                resolve();
            } else {
                reject(new Error('Apply failed'));
            }
        })
        .catch(error => reject(error));
    });
}

/**
 * Show a toast notification
 * @param {string} message - The message to display
 * @param {string} type - The type of toast (success, error, warning, info)
 */
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toast-container') || createToastContainer();
    
    const toastId = 'toast-' + Date.now();
    const bgClass = type === 'success' ? 'text-bg-success' : 
                   type === 'error' ? 'text-bg-danger' : 
                   type === 'warning' ? 'text-bg-warning' : 'text-bg-info';
    
    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center ${bgClass} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
    
    // Remove toast from DOM after it hides
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

/**
 * Create a toast container if it doesn't exist
 * @returns {HTMLElement} The toast container element
 */
function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNotifyHire);
} else {
    // DOM is already ready
    initNotifyHire();
}

// Export functions for use in other scripts (if using modules)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        initNotifyHire,
        handleNotifyHireFromSaved,
        showNotifyHireModal,
        processNotifyHire,
        showResultsModal,
        removeSuccessfulJobsFromUI,
        uncheckAllCheckboxes,
        updateJobCounters,
        applyToSelectedJobs,
        applyToJob,
        showToast,
        createToastContainer
    };
}

