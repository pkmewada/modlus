<?php
/*
|--------------------------------------------------------------------------
| My Production Tasks — Video Editor view
|--------------------------------------------------------------------------
|
| Shows only the production tasks assigned to the logged-in employee.
| assignedEditorId scoping is enforced server-side in
| api/social-content-production/emp-*.php, never trusted from the browser.
|
*/
include __DIR__ . '/../includes/emp-auth.php';
include __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/Csrf.php';
include __DIR__ . '/../includes/emp-header.php';
include __DIR__ . '/../includes/emp-sidebar.php';
?>
<script>
    const CSRF_TOKEN = "<?php echo htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>";
</script>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">My Production Tasks</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="emp-dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Production Tasks</li>
                </ol>
            </div>
        </div>

        <div class="card custom-card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">Assigned To Me</h5>

                <div class="d-flex gap-2 flex-wrap">
                    <select class="form-select form-select-sm" id="ecpStatus" style="min-width:150px;">
                        <option value="">All Status</option>
                        <option value="ASSIGNED">Assigned</option>
                        <option value="IN_PROGRESS">In Progress</option>
                        <option value="SUBMITTED">Submitted</option>
                        <option value="CORRECTION">Correction</option>
                        <option value="APPROVED">Approved</option>
                        <option value="PRODUCTION_READY">Production Ready</option>
                    </select>
                    <label class="d-flex align-items-center gap-1 fs-13 text-muted mb-0">
                        <input type="checkbox" class="form-check-input" id="ecpOverdue"> Overdue only
                    </label>
                    <button type="button" class="btn btn-sm btn-primary" id="ecpRefreshBtn">
                        <i class="ri-refresh-line"></i>
                    </button>
                </div>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Content</th>
                                <th>Status</th>
                                <th>Due</th>
                                <th>Last Remark</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="ecpBody">
                            <tr><td colspan="6" class="text-center text-muted py-4">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Submit for review -->
<div class="modal fade" id="ecpSubmitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Submit For Review</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="ecpSubmitId">

                <label class="form-label d-block">Submission Method <span class="text-danger">*</span></label>
                <div class="d-flex gap-3 mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="ecpSubmissionType" id="ecpTypeDrive" value="drive" checked>
                        <label class="form-check-label" for="ecpTypeDrive">Google Drive Link</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="ecpSubmissionType" id="ecpTypeMedia" value="media">
                        <label class="form-check-label" for="ecpTypeMedia">Upload Media</label>
                    </div>
                </div>

                <div id="ecpDriveField" class="mb-3">
                    <label class="form-label" for="ecpDriveUrl">Google Drive Link <span class="text-danger">*</span></label>
                    <input type="url" class="form-control" id="ecpDriveUrl" placeholder="https://drive.google.com/...">
                </div>

                <div id="ecpMediaField" class="mb-3 d-none">
                    <label class="form-label" for="ecpMediaFile">Upload Media <span class="text-danger">*</span></label>
                    <input type="file" class="form-control" id="ecpMediaFile" accept="video/mp4,video/quicktime,video/webm,image/jpeg,image/png">
                    <div class="fs-11 text-muted mt-1">Video (mp4, mov, webm) or image (jpg, png), up to 100MB.</div>
                </div>

                <label class="form-label" for="ecpSubmitRemark">Note (optional)</label>
                <textarea class="form-control" id="ecpSubmitRemark" rows="2" placeholder="e.g. First cut ready for review"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="ecpSubmitSaveBtn">Submit Production</button>
            </div>
        </div>
    </div>
</div>

