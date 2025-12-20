// Capture To-Do icon clicks via external script (CSP-safe)
// Attaches capture-phase listeners so row/overlay handlers cannot swallow events
(function(){
  function forceShow(modalEl){
    if (!modalEl) return;
    try {
      modalEl.classList.add('show');
      modalEl.style.setProperty('display','flex','important');
      modalEl.style.position = 'fixed';
      modalEl.style.top = '0';
      modalEl.style.left = '0';
      modalEl.style.width = '100vw';
      modalEl.style.height = '100vh';
      modalEl.style.alignItems = 'center';
      modalEl.style.justifyContent = 'center';
      modalEl.style.background = 'rgba(0,0,0,0.5)';
      modalEl.style.zIndex = '2147483647';
      modalEl.style.pointerEvents = 'auto';
      modalEl.setAttribute('aria-hidden', 'false');
      var existingBackdrop = document.querySelector('.modal-backdrop.fade.show');
      if (existingBackdrop && existingBackdrop.parentNode) existingBackdrop.parentNode.removeChild(existingBackdrop);
    } catch(_) {}
  }
  window.handleTodoIconClick = function(e, el) {
    try {
      // allow other handlers to run; only prevent default when we actually handle showing a modal
      var targetEl = (e && e.target) ? e.target : el;
      if (el && el.classList && el.classList.contains('todo-icon')) { el = el.parentElement || el; }
      var wrapperEl = targetEl && targetEl.closest ? (targetEl.closest('.todo-icon-wrapper') || el) : el;
      var iconEl = null;
      try { iconEl = (wrapperEl && wrapperEl.querySelector) ? wrapperEl.querySelector('.todo-icon') : null; } catch(_) { iconEl = null; }
      var statusText = (wrapperEl && wrapperEl.getAttribute) ? (wrapperEl.getAttribute('data-doc-status') || '') : '';
      var pendingText = (wrapperEl && wrapperEl.getAttribute) ? (wrapperEl.getAttribute('data-doc-pending') || '') : '';
      var tooltipTitle = (wrapperEl && wrapperEl.getAttribute) ? (wrapperEl.getAttribute('title') || '') : '';
      function readId(node, attr) {
        if (!node) return '';
        try {
          if (node.getAttribute && node.getAttribute(attr)) return node.getAttribute(attr);
          if (node.dataset) {
            if (attr === 'data-application-id' && (node.dataset.applicationId || node.dataset.applicationID)) return node.dataset.applicationId || node.dataset.applicationID;
            if (attr === 'data-job-id' && (node.dataset.jobId || node.dataset.jobID)) return node.dataset.jobId || node.dataset.jobID;
          }
        } catch(_) {}
        return '';
      }
      var appId = readId(wrapperEl, 'data-application-id') || readId(iconEl, 'data-application-id');
      var jobId = readId(wrapperEl, 'data-job-id') || readId(iconEl, 'data-job-id');
      if ((!appId || !jobId) && targetEl && targetEl.closest) {
        var nearestWithIds = targetEl.closest('[data-application-id],[data-job-id]');
        appId = appId || readId(nearestWithIds, 'data-application-id');
        jobId = jobId || readId(nearestWithIds, 'data-job-id');
      }
      if (!appId || !jobId) {
        var probe = wrapperEl;
        var hops = 0;
        while (probe && hops < 5 && (!appId || !jobId)) {
          appId = appId || readId(probe, 'data-application-id');
          jobId = jobId || readId(probe, 'data-job-id');
          probe = probe.parentElement;
          hops++;
        }
      }
      if (!appId || !jobId) {
        var scope = (wrapperEl && wrapperEl.closest) ? (wrapperEl.closest('.application') || wrapperEl) : wrapperEl;
        try {
          var anyAppEl = scope && scope.querySelector ? scope.querySelector('[data-application-id]') : null;
          var anyJobEl = scope && scope.querySelector ? scope.querySelector('[data-job-id]') : null;
          appId = appId || readId(anyAppEl, 'data-application-id');
          jobId = jobId || readId(anyJobEl, 'data-job-id');
        } catch(_) {}
      }
      var namesFromPending = (pendingText || '').split(',').map(function(s){ return s.trim(); }).filter(function(s){ return !!s; });
      var desiredNames = namesFromPending;
      if (!desiredNames.length) {
        var statusTokens = (statusText || '').split(',').map(function(s){ return s.trim(); }).filter(function(s){ return !!s; });
        var pendingTokens = statusTokens.filter(function(tok){ return /(\(Pending\))/i.test(tok); });
        desiredNames = pendingTokens.map(function(tok){ return tok.replace(/\s*\(Pending\)\s*/i, '').trim(); }).filter(function(s){ return !!s; });
      }
      if (!desiredNames.length && tooltipTitle) {
        var t = tooltipTitle.trim();
        if (/^Docs:\s*/i.test(t)) {
          var without = t.replace(/^Docs:\s*/i, '');
          desiredNames = without.split(',').map(function(s){ return s.trim(); }).filter(function(s){ return !!s; });
        } else if (/^Provide:\s*/i.test(t)) {
          var withoutProvide = t.replace(/^Provide:\s*/i, '');
          desiredNames = withoutProvide.split(',').map(function(s){ return s.trim(); }).filter(function(s){ return !!s; });
        }
      }

      var type = (wrapperEl && wrapperEl.getAttribute) ? (wrapperEl.getAttribute('data-todo-type') || '') : '';
      if (type === 'interview') {
          var interviewsJson = (wrapperEl && wrapperEl.getAttribute) ? wrapperEl.getAttribute('data-interviews') : '[]';
          var interviews = [];
          try { interviews = JSON.parse(interviewsJson); } catch(e){}
          
          if (!interviews || !interviews.length) {
              var d = (wrapperEl && wrapperEl.getAttribute) ? wrapperEl.getAttribute('data-interview-date') : '';
              if (d) {
                  interviews.push({
                      date: d,
                      url: (wrapperEl && wrapperEl.getAttribute) ? wrapperEl.getAttribute('data-interview-url') : '',
                      platform: (wrapperEl && wrapperEl.getAttribute) ? wrapperEl.getAttribute('data-interview-platform') : ''
                  });
              }
          }
          
          var modalId = 'interviewDetailsModal';
          var m = document.getElementById(modalId);
          if (!m) {
              m = document.createElement('div');
              m.id = modalId;
              m.className = 'modal fade';
              m.setAttribute('tabindex', '-1');
              m.innerHTML = '<div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Interview Details</h5><button type="button" class="btn-close close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Close</button></div></div></div>';
              document.body.appendChild(m);
          }
          
          var body = m.querySelector('.modal-body');
          if (body) {
              if (interviews.length > 0) {
                  var html = '<ul class="list-group">';
                  interviews.forEach(function(iv){
                      var when = iv.date ? new Date(iv.date) : null;
                      var endWhen = iv.end_date ? new Date(iv.end_date) : null;
                      var dateStr = when ? when.toLocaleDateString([], { year: 'numeric', month: 'long', day: 'numeric' }) : 'TBD';
                      var timeStr = when ? when.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
                      var endTimeStr = endWhen ? endWhen.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
                      html += '<li class="list-group-item">';
                      html += '<strong>' + dateStr + '</strong>';
                      if (timeStr || endTimeStr) {
                          html += '<br><span class="badge bg-info" style="font-size:13px;">' + (iv.platform || 'Interview') + ' • ' + (timeStr ? ('From ' + timeStr) : 'From Not Set') + (endTimeStr ? (' — To ' + endTimeStr) : ' — To Not Set') + '</span>';
                      } else if (iv.platform) {
                          html += '<br><small class="text-muted">' + iv.platform + '</small>';
                      }
                      if (iv.url) html += '<br><a href="' + iv.url + '" target="_blank" class="btn btn-sm btn-primary mt-2">Join Meeting</a>';
                      html += '</li>';
                  });
                  html += '</ul>';
                  body.innerHTML = html;
              } else {
                  body.innerHTML = '<p>No interview details available.</p>';
              }
          }
          
          forceShow(m);
          return;
      }

      console.log('Open To-Do modal', { appId: appId, jobId: jobId, desiredNames: desiredNames });
      var assignedModalEl = document.getElementById('assignedDocsModal');
      if (!assignedModalEl) {
        assignedModalEl = document.createElement('div');
        assignedModalEl.className = 'modal';
        assignedModalEl.id = 'assignedDocsModal';
        assignedModalEl.tabIndex = -1;
        assignedModalEl.setAttribute('role', 'dialog');
        assignedModalEl.setAttribute('aria-labelledby', 'assignedDocsModalLabel');
        assignedModalEl.setAttribute('aria-hidden', 'true');
        assignedModalEl.innerHTML = '<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document" style="max-width:80vw;width:80vw;">\n          <div class="modal-content" style="width:100%;max-height:80vh;">\n            <div class="modal-header">\n              <h5 class="modal-title" id="assignedDocsModalLabel">Submit Pending Documents</h5>\n              <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>\n            </div>\n            <div class="modal-body" style="max-height:65vh;overflow-y:auto;">\n              <p id="assignedDocsContent" class="mb-2"></p>\n              <div id="assignedDocsAlert" class="alert alert-success" style="display:none;font-size:12px;padding:6px 10px;margin-bottom:8px;"></div>\n              <div id="assignedDocsList"></div>\n            </div>\n            <div class="modal-footer">\n              <button type="button" class="btn btn-primary" id="submitAllDocsBtn">Submit All</button>\n              <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Close</button>\n            </div>\n          </div>\n        </div>';
        document.body.appendChild(assignedModalEl);
        var dlgNew = assignedModalEl.querySelector('.modal-dialog');
        if (dlgNew) { dlgNew.classList.add('modal-lg','modal-dialog-centered','modal-dialog-scrollable'); dlgNew.style.maxWidth='80vw'; dlgNew.style.width='80vw'; }
        var contNew = assignedModalEl.querySelector('.modal-content');
        if (contNew) { contNew.style.maxHeight='80vh'; contNew.style.width='100%'; }
        var bodyNew = assignedModalEl.querySelector('.modal-body');
        if (bodyNew) { bodyNew.style.maxHeight='65vh'; bodyNew.style.overflowY='auto'; }
        var submitAllInit = assignedModalEl.querySelector('#submitAllDocsBtn');
        if (submitAllInit) {
          submitAllInit.disabled = true;
          submitAllInit.textContent = 'Submit All';
          submitAllInit.onclick = function(){
            var rows = assignedModalEl.querySelectorAll('#assignedDocsList .list-group-item');
            if (!rows.length) { alert('No pending documents'); return; }
          };
        }
      }
      var assignedContentEl = assignedModalEl.querySelector('#assignedDocsContent');
      var assignedListEl = assignedModalEl.querySelector('#assignedDocsList');
      var assignedAlertEl = assignedModalEl.querySelector('#assignedDocsAlert');
      var dlgEl = assignedModalEl.querySelector('.modal-dialog');
      if (dlgEl) { dlgEl.classList.add('modal-lg','modal-dialog-centered','modal-dialog-scrollable'); dlgEl.style.maxWidth='80vw'; dlgEl.style.width='80vw'; }
      var contEl = assignedModalEl.querySelector('.modal-content');
      if (contEl) { contEl.style.maxHeight='80vh'; contEl.style.width='100%'; }
      var bodyElSizer = assignedModalEl.querySelector('.modal-body');
      if (bodyElSizer) { bodyElSizer.style.maxHeight='65vh'; bodyElSizer.style.overflowY='auto'; }
      function showSuccess(message) {
        var el = assignedAlertEl || assignedModalEl.querySelector('#assignedDocsAlert');
        if (!el) return;
        el.textContent = message || 'Submitted successfully';
        el.style.display = 'block';
        setTimeout(function(){ try { el.style.display = 'none'; } catch(_) {} }, 2000);
      }
      if (assignedListEl) assignedListEl.className = 'list-group';
      var assignedTitleEl = assignedModalEl.querySelector('#assignedDocsModalLabel');
      if (assignedTitleEl) assignedTitleEl.textContent = 'Submit Pending Documents';
      if (assignedContentEl) assignedContentEl.textContent = desiredNames.length ? ('Please submit: ' + desiredNames.join(', ')) : 'Loading requested documents…';
      if (assignedListEl) {
        assignedListEl.innerHTML = '';
        var fragmentInit = document.createDocumentFragment();
        if (desiredNames.length) {
          desiredNames.forEach(function(nameTxt){
            var row = document.createElement('div');
            row.className = 'list-group-item d-flex align-items-center';
            var nameEl = document.createElement('span');
            nameEl.textContent = nameTxt;
            nameEl.style.flex = '1';
            var statusEl = document.createElement('span');
            statusEl.className = 'badge badge-warning bg-warning text-dark';
            statusEl.textContent = 'Pending';
            row.appendChild(nameEl);
            row.appendChild(statusEl);
            fragmentInit.appendChild(row);
          });
        } else {
          var msg = document.createElement('div');
          msg.className = 'list-group-item text-muted';
          msg.textContent = 'Loading requested documents…';
          fragmentInit.appendChild(msg);
        }
        assignedListEl.appendChild(fragmentInit);
        var submitAllBtnInit = document.getElementById('submitAllDocsBtn');
        function submitAllNow(){
          var btn = document.getElementById('submitAllDocsBtn');
          if (btn) { btn.disabled = true; btn.textContent = 'Submitting...'; }
          var rows = assignedListEl.querySelectorAll('.list-group-item');
          var ops = [];
          rows.forEach(function(r){
            var reqId = r.getAttribute('data-request-id');
            var selectEl = r.querySelector('select');
            var fileEl = r.querySelector('input[type="file"]');
            var badgeEl = r.querySelector('.badge');
            if (reqId && selectEl && !selectEl.disabled && selectEl.value) {
              ops.push(fetch(window.location.origin + '/provider/document-request/' + reqId + '/assign-document', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: JSON.stringify({ documentId: selectEl.value })
              }).then(function(res){ if (!res.ok) { throw new Error('HTTP ' + res.status); } var ct = res.headers.get('content-type') || ''; if (ct.indexOf('application/json') === -1) { throw new Error('Invalid content type'); } return res.json(); }).then(function(json){
                if (json && json.success) {
                  if (badgeEl) { badgeEl.textContent = 'Provided'; badgeEl.style.color = '#0aad4f'; }
                  selectEl.disabled = true;
                  if (fileEl) fileEl.disabled = true;
                }
              }));
            } else if (reqId && fileEl && !fileEl.disabled && fileEl.files && fileEl.files[0]) {
              var fd = new FormData();
              fd.append('file', fileEl.files[0]);
              var nameTxt = (r.querySelector('span') && r.querySelector('span').textContent) || '';
              fd.append('category', nameTxt);
              ops.push(fetch(window.location.origin + '/provider/documents/upload-ajax', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: fd
              }).then(function(up){ if (!up.ok) { throw new Error('HTTP ' + up.status); } var ct = up.headers.get('content-type') || ''; if (ct.indexOf('application/json') === -1) { throw new Error('Invalid content type'); } return up.json(); }).then(function(json){
                if (!json || !json.success || !json.document || !json.document.id) return;
                return fetch(window.location.origin + '/provider/document-request/' + reqId + '/assign-document', {
                  method: 'POST',
                  headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                  credentials: 'same-origin',
                  body: JSON.stringify({ documentId: json.document.id })
                }).then(function(r2){ if (!r2.ok) { throw new Error('HTTP ' + r2.status); } var ct2 = r2.headers.get('content-type') || ''; if (ct2.indexOf('application/json') === -1) { throw new Error('Invalid content type'); } return r2.json(); }).then(function(j2){
                  if (j2 && j2.success) {
                    if (badgeEl) { badgeEl.textContent = 'Provided'; badgeEl.style.color = '#0aad4f'; }
                    fileEl.disabled = true;
                  }
                });
              }));
            }
          });
          if (!ops.length) {
            if (btn) { btn.disabled = false; btn.textContent = 'Submit All'; }
            alert('Select a document or upload files for pending requests');
            return;
          }
          Promise.all(ops).then(function(){
            if (btn) {
              btn.textContent = 'Done';
              setTimeout(function(){ btn.disabled = false; btn.textContent = 'Submit All'; }, 1500);
            }
            showSuccess('All submissions completed');
          });
        }
        if (submitAllBtnInit) {
          submitAllBtnInit.disabled = false;
          submitAllBtnInit.textContent = 'Submit All';
          submitAllBtnInit.onclick = function(e){ if (e && e.preventDefault) e.preventDefault(); if (e && e.stopPropagation) e.stopPropagation(); submitAllNow(); };
        }
        var modalShowEl = assignedModalEl;
        if (modalShowEl) {
          if (window.bootstrap && window.bootstrap.Modal) {
            try { window.bootstrap.Modal.getOrCreateInstance(modalShowEl).show(); setTimeout(function(){ try { window.bootstrap.Modal.getOrCreateInstance(modalShowEl).show(); } catch(_){ } }, 10); } catch(_){}
          } else if ((window.$ || window.jQuery) && ((window.$ && window.$.fn && window.$.fn.modal) || (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal))) {
            try { (window.$ || window.jQuery)(modalShowEl).modal('show'); setTimeout(function(){ try { (window.$ || window.jQuery)(modalShowEl).modal('show'); } catch(_){ } }, 10); } catch(_){}
          } else {
            modalShowEl.classList.add('show');
            modalShowEl.style.display = 'flex';
            modalShowEl.style.position = 'fixed';
            modalShowEl.style.top = '0';
            modalShowEl.style.left = '0';
            modalShowEl.style.width = '100vw';
            modalShowEl.style.height = '100vh';
            modalShowEl.style.alignItems = 'center';
            modalShowEl.style.justifyContent = 'center';
            modalShowEl.style.background = 'rgba(0,0,0,0.5)';
            modalShowEl.style.zIndex = '100005';
            modalShowEl.style.pointerEvents = 'auto';
            modalShowEl.setAttribute('aria-hidden', 'false');
          }
        }
      }

      if (appId && assignedListEl) {
        var updated = false;
        Promise.all([
          fetch(window.location.origin + '/provider/applications/' + appId + '/document-requests', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
          }).then(function(r){ if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); }),
          fetch(window.location.origin + '/provider/documents/list', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
          }).then(function(r){ if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); })
        ]).then(function(arr){
          var reqData = arr[0], docData = arr[1];
          var requests = (reqData && (reqData.documentRequests || reqData.requests || reqData.data || [])) || [];
          var documents = (docData && docData.documents) || [];
          console.log('Requests/documents loaded', { requestsCount: requests.length, documentsCount: documents.length });
          var pendingRequestsAll = requests.filter(function(r){ return !r.providedAt; });
          var pendingRequests = pendingRequestsAll;
          if (desiredNames && desiredNames.length) {
            var desiredSet = desiredNames.map(function(n){ return n.toLowerCase(); });
            pendingRequests = pendingRequestsAll.filter(function(r){ return desiredSet.indexOf((r.name || '').toLowerCase()) !== -1; });
            if (!pendingRequests.length) pendingRequests = pendingRequestsAll;
          }
          if (jobId) {
            pendingRequests = pendingRequests.filter(function(r){ return (r.jobId ? String(r.jobId).toLowerCase() === String(jobId).toLowerCase() : true); });
          }
          console.log('Pending after filter', { names: pendingRequests.map(function(r){ return r.name; }) });
          if (assignedTitleEl) assignedTitleEl.textContent = 'Pending Document Requests';
          if (assignedContentEl) assignedContentEl.textContent = pendingRequests.length ? ('Please submit: ' + pendingRequests.map(function(r){ return r.name; }).join(', ')) : ((desiredNames && desiredNames.length) ? ('Please submit: ' + desiredNames.join(', ')) : 'No pending documents.');
          assignedListEl.innerHTML = '';
          var fragment = document.createDocumentFragment();
          pendingRequests.forEach(function(req){
            var row = document.createElement('div');
            row.className = 'list-group-item d-flex align-items-center';
            var name = document.createElement('span');
            name.textContent = req.name;
            name.style.flex = '1';
            var status = document.createElement('span');
            status.className = 'badge badge-warning bg-warning text-dark';
            status.textContent = 'Pending';
            var select = null;
            var assignBtn = null;
            var fileInput = null;
            var uploadAssignBtn = null;
            if (documents.length > 0) {
              select = document.createElement('select');
              select.style.flex = '1';
              select.className = 'form-select form-select-sm';
              documents.forEach(function(d){
                var opt = document.createElement('option');
                opt.value = d.id;
                opt.textContent = d.name || d.fileName;
                select.appendChild(opt);
              });
              assignBtn = document.createElement('button');
              assignBtn.type = 'button';
              assignBtn.className = 'btn btn-sm btn-primary';
              assignBtn.textContent = 'Submit';
              assignBtn.addEventListener('click', function(e){
                if (e && typeof e.preventDefault === 'function') e.preventDefault();
                if (e && typeof e.stopPropagation === 'function') e.stopPropagation();
                var documentId = select && select.value;
                if (!documentId) { alert('Please select a document to submit'); return; }
                var prevText = assignBtn.textContent;
                assignBtn.disabled = true; assignBtn.textContent = 'Submitting...';
                if (status) { status.textContent = 'Submitting...'; status.style.color = '#0a58ca'; }
                fetch(window.location.origin + '/provider/document-request/' + req.id + '/assign-document', {
                  method: 'POST',
                  headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                  credentials: 'same-origin',
                  body: JSON.stringify({ documentId: documentId })
                }).then(function(r){ if (!r.ok) { throw new Error('HTTP ' + r.status); } var ct = r.headers.get('content-type') || ''; if (ct.indexOf('application/json') === -1) { throw new Error('Invalid content type'); } return r.json(); }).then(function(res){
                  if (res && res.success) {
                    status.textContent = 'Provided';
                    status.style.color = '#0aad4f';
                    if (select) select.disabled = true;
                    if (assignBtn) assignBtn.disabled = true;
                    if (fileInput) fileInput.disabled = true;
                    if (uploadAssignBtn) uploadAssignBtn.disabled = true;
                    showSuccess('Document submitted successfully');
                  } else {
                    alert((res && res.message) || 'Failed to submit document');
                    assignBtn.disabled = false; assignBtn.textContent = prevText;
                    if (status) { status.textContent = 'Failed'; status.style.color = '#dc3545'; }
                  }
                }).catch(function(){
                  alert('Network error while submitting document');
                  assignBtn.disabled = false; assignBtn.textContent = prevText;
                  if (status) { status.textContent = 'Failed'; status.style.color = '#dc3545'; }
                });
              });
            }
            fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.accept = '.pdf,.jpg,.jpeg,.png,.doc,.docx';
            fileInput.style.flex = '1';
            fileInput.className = 'form-control form-control-sm';
            uploadAssignBtn = document.createElement('button');
            uploadAssignBtn.type = 'button';
            uploadAssignBtn.className = 'btn btn-sm btn-secondary';
            uploadAssignBtn.textContent = 'Upload & Submit';
              uploadAssignBtn.addEventListener('click', function(e){
                if (e && typeof e.preventDefault === 'function') e.preventDefault();
                if (e && typeof e.stopPropagation === 'function') e.stopPropagation();
                var file = fileInput.files && fileInput.files[0];
                if (!file) { alert('Select a file to upload'); return; }
                var prevUp = uploadAssignBtn.textContent; uploadAssignBtn.disabled = true; uploadAssignBtn.textContent = 'Uploading...';
                var fd = new FormData();
                fd.append('file', file);
                fd.append('category', req.name);
                fetch(window.location.origin + '/provider/documents/upload-ajax', {
                  method: 'POST',
                  headers: { 'Accept': 'application/json' },
                  credentials: 'same-origin',
                  body: fd
                }).then(function(r){ if (!r.ok) { throw new Error('HTTP ' + r.status); } var ct = r.headers.get('content-type') || ''; if (ct.indexOf('application/json') === -1) { throw new Error('Invalid content type'); } return r.json(); }).then(function(up){
                if (!up || !up.success || !up.document || !up.document.id) {
                  alert((up && up.message) || 'Upload failed');
                  uploadAssignBtn.disabled = false; uploadAssignBtn.textContent = prevUp;
                  return;
                }
                var documentId = up.document.id;
                return fetch(window.location.origin + '/provider/document-request/' + req.id + '/assign-document', {
                  method: 'POST',
                  headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                  credentials: 'same-origin',
                  body: JSON.stringify({ documentId: documentId })
                }).then(function(r){ if (!r.ok) { throw new Error('HTTP ' + r.status); } var ct2 = r.headers.get('content-type') || ''; if (ct2.indexOf('application/json') === -1) { throw new Error('Invalid content type'); } return r.json(); });
              }).then(function(res){
                if (!res) return;
                if (res && res.success) {
                  status.textContent = 'Provided';
                  status.style.color = '#0aad4f';
                  if (select) select.disabled = true;
                  if (assignBtn) assignBtn.disabled = true;
                  if (fileInput) fileInput.disabled = true;
                  if (uploadAssignBtn) uploadAssignBtn.disabled = true;
                  showSuccess('Document uploaded and submitted successfully');
                } else {
                  alert((res && res.message) || 'Failed to submit uploaded document');
                  uploadAssignBtn.disabled = false; uploadAssignBtn.textContent = prevUp;
                  if (status) { status.textContent = 'Failed'; status.style.color = '#dc3545'; }
                }
              }).catch(function(){ alert('Network error while uploading/assigning document'); uploadAssignBtn.disabled = false; uploadAssignBtn.textContent = prevUp; if (status) { status.textContent = 'Failed'; status.style.color = '#dc3545'; } });
            });
            row.setAttribute('data-request-id', req.id);
            row.appendChild(name);
            row.appendChild(status);
            if (select) row.appendChild(select);
            if (assignBtn) row.appendChild(assignBtn);
            if (fileInput) row.appendChild(fileInput);
            if (uploadAssignBtn) row.appendChild(uploadAssignBtn);
            fragment.appendChild(row);
          });
          if (!pendingRequests.length && desiredNames && desiredNames.length) {
            desiredNames.forEach(function(nameTxt){
              var row = document.createElement('div');
              row.style.display = 'flex';
              row.style.alignItems = 'center';
              row.style.gap = '8px';
              row.style.marginBottom = '8px';
              var name = document.createElement('span');
              name.textContent = nameTxt;
              name.style.flex = '1';
              var status = document.createElement('span');
              status.className = 'badge badge-warning bg-warning text-dark';
              status.textContent = 'Pending';
              var fileInput = document.createElement('input');
              fileInput.type = 'file';
              fileInput.accept = '.pdf,.jpg,.jpeg,.png,.doc,.docx';
              fileInput.style.flex = '1';
              fileInput.className = 'form-control form-control-sm';
              var uploadBtn = document.createElement('button');
              uploadBtn.type = 'button';
              uploadBtn.className = 'btn btn-sm btn-secondary';
              uploadBtn.textContent = 'Upload';
              uploadBtn.addEventListener('click', function(){
                var file = fileInput.files && fileInput.files[0];
                if (!file) { alert('Select a file to upload'); return; }
                var fd = new FormData();
                fd.append('file', file);
                fd.append('category', nameTxt);
                fetch(window.location.origin + '/provider/documents/upload-ajax', {
                  method: 'POST',
                  credentials: 'same-origin',
                  body: fd
                }).then(function(r){ return r.json(); }).then(function(up){
                  if (!up || !up.success || !up.document || !up.document.id) {
                    alert((up && up.message) || 'Upload failed');
                    return;
                  }
                  status.textContent = 'Uploaded';
                  status.style.color = '#0aad4f';
                  fileInput.disabled = true;
                  uploadBtn.disabled = true;
                }).catch(function(){ alert('Network error while uploading document'); });
              });
              row.appendChild(name);
              row.appendChild(status);
              row.appendChild(fileInput);
              row.appendChild(uploadBtn);
              fragment.appendChild(row);
            });
          }
          assignedListEl.appendChild(fragment);
          var submitAllBtn = document.getElementById('submitAllDocsBtn');
          if (submitAllBtn) {
            submitAllBtn.disabled = false;
            submitAllBtn.textContent = 'Submit All';
            submitAllBtn.onclick = function(e){ if (e && e.preventDefault) e.preventDefault(); if (e && e.stopPropagation) e.stopPropagation(); submitAllNow(); };
          }
          updated = true;
        }).catch(function(){
          assignedListEl.innerHTML = '<div class="text-danger" style="font-size: 12px;">Failed to load document requests.</div>';
          if (assignedContentEl) assignedContentEl.textContent = 'Failed to load document requests.';
          var submitAllBtnErr = document.getElementById('submitAllDocsBtn');
          if (submitAllBtnErr) {
            submitAllBtnErr.disabled = true;
            submitAllBtnErr.textContent = 'Close';
            submitAllBtnErr.onclick = function(){
              var modal = assignedModalEl;
              if (!modal) return;
              if (window.bootstrap && window.bootstrap.Modal) {
                try { window.bootstrap.Modal.getOrCreateInstance(modal).hide(); } catch(_){}
              } else if ((window.$ || window.jQuery) && ((window.$ && window.$.fn && window.$.fn.modal) || (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal))) {
                try { (window.$ || window.jQuery)(modal).modal('hide'); } catch(_){}
              } else {
                modal.classList.remove('show');
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
              }
            };
          }
        });
        setTimeout(function(){
          try {
            if (!updated) {
              if (assignedTitleEl) assignedTitleEl.textContent = 'Pending Document Requests';
              if (assignedContentEl) assignedContentEl.textContent = (desiredNames && desiredNames.length) ? ('Please submit: ' + desiredNames.join(', ')) : 'No pending documents.';
              var submitAllBtnFS = document.getElementById('submitAllDocsBtn');
              if (submitAllBtnFS) {
                submitAllBtnFS.disabled = true;
                submitAllBtnFS.textContent = 'Submit All';
              }
            }
          } catch (_) {}
        }, 2000);
      }

      var modalEl = assignedModalEl;
      if (modalEl) {
        if (window.bootstrap && window.bootstrap.Modal) {
          var m = window.bootstrap.Modal.getOrCreateInstance(modalEl);
          m.show();
        } else if ((window.$ || window.jQuery) && ((window.$ && window.$.fn && window.$.fn.modal) || (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal))) {
          var $ = window.$ || window.jQuery;
          $(modalEl).modal('show');
        } else {
          modalEl.classList.add('show');
          modalEl.style.display = 'flex';
          modalEl.style.position = 'fixed';
          modalEl.style.top = '0';
          modalEl.style.left = '0';
          modalEl.style.width = '100vw';
          modalEl.style.height = '100vh';
          modalEl.style.alignItems = 'center';
          modalEl.style.justifyContent = 'center';
          modalEl.style.background = 'rgba(0,0,0,0.5)';
          modalEl.style.zIndex = '100005';
          modalEl.style.pointerEvents = 'auto';
          modalEl.setAttribute('aria-hidden', 'false');
        }
      }
      return false;
    } catch (err) {
      console.error('Failed to open assigned docs modal (external):', err);
      return false;
    }
  };
  window.openInterviewModal = function(e, el){
    try {
      if (typeof e !== 'undefined') {
        if (typeof e.preventDefault === 'function') e.preventDefault();
        if (typeof e.stopPropagation === 'function') e.stopPropagation();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
      }
      var targetEl = (e && e.target) ? e.target : el;
      if (el && el.classList && el.classList.contains('todo-icon')) { el = el.parentElement || el; }
      var wrapper = targetEl && targetEl.closest ? (targetEl.closest('.todo-icon-wrapper') || el) : el;
      var icon = null;
      try { icon = (wrapper && wrapper.querySelector) ? wrapper.querySelector('.todo-icon') : null; } catch(_) { icon = null; }
      function readId(node, attr) {
        if (!node) return '';
        try {
          if (node.getAttribute && node.getAttribute(attr)) return node.getAttribute(attr);
          if (node.dataset) {
            if (attr === 'data-application-id' && (node.dataset.applicationId || node.dataset.applicationID)) return node.dataset.applicationId || node.dataset.applicationID;
            if (attr === 'data-job-id' && (node.dataset.jobId || node.dataset.jobID)) return node.dataset.jobId || node.dataset.jobID;
          }
        } catch(_) {}
        return '';
      }
      var appId = readId(wrapper, 'data-application-id') || readId(icon, 'data-application-id');
      var jobId = readId(wrapper, 'data-job-id') || readId(icon, 'data-job-id');
      if ((!appId || !jobId) && targetEl && targetEl.closest) {
        var nearestWithIds = targetEl.closest('[data-application-id],[data-job-id]');
        appId = appId || readId(nearestWithIds, 'data-application-id');
        jobId = jobId || readId(nearestWithIds, 'data-job-id');
      }
      if (!appId || !jobId) {
        var probe = wrapper;
        var hops = 0;
        while (probe && hops < 5 && (!appId || !jobId)) {
          appId = appId || readId(probe, 'data-application-id');
          jobId = jobId || readId(probe, 'data-job-id');
          probe = probe.parentElement;
          hops++;
        }
      }
      var modalEl = document.getElementById('interviewModalProvider');
      if (!modalEl) {
        modalEl = document.createElement('div');
        modalEl.className = 'modal fade';
        modalEl.id = 'interviewModalProvider';
        modalEl.tabIndex = -1;
        modalEl.setAttribute('role', 'dialog');
        modalEl.setAttribute('aria-labelledby', 'interviewModalLabel');
        modalEl.setAttribute('aria-hidden', 'true');
        modalEl.innerHTML = '<div class="modal-dialog modal-lg" role="document">\n          <div class="modal-content">\n            <div class="modal-header">\n              <h5 class="modal-title" id="interviewModalLabel">Interview Details</h5>\n              <button type="button" class="btn-close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"></button>\n            </div>\n            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">\n              <div id="interviewContent" class="list-group"></div>\n            </div>\n            <div class="modal-footer">\n              <a id="viewCalendarBtn" class="btn btn-outline-primary" target="_blank">View Calendar</a>\n              <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Close</button>\n            </div>\n          </div>\n        </div>';
        document.body.appendChild(modalEl);
        console.log('[Interview Modal] Modal element created and added to DOM');
      }
      var contentEl = modalEl.querySelector('#interviewContent');
      var titleEl = modalEl.querySelector('#interviewModalLabel');
      var calendarBtn = modalEl.querySelector('#viewCalendarBtn');
      if (titleEl) titleEl.textContent = 'Interview Details';
      if (contentEl) {
        contentEl.innerHTML = '<div class="list-group-item text-muted" style="font-size:12px;">Loading interview…</div>';
      }
      if (calendarBtn) {
        if (jobId) {
          calendarBtn.href = window.location.origin + '/provider/interviews/calendar?jobId=' + encodeURIComponent(jobId) + '&status=interview';
        } else if (appId) {
          calendarBtn.href = window.location.origin + '/provider/interviews/calendar?applicationId=' + encodeURIComponent(appId) + '&status=interview';
        } else {
          calendarBtn.href = window.location.origin + '/provider/interviews/calendar?status=interview';
        }
      }
      var q = jobId ? ('jobId=' + encodeURIComponent(jobId)) : (appId ? ('applicationId=' + encodeURIComponent(appId)) : '');
      var url = window.location.origin + '/provider/interviews/calendar-data' + (q ? ('?' + q) : '');
      console.log('[Interview Modal] Fetching from:', url);
      console.log('[Interview Modal] jobId:', jobId, 'appId:', appId);
      fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
          .then(function(r){ if (!r.ok) { throw new Error('HTTP ' + r.status); } var ct = r.headers.get('content-type') || ''; if (ct.indexOf('application/json') === -1) { throw new Error('Invalid content type'); } return r.json(); })
          .then(function(events){
            console.log('[Interview Modal] Raw response:', events);
            var items = Array.isArray(events) ? events.filter(function(ev){ return (ev && (ev.type === 'interview' || (ev.id && String(ev.id).indexOf('interview_') === 0))); }) : [];
            console.log('[Interview Modal] After type filter:', items);
            if (jobId) {
              items = items.filter(function(ev){ return ev.jobId && String(ev.jobId).toLowerCase() === String(jobId).toLowerCase(); });
            }
            if (!jobId && appId) {
              items = items.filter(function(ev){ return ev.applicationId && String(ev.applicationId).toLowerCase() === String(appId).toLowerCase(); });
            }
            console.log('[Interview Modal] Final items:', items);
            var fragment = document.createDocumentFragment();
            if (!items.length) {
              var msg = document.createElement('div');
              msg.className = 'list-group-item text-muted';
              msg.style.fontSize = '14px';
              msg.style.padding = '20px';
              msg.textContent = jobId ? 'No interview scheduled for this job.' : 'No interview scheduled.';
              fragment.appendChild(msg);
            } else {
              items.forEach(function(ev){
                var row = document.createElement('div');
                row.className = 'list-group-item';
                row.style.padding = '15px';
                row.style.borderBottom = '1px solid #dee2e6';
                var when = ev.start ? new Date(ev.start) : null;
                var endWhen = ev.end ? new Date(ev.end) : null;
                var dateStr = when ? when.toLocaleDateString([], { year: 'numeric', month: 'long', day: 'numeric' }) : '';
                var timeStr = when ? when.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
                var endTimeStr = endWhen ? endWhen.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
                var title = document.createElement('div');
                title.textContent = (ev.jobTitle ? (ev.jobTitle + ' • ') : '') + (ev.title || 'Interview');
                title.style.fontSize = '16px';
                title.style.fontWeight = '600';
                title.style.marginBottom = '8px';
                var meta = document.createElement('div');
                meta.className = 'd-flex align-items-center';
                meta.style.marginBottom = '10px';
                var badge = document.createElement('span');
                badge.className = 'badge rounded-pill';
                badge.style.fontSize = '12px';
                badge.style.fontWeight = '600';
                badge.style.background = '#eef2ff';
                badge.style.color = '#111827';
                badge.style.border = '1px solid #c7d2fe';
                badge.style.padding = '4px 8px';
                badge.textContent = (ev.platform || 'Platform') + (dateStr ? (' • ' + dateStr) : '') + (' • ' + (timeStr ? ('From ' + timeStr) : 'From Not Set') + (endTimeStr ? (' — To ' + endTimeStr) : ' — To Not Set'));
                meta.appendChild(badge);
                row.appendChild(title);
                row.appendChild(meta);
                if (ev.url) {
                  var open = document.createElement('a');
                  open.className = 'btn btn-sm btn-primary mt-2';
                  open.href = ev.url; open.target = '_blank';
                  open.textContent = 'Open Meeting Link';
                  row.appendChild(open);
                }
                fragment.appendChild(row);
              });
            }
            contentEl.innerHTML = '';
            contentEl.appendChild(fragment);
          }).catch(function(err){
            console.error('[Interview Modal] Fetch error:', err);
            contentEl.innerHTML = '<div class="list-group-item text-danger" style="font-size:12px;">Failed to load interview: ' + (err.message || 'Unknown error') + '</div>';
          });
      }
      // Remove any existing backdrop
      var backdrop = document.querySelector('.modal-backdrop.fade.show');
      if (backdrop) backdrop.remove();
      
      console.log('[Interview Modal] Attempting to show modal. Bootstrap:', !!(window.bootstrap && window.bootstrap.Modal), 'jQuery:', !!(window.$ || window.jQuery));
      
      if (window.bootstrap && window.bootstrap.Modal) {
        try {
          var m = window.bootstrap.Modal.getOrCreateInstance(modalEl);
          m.show();
          console.log('[Interview Modal] Shown via Bootstrap Modal');
        } catch(err) {
          console.error('[Interview Modal] Bootstrap show error:', err);
        }
      } else if ((window.$ || window.jQuery) && ((window.$ && window.$.fn && window.$.fn.modal) || (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal))) {
        try {
          var $ = window.$ || window.jQuery;
          $(modalEl).modal('show');
          console.log('[Interview Modal] Shown via jQuery modal');
        } catch(err) {
          console.error('[Interview Modal] jQuery show error:', err);
        }
      } else {
        console.log('[Interview Modal] Using fallback CSS display');
        modalEl.classList.add('show');
        modalEl.style.display = 'block';
        modalEl.style.position = 'fixed';
        modalEl.style.top = '0';
        modalEl.style.left = '0';
        modalEl.style.width = '100vw';
        modalEl.style.height = '100vh';
        modalEl.style.alignItems = 'center';
        modalEl.style.justifyContent = 'center';
        modalEl.style.background = 'rgba(0,0,0,0.5)';
        modalEl.style.zIndex = '100005';
        modalEl.style.pointerEvents = 'auto';
        modalEl.style.overflow = 'auto';
        modalEl.setAttribute('aria-hidden', 'false');
        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.style.zIndex = '100004';
        document.body.appendChild(backdrop);
      }
      forceShow(modalEl);
      return false;
    } catch (err) {
      return false;
    }
  };
  function handle(e){
    try {
      var el = e.target.closest('.todo-icon-wrapper') || e.target.closest('.todo-icon');
      if (!el) return;
      // do not stop propagation until we know we will handle the event
      try {
        var wrapper = el.classList.contains('todo-icon') ? el.parentElement : el;
        var icon = wrapper.querySelector('.todo-icon');
        var iconType = icon ? (icon.getAttribute('data-todo-icon-type') || '') : '';
        var todoAction = icon ? (icon.getAttribute('data-todo-action') || '') : (wrapper.getAttribute('data-todo-action') || '');
        var appId = wrapper.getAttribute('data-application-id') || (icon ? icon.getAttribute('data-application-id') : '');
        var jobId = wrapper.getAttribute('data-job-id') || (icon ? icon.getAttribute('data-job-id') : '');
        function navigate(url){ if (url) { window.location.href = url; } }
        function routeByAction(action){
          var a = String(action || '').toLowerCase();
          if (a.indexOf('interview') !== -1) {
            if (typeof window.openInterviewModal === 'function') { window.openInterviewModal(e, wrapper); }
            return true;
          }
          // Defer credentialing and review handling to template-specific script
          if (a.indexOf('credential') !== -1) {
            return false;
          }
          if (a.indexOf('review') !== -1) {
            return false;
          }
          if (a.indexOf('document') !== -1 || a.indexOf('provide') !== -1) {
            if (typeof window.handleTodoIconClick === 'function') { window.handleTodoIconClick(e, wrapper); }
            var modalElDoc = document.getElementById('documentUploadModal') || document.getElementById('assignedDocsModal');
            if (modalElDoc) {
              if (window.bootstrap && window.bootstrap.Modal) {
                var mm = window.bootstrap.Modal.getOrCreateInstance(modalElDoc);
                mm.show();
              } else if ((window.$ || window.jQuery) && ((window.$ && window.$.fn && window.$.fn.modal) || (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal))) {
                var $$ = window.$ || window.jQuery;
                $$(modalElDoc).modal('show');
              } else {
                modalElDoc.classList.add('show');
                modalElDoc.style.display = 'flex';
                modalElDoc.style.position = 'fixed';
                modalElDoc.style.top = '0';
                modalElDoc.style.left = '0';
                modalElDoc.style.width = '100vw';
                modalElDoc.style.height = '100vh';
                modalElDoc.style.alignItems = 'center';
                modalElDoc.style.justifyContent = 'center';
                modalElDoc.style.background = 'rgba(0,0,0,0.5)';
                modalElDoc.style.zIndex = '100005';
                modalElDoc.style.pointerEvents = 'auto';
                modalElDoc.setAttribute('aria-hidden', 'false');
              }
            }
            return true;
          }
          if (a.indexOf('response') !== -1) { if (appId) { navigate(window.location.origin + '/provider/applications/' + appId); return true; } }
          if (a.indexOf('alert') !== -1) { navigate(window.location.origin + '/provider/notifications'); return true; }
          if (appId) { navigate(window.location.origin + '/provider/applications/' + appId); return true; }
          if (jobId) { navigate(window.location.origin + '/provider/jobs/' + jobId + '/detail'); return true; }
          return false;
        }
        if (todoAction) {
          var handled = routeByAction(todoAction);
          var actionLower = String(todoAction || '').toLowerCase();
          var isReviewAction = actionLower.indexOf('review') !== -1;
          var isCredentialAction = actionLower.indexOf('credential') !== -1;
          if (isReviewAction || isCredentialAction) {
            return;
          }
          if (handled) {
            if (!isReviewAction) {
              if (typeof e.preventDefault === 'function') e.preventDefault();
              if (typeof e.stopPropagation === 'function') e.stopPropagation();
              if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
            }
            var docM = document.getElementById('assignedDocsModal');
            var intM = document.getElementById('interviewModalProvider');
            var targetModal = intM || docM;
            if (targetModal) {
              if (window.bootstrap && window.bootstrap.Modal) {
                try { window.bootstrap.Modal.getOrCreateInstance(targetModal).show(); setTimeout(function(){ try { window.bootstrap.Modal.getOrCreateInstance(targetModal).show(); } catch(_){ } }, 10); } catch(_){}
              } else if ((window.$ || window.jQuery) && ((window.$ && window.$.fn && window.$.fn.modal) || (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal))) {
                try { (window.$ || window.jQuery)(targetModal).modal('show'); setTimeout(function(){ try { (window.$ || window.jQuery)(targetModal).modal('show'); } catch(_){ } }, 10); } catch(_){}
              } else {
                targetModal.classList.add('show');
                targetModal.style.display = 'flex';
                targetModal.style.position = 'fixed';
                targetModal.style.top = '0';
                targetModal.style.left = '0';
                targetModal.style.width = '100vw';
                targetModal.style.height = '100vh';
                targetModal.style.alignItems = 'center';
                targetModal.style.justifyContent = 'center';
                targetModal.style.background = 'rgba(0,0,0,0.5)';
                targetModal.style.zIndex = '100005';
                targetModal.style.pointerEvents = 'auto';
                targetModal.setAttribute('aria-hidden', 'false');
              }
              forceShow(targetModal);
            }
            return;
          }
        }
        if (iconType === 'interview') {
          if (typeof e.preventDefault === 'function') e.preventDefault();
          if (typeof e.stopPropagation === 'function') e.stopPropagation();
          if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
          if (typeof window.openInterviewModal === 'function') {
            window.openInterviewModal(e, wrapper);
          }
        } else {
          if (iconType === 'review' || iconType === 'credentialing') {
            return;
          }
          if (typeof e.preventDefault === 'function') e.preventDefault();
          if (typeof e.stopPropagation === 'function') e.stopPropagation();
          if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
          if (typeof window.handleTodoIconClick === 'function') {
            window.handleTodoIconClick(e, wrapper);
          }
          var modalEl = document.getElementById('documentUploadModal') || document.getElementById('assignedDocsModal');
          if (modalEl) {
            if (window.bootstrap && window.bootstrap.Modal) {
              var m = window.bootstrap.Modal.getOrCreateInstance(modalEl);
              m.show();
              setTimeout(function(){ try { m.show(); } catch(_){ } }, 10);
            } else if ((window.$ || window.jQuery) && ((window.$ && window.$.fn && window.$.fn.modal) || (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal))) {
              var $ = window.$ || window.jQuery;
              $(modalEl).modal('show');
              setTimeout(function(){ try { $(modalEl).modal('show'); } catch(_){ } }, 10);
            } else {
              modalEl.classList.add('show');
              modalEl.style.display = 'flex';
              modalEl.style.position = 'fixed';
              modalEl.style.top = '0';
              modalEl.style.left = '0';
              modalEl.style.width = '100vw';
              modalEl.style.height = '100vh';
              modalEl.style.alignItems = 'center';
              modalEl.style.justifyContent = 'center';
              modalEl.style.background = 'rgba(0,0,0,0.5)';
              modalEl.style.zIndex = '100005';
              modalEl.style.pointerEvents = 'auto';
              modalEl.setAttribute('aria-hidden', 'false');
            }
            forceShow(modalEl);
          }
        }
      } catch (_) {}
    } catch(err) {
      // no-op
    }
  }
  function attach(){
    document.addEventListener('click', handle, true);
    document.addEventListener('mousedown', handle, true);
    document.addEventListener('pointerdown', handle, true);
    document.addEventListener('touchstart', handle, true);

    try {
      var clickableEls = document.querySelectorAll('.todo-icon-wrapper, .todo-icon');
      clickableEls.forEach(function(el){
        el.style.cursor = 'pointer';
        el.style.pointerEvents = 'auto';
        if (!el.__todoClickBound) {
          el.addEventListener('click', function(ev){ handle(ev); }, false);
          el.addEventListener('touchstart', function(ev){ handle(ev); }, false);
          el.addEventListener('keydown', function(ev){
            var key = ev.key || ev.code;
            if (key === 'Enter' || key === ' ' || key === 'Spacebar') { handle(ev); }
          }, false);
          el.__todoClickBound = true;
        }
      });
    } catch (_) {}

    try {
      document.addEventListener('click', function(ev){
        var t = ev.target;
        var trigger = t && t.closest ? t.closest('[data-target="#assignedDocsModal"],[aria-controls="assignedDocsModal"],.todo-icon-wrapper,.todo-icon') : null;
        if (trigger) { handle(ev); }
      }, false);
    } catch (_) {}

    // Ensure any legacy requested-doc popup is removed
    try {
      var legacy = document.getElementById('todo-popup');
      if (legacy && legacy.parentNode) {
        legacy.parentNode.removeChild(legacy);
      }
      if (window.todoPopup) {
        if (window.todoPopup.parentNode) window.todoPopup.parentNode.removeChild(window.todoPopup);
        window.todoPopup = null;
      }
    } catch (_) {}
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attach);
  } else {
    attach();
  }
})();