<!-- Detail & history -->
<div class="modal fade" id="ecpDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Production Task</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="ecpDetailScope" class="mb-3"></div>
                <h6 class="fs-13 text-muted text-uppercase mb-2">Content Brief</h6>
                <div id="ecpDetailBrief" class="mb-3"></div>
                <h6 class="fs-13 text-muted text-uppercase mb-2">Production Output</h6>
                <div id="ecpDetailOutput" class="mb-3"></div>
                <h6 class="fs-13 text-muted text-uppercase mb-2">History</h6>
                <div id="ecpDetailHistory"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(function () {

    const STATUS_LABEL = {
        NEW: 'New', ASSIGNED: 'Assigned', IN_PROGRESS: 'In Progress', SUBMITTED: 'Submitted',
        CORRECTION: 'Correction', APPROVED: 'Approved', PRODUCTION_READY: 'Production Ready'
    };
    const STATUS_COLOR = {
        NEW: 'secondary', ASSIGNED: 'info', IN_PROGRESS: 'primary', SUBMITTED: 'warning',
        CORRECTION: 'danger', APPROVED: 'success', PRODUCTION_READY: 'dark'
    };

    let tasks = [];

    function esc(str) { return $('<div>').text(str == null ? '' : String(str)).html(); }
    function notify(type, message) { if (window.showToast) window.showToast(type, message); else if (window.Swal) Swal.fire({ icon: type === 'danger' ? 'error' : type, text: message }); }

    function fmtDate(d) {
        if (!d) return '—';
        return new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }
    function fmtDateTime(dt) {
        if (!dt) return '—';
        const d = new Date(String(dt).replace(' ', 'T'));
        if (isNaN(d.getTime())) return dt;
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) + ' ' +
               d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    }

    function statusBadge(status) {
        return `<span class="badge bg-${STATUS_COLOR[status] || 'secondary'}">${esc(STATUS_LABEL[status] || status)}</span>`;
    }

    function dueCell(task) {
        if (!task.dueAt) return '<span class="text-muted">—</span>';
        const overdue = new Date(task.dueAt.replace(' ', 'T')) < new Date() && !['APPROVED', 'PRODUCTION_READY'].includes(task.status);
        return `<span class="${overdue ? 'text-danger fw-semibold' : ''}">${esc(fmtDateTime(task.dueAt))}${overdue ? ' <i class="ri-error-warning-line" title="Overdue"></i>' : ''}</span>`;
    }

    function loadTasks() {
        $('#ecpBody').html('<tr><td colspan="6" class="text-center text-muted py-4">Loading...</td></tr>');

        $.ajax({
            url: 'api/social-content-production/emp-get-tasks.php',
            data: {
                status: $('#ecpStatus').val(),
                overdue: $('#ecpOverdue').is(':checked') ? '1' : '0'
            },
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.success) {
                notify('danger', (res && res.message) || 'Failed to load your production tasks.');
                tasks = [];
                renderRows();
                return;
            }
            tasks = res.data || [];
            renderRows();
        }).fail(function () {
            notify('danger', 'Network error while loading your production tasks.');
            tasks = [];
            renderRows();
        });
    }

    function actionsFor(task) {
        const btns = [];
        if (task.status === 'ASSIGNED' || task.status === 'CORRECTION') {
            btns.push(`<button class="btn btn-sm btn-primary ecp-start" data-id="${task.id}"><i class="ri-play-line"></i> ${task.status === 'CORRECTION' ? 'Resume' : 'Start'}</button>`);
        }
        if (task.status === 'IN_PROGRESS') {
            btns.push(`<button class="btn btn-sm btn-success ecp-submit" data-id="${task.id}"><i class="ri-send-plane-line"></i> Submit</button>`);
        }
        btns.push(`<button class="btn btn-sm btn-outline-primary ecp-view" data-id="${task.id}" title="View history"><i class="ri-eye-line"></i></button>`);
        return btns.join(' ');
    }

    function renderRows() {
        if (!tasks.length) {
            $('#ecpBody').html('<tr><td colspan="6" class="text-center text-muted py-4">No production tasks assigned to you right now.</td></tr>');
            return;
        }

        $('#ecpBody').html(tasks.map(function (task) {
            return `
                <tr>
                    <td>
                        <div class="fw-semibold">${esc(task.clientName)}</div>
                        <div class="text-muted small">${esc(task.clientCode || '')}</div>
                    </td>
                    <td>
                        <div>${esc(task.platformName)} · ${esc(task.featureName)}</div>
                        <div class="text-muted small">${fmtDate(task.contentDate)}${task.title ? ' · ' + esc(task.title) : ''}</div>
                    </td>
                    <td>${statusBadge(task.status)}</td>
                    <td>${dueCell(task)}</td>
                    <td class="small text-muted" style="max-width:220px;">${task.lastRemark ? esc(task.lastRemark) : '—'}</td>
                    <td class="text-end text-nowrap">${actionsFor(task)}</td>
                </tr>
            `;
        }).join(''));
    }

    $(document).on('click', '.ecp-start', function () {
        updateTask({ id: $(this).data('id'), action: 'start' });
    });

    $(document).on('click', '.ecp-submit', function () {
        $('#ecpSubmitId').val($(this).data('id'));
        $('#ecpSubmitRemark').val('');
        $('#ecpDriveUrl').val('');
        $('#ecpMediaFile').val('');
        $('#ecpTypeDrive').prop('checked', true);
        $('#ecpDriveField').removeClass('d-none');
        $('#ecpMediaField').addClass('d-none');
        $('#ecpSubmitModal').modal('show');
    });

    $('input[name="ecpSubmissionType"]').on('change', function () {
        const isDrive = $('#ecpTypeDrive').is(':checked');
        $('#ecpDriveField').toggleClass('d-none', !isDrive);
        $('#ecpMediaField').toggleClass('d-none', isDrive);
    });

    $('#ecpSubmitSaveBtn').on('click', function () {
        const id = $('#ecpSubmitId').val();
        const submissionType = $('#ecpTypeDrive').is(':checked') ? 'drive' : 'media';
        const remark = $('#ecpSubmitRemark').val().trim();

        if (submissionType === 'drive' && !$('#ecpDriveUrl').val().trim()) {
            notify('danger', 'Enter a Google Drive link.');
            return;
        }
        if (submissionType === 'media' && !$('#ecpMediaFile')[0].files.length) {
            notify('danger', 'Choose a file to upload.');
            return;
        }

        const formData = new FormData();
        formData.append('id', id);
        formData.append('submissionType', submissionType);
        formData.append('remark', remark);
        if (submissionType === 'drive') {
            formData.append('submissionUrl', $('#ecpDriveUrl').val().trim());
        } else {
            formData.append('media', $('#ecpMediaFile')[0].files[0]);
        }

        const $btn = $(this).prop('disabled', true);
        $.ajax({
            url: 'api/social-content-production/emp-submit-production.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: { 'X-CSRF-Token': CSRF_TOKEN },
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.success) {
                notify('danger', (res && res.message) || 'Failed to submit production.');
                return;
            }
            notify('success', 'Submitted for review.');
            $('#ecpSubmitModal').modal('hide');
            loadTasks();
        }).fail(function () {
            notify('danger', 'Network error while submitting.');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    $(document).on('click', '.ecp-view', function () {
        const id = $(this).data('id');
        $.ajax({ url: 'api/social-content-production/emp-get-tasks.php', data: { id: id }, dataType: 'json' })
            .done(function (res) {
                if (!res || !res.success) { notify('danger', (res && res.message) || 'Failed to load task.'); return; }
                renderDetail(res.data);
                $('#ecpDetailModal').modal('show');
            });
    });

    // Read-only content brief — sourced entirely from clientSocialContent via
    // the engine's existing join, never editable here. Data Entry owns these
    // fields; Production only displays them.
    function linkOrText(value) {
        return /^https?:\/\//i.test(value)
            ? `<a href="${esc(value)}" target="_blank" rel="noopener noreferrer">${esc(value)}</a>`
            : esc(value);
    }

    function briefRow(label, value, isLink) {
        if (value === null || value === undefined) return '';
        const text = String(value).trim();
        if (text === '') return '';
        const rendered = isLink ? linkOrText(text) : esc(text).replace(/\n/g, '<br>');
        return `<div class="mb-2"><div class="fs-11 text-uppercase text-muted fw-semibold">${esc(label)}</div><div class="fs-13">${rendered}</div></div>`;
    }

    function renderBrief(task) {
        const rows = [
            briefRow('Title', task.title),
            briefRow('Raw Content', task.rawContent),
            briefRow('Caption', task.caption),
            briefRow('Description', task.contentDescription),
            briefRow('Song / Audio', task.songUrl, true),
            briefRow('Reference (Idea)', task.ideaReference),
            briefRow('Reference Link', task.referenceLink, true),
            briefRow('Social Media Handle', task.socialMediaHandle),
            briefRow('Post Type', task.postType),
            briefRow('Data Entry Remarks', task.contentRemarks)
        ].filter(Boolean).join('');

        return rows || '<div class="text-muted fs-13">No additional content details were provided in Data Entry.</div>';
    }

    // Production output — the editor's actual submission (Drive link or
    // uploaded file). Read from socialContentProduction.submissionType/Url
    // (the LATEST submission only); the editor's own note for it lives in
    // the matching 'submitted' history entry, shown there rather than
    // duplicated here.
    function renderOutput(task) {
        if (!task.submissionType || !task.submissionUrl) {
            return '<div class="text-muted fs-13">No production output submitted yet.</div>';
        }

        const isDrive = task.submissionType === 'drive';
        const url = task.submissionUrl;
        const ext = (String(url).split('.').pop() || '').toLowerCase().split(/[?#]/)[0];
        const isVideo = ['mp4', 'mov', 'webm'].includes(ext);

        const openLink = `<a href="${esc(url)}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">` +
            `<i class="ri-external-link-line"></i> ${isDrive ? 'Open Google Drive' : 'View / Open Media'}</a>`;

        const preview = (!isDrive && isVideo)
            ? `<video controls class="mt-2 d-block" style="max-width:100%; max-height:260px;"><source src="${esc(url)}"></video>`
            : '';

        const meta = [
            task.editorName ? 'Submitted by <b>' + esc(task.editorName) + '</b>' : '',
            task.submittedAt ? esc(fmtDateTime(task.submittedAt)) : ''
        ].filter(Boolean).join(' · ');

        return `
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                <span class="badge bg-info-transparent">${isDrive ? 'Google Drive' : 'Uploaded Media'}</span>
                ${openLink}
            </div>
            ${preview}
            ${meta ? `<div class="fs-12 text-muted mt-2">${meta}</div>` : ''}
        `;
    }

    function renderDetail(task) {
        $('#ecpDetailScope').html(`
            <div class="d-flex flex-wrap gap-3 fs-13">
                <span>Client: <b>${esc(task.clientName)}</b></span>
                <span>${esc(task.platformName)} · ${esc(task.featureName)}</span>
                <span>Date: <b>${fmtDate(task.contentDate)}</b></span>
                <span>Status: ${statusBadge(task.status)}</span>
                <span>Due: ${dueCell(task)}</span>
            </div>
        `);

        $('#ecpDetailBrief').html(renderBrief(task));
        $('#ecpDetailOutput').html(renderOutput(task));

        const history = task.history || [];
        if (!history.length) {
            $('#ecpDetailHistory').html('<div class="text-muted fs-13">No history yet.</div>');
            return;
        }

        $('#ecpDetailHistory').html(history.map(h => `
            <div class="border-bottom py-2">
                <div class="d-flex justify-content-between">
                    <span class="fw-semibold fs-13">${esc(h.action.replace(/_/g, ' '))}</span>
                    <span class="text-muted fs-12">${esc(fmtDateTime(h.createdAt))}</span>
                </div>
                <div class="fs-12 text-muted">${esc(h.performedByName || 'Unknown')} (${esc(h.performedByType)})${h.oldStatus || h.newStatus ? ' · ' + esc(h.oldStatus || '-') + ' → ' + esc(h.newStatus || '-') : ''}</div>
                ${h.remark ? `<div class="fs-13 mt-1">${esc(h.remark)}</div>` : ''}
            </div>
        `).join(''));
    }

    function updateTask(payload, onSuccess) {
        $.ajax({
            url: 'api/social-content-production/emp-update-task.php',
            type: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-Token': CSRF_TOKEN },
            data: JSON.stringify(payload),
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.success) {
                notify('danger', (res && res.message) || 'Action failed.');
                return;
            }
            notify('success', 'Saved.');
            onSuccess && onSuccess();
            loadTasks();
        }).fail(function () {
            notify('danger', 'Network error.');
        });
    }

    $('#ecpRefreshBtn').on('click', loadTasks);
    $('#ecpStatus').on('change', loadTasks);
    $('#ecpOverdue').on('change', loadTasks);

    loadTasks();
});
</script>

<?php include __DIR__ . '/../includes/emp-footer.php'; ?>
