<?php
/*
|--------------------------------------------------------------------------
| Social Content Production — Manager view
|--------------------------------------------------------------------------
|
| The "manufacturing" stage between raw content entry (clientSocialContent,
| filled in on social-data-entry.php / social-overview.php) and publishing
| (socialPosts, handled entirely by SocialPostEngine.php — untouched here).
|
| This page never writes to socialPosts. PRODUCTION_READY is a business
| state only; the handoff to Social Media Automation is a future phase.
|
*/
include __DIR__ . "/../includes/auth.php";
include __DIR__ . "/../includes/db.php";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Content Production</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item">Social Media</li>
                    <li class="breadcrumb-item active" aria-current="page">Content Production</li>
                </ol>
            </div>
            <div class="d-flex gap-2">
                <a href="social-data-entry" class="btn btn-light btn-sm">
                    <i class="ri-edit-box-line me-1"></i> Data Entry
                </a>
                <a href="social-overview" class="btn btn-light btn-sm">
                    <i class="ri-grid-line me-1"></i> Overview
                </a>
            </div>
        </div>

        <!-- ============================ FILTER BAR ============================ -->
        <div class="card custom-card scp-filterbar">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-xl-2 col-md-4">
                        <label for="scpMonth">Month</label>
                        <input type="month" class="form-control form-control-sm" id="scpMonth">
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <label for="scpStatus">Status</label>
                        <select class="form-select form-select-sm" id="scpStatus">
                            <option value="">All Status</option>
                            <option value="NEW">New</option>
                            <option value="ASSIGNED">Assigned</option>
                            <option value="IN_PROGRESS">In Progress</option>
                            <option value="SUBMITTED">Submitted</option>
                            <option value="CORRECTION">Correction</option>
                            <option value="APPROVED">Approved</option>
                            <option value="PRODUCTION_READY">Production Ready</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <label for="scpEditor">Editor</label>
                        <select class="form-select form-select-sm" id="scpEditor">
                            <option value="">All Editors</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <label for="scpClient">Client</label>
                        <select class="form-select form-select-sm" id="scpClient">
                            <option value="">All Clients</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-4 d-flex align-items-center pt-3 pt-xl-0">
                        <label class="d-flex align-items-center gap-1 fs-13 text-muted mb-0">
                            <input type="checkbox" class="form-check-input" id="scpOverdue"> Overdue only
                        </label>
                    </div>
                    <div class="col-xl-2 col-md-4 d-flex justify-content-md-end">
                        <button type="button" class="btn btn-sm btn-primary" id="scpRefreshBtn">
                            <i class="ri-refresh-line me-1"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card custom-card">
            <div class="card-header">
                <h5 class="mb-0">Production Queue</h5>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Content</th>
                                <th>Editor</th>
                                <th>Status</th>
                                <th>Due</th>
                                <th>Last Remark</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="scpBody">
                            <tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Assign / Reassign -->
<div class="modal fade" id="scpAssignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="scpAssignTitle">Assign Editor</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="scpAssignId">
                <div class="mb-3">
                    <label class="form-label" for="scpAssignEditor">Video Editor <span class="text-danger">*</span></label>
                    <select class="form-select" id="scpAssignEditor"></select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="scpAssignDue">Due (TAT)</label>
                    <input type="datetime-local" class="form-control" id="scpAssignDue">
                </div>
                <div class="mb-0">
                    <label class="form-label" for="scpAssignRemark">Note (optional)</label>
                    <textarea class="form-control" id="scpAssignRemark" rows="2" placeholder="e.g. Assigned for product reel"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="scpAssignSaveBtn">Save Assignment</button>
            </div>
        </div>
    </div>
</div>

<!-- Review (approve / correction) -->
<div class="modal fade" id="scpReviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Review Submission</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="scpReviewId">
                <div class="mb-3">
                    <label class="form-label" for="scpReviewRemark">Correction remark <span class="text-muted fs-12">(required only if requesting a correction)</span></label>
                    <textarea class="form-control" id="scpReviewRemark" rows="3" placeholder="e.g. Please shorten the intro by 3 seconds"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-danger btn-sm" id="scpCorrectionBtn">Request Correction</button>
                <button type="button" class="btn btn-success btn-sm" id="scpApproveBtn">Approve</button>
            </div>
        </div>
    </div>
</div>

<!-- Detail & history -->
<div class="modal fade" id="scpDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Production Task</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="scpDetailScope" class="mb-3"></div>
                <h6 class="fs-13 text-muted text-uppercase mb-2">Content Brief</h6>
                <div id="scpDetailBrief" class="mb-3"></div>
                <h6 class="fs-13 text-muted text-uppercase mb-2">Production Output</h6>
                <div id="scpDetailOutput" class="mb-3"></div>
                <h6 class="fs-13 text-muted text-uppercase mb-2">History</h6>
                <div id="scpDetailHistory"></div>
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
    function notify(type, message) { if (window.showToast) window.showToast(type, message); }

    function confirmDialog(opts) {
        return Swal.fire({
            title: opts.title,
            html: opts.html || '',
            icon: opts.icon || 'warning',
            showCancelButton: true,
            confirmButtonText: opts.confirmText || 'Confirm',
            cancelButtonText: 'Cancel',
            confirmButtonColor: opts.color || '#dc3545',
            reverseButtons: true
        });
    }

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

    // ------------------------------------------------------------------
    // BOOTSTRAP FILTERS
    // ------------------------------------------------------------------
    function buildMonthDefault() {
        const now = new Date();
        return now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
    }
    $('#scpMonth').val(buildMonthDefault());

    $.ajax({ url: 'api/social-content-production/get-editors.php', dataType: 'json' }).done(function (res) {
        if (res && res.success) {
            // one fetch, used to populate both the page filter and the
            // Assign Editor modal dropdown — no second editor query
            const options = res.data.map(e => `<option value="${e.id}">${esc(e.fullName)}</option>`).join('');
            $('#scpEditor').append(options);
            $('#scpAssignEditor').html('<option value="">Select editor…</option>' + options);
        }
    });
    $.ajax({ url: 'api/client/getClients.php', dataType: 'json' }).done(function (res) {
        if (res && res.success) {
            $('#scpClient').append(res.data.map(c => `<option value="${c.id}">${esc(c.fullName)}</option>`).join(''));
        }
    });

    // ------------------------------------------------------------------
    // LOAD + RENDER
    // ------------------------------------------------------------------
    function loadTasks() {
        $('#scpBody').html('<tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>');

        $.ajax({
            url: 'api/social-content-production/get-tasks.php',
            data: {
                status: $('#scpStatus').val(),
                editorId: $('#scpEditor').val(),
                clientId: $('#scpClient').val(),
                month: $('#scpMonth').val(),
                overdue: $('#scpOverdue').is(':checked') ? '1' : '0'
            },
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.success) {
                notify('danger', (res && res.message) || 'Failed to load production tasks.');
                tasks = [];
                renderRows();
                return;
            }
            tasks = res.data || [];
            renderRows();
        }).fail(function () {
            notify('danger', 'Network error while loading production tasks.');
            tasks = [];
            renderRows();
        });
    }

    function actionsFor(task) {
        const btns = [];
        if (task.status === 'NEW') {
            btns.push(`<button class="btn btn-sm btn-primary scp-assign" data-id="${task.id}"><i class="ri-user-add-line"></i> Assign</button>`);
        }
        if (['ASSIGNED', 'IN_PROGRESS', 'CORRECTION'].includes(task.status)) {
            btns.push(`<button class="btn btn-sm btn-outline-secondary scp-assign" data-id="${task.id}" title="Reassign"><i class="ri-user-settings-line"></i></button>`);
        }
        if (task.status === 'SUBMITTED') {
            btns.push(`<button class="btn btn-sm btn-warning scp-review" data-id="${task.id}"><i class="ri-clipboard-line"></i> Review</button>`);
        }
        if (task.status === 'APPROVED') {
            btns.push(`<button class="btn btn-sm btn-success scp-mark-ready" data-id="${task.id}"><i class="ri-checkbox-circle-line"></i> Mark Ready</button>`);
        }
        if (task.status === 'PRODUCTION_READY') {
            btns.push(automationCell(task));
        }
        btns.push(`<button class="btn btn-sm btn-outline-primary scp-view" data-id="${task.id}" title="View history"><i class="ri-eye-line"></i></button>`);
        return btns.join(' ');
    }

    // Phase 4.5 — Send to Automation. Only ever shown for PRODUCTION_READY
    // tasks. Once a handoff row exists (pending/sent/failed) the engine
    // itself will never allow another one for this task (UNIQUE(productionId)),
    // so the button is replaced by a passive status badge in every case —
    // there is no retry action in this phase.
    function automationCell(task) {
        if (task.automationStatus === 'sent') {
            return `<span class="badge bg-success-transparent" title="Automation post #${task.automationSocialPostId || ''}"><i class="ri-send-plane-fill"></i> Sent to Automation</span>`;
        }
        if (task.automationStatus === 'failed') {
            return `<span class="badge bg-danger-transparent" title="${esc(task.automationErrorMessage || 'Automation handoff failed.')}"><i class="ri-error-warning-line"></i> Automation Failed</span>`;
        }
        if (task.automationStatus === 'pending') {
            return `<span class="badge bg-warning-transparent"><i class="ri-time-line"></i> Automation Pending</span>`;
        }
        return `<button class="btn btn-sm btn-dark scp-send-automation" data-id="${task.id}"><i class="ri-send-plane-line"></i> Send to Automation</button>`;
    }

    function renderRows() {
        if (!tasks.length) {
            $('#scpBody').html('<tr><td colspan="7" class="text-center text-muted py-4">No production tasks match the current filters.</td></tr>');
            return;
        }

        $('#scpBody').html(tasks.map(function (task) {
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
                    <td>${task.editorName ? esc(task.editorName) : '<span class="text-muted">Unassigned</span>'}</td>
                    <td>${statusBadge(task.status)}</td>
                    <td>${dueCell(task)}</td>
                    <td class="small text-muted" style="max-width:220px;">${task.lastRemark ? esc(task.lastRemark) : '—'}</td>
                    <td class="text-end text-nowrap">${actionsFor(task)}</td>
                </tr>
            `;
        }).join(''));
    }

    // ------------------------------------------------------------------
    // ASSIGN / REASSIGN
    // ------------------------------------------------------------------
    $(document).on('click', '.scp-assign', function () {
        const task = tasks.find(t => t.id === Number($(this).data('id')));
        if (!task) return;

        $('#scpAssignTitle').text(task.assignedEditorId ? 'Reassign Editor' : 'Assign Editor');
        $('#scpAssignId').val(task.id);
        $('#scpAssignEditor').val(task.assignedEditorId || '');
        $('#scpAssignDue').val(task.dueAt ? task.dueAt.replace(' ', 'T').slice(0, 16) : '');
        $('#scpAssignRemark').val('');
        $('#scpAssignModal').modal('show');
    });

    $('#scpAssignSaveBtn').on('click', function () {
        const editorId = $('#scpAssignEditor').val();
        if (!editorId) { notify('danger', 'Select an editor.'); return; }

        manageTask({
            id: $('#scpAssignId').val(),
            action: 'assign',
            editorId: editorId,
            dueAt: $('#scpAssignDue').val() ? $('#scpAssignDue').val().replace('T', ' ') + ':00' : null,
            remark: $('#scpAssignRemark').val().trim()
        }, function () {
            $('#scpAssignModal').modal('hide');
        });
    });

    // ------------------------------------------------------------------
    // REVIEW
    // ------------------------------------------------------------------
    $(document).on('click', '.scp-review', function () {
        $('#scpReviewId').val($(this).data('id'));
        $('#scpReviewRemark').val('');
        $('#scpReviewModal').modal('show');
    });

    $('#scpApproveBtn').on('click', function () {
        manageTask({ id: $('#scpReviewId').val(), action: 'approve', remark: $('#scpReviewRemark').val().trim() }, function () {
            $('#scpReviewModal').modal('hide');
        });
    });

    $('#scpCorrectionBtn').on('click', function () {
        const remark = $('#scpReviewRemark').val().trim();
        if (!remark) { notify('danger', 'A correction remark is required.'); return; }
        manageTask({ id: $('#scpReviewId').val(), action: 'request_correction', remark: remark }, function () {
            $('#scpReviewModal').modal('hide');
        });
    });

    // ------------------------------------------------------------------
    // MARK READY
    // ------------------------------------------------------------------
    $(document).on('click', '.scp-mark-ready', function () {
        const id = $(this).data('id');
        confirmDialog({
            title: 'Mark this production ready?',
            html: 'This confirms the content is finished and approved. It does not publish or schedule anything.',
            icon: 'question',
            confirmText: 'Mark Ready',
            color: '#198754'
        }).then(res => {
            if (!res.isConfirmed) return;
            manageTask({ id: id, action: 'mark_ready' });
        });
    });

    // ------------------------------------------------------------------
    // SEND TO AUTOMATION (Phase 4.5)
    // ------------------------------------------------------------------
    $(document).on('click', '.scp-send-automation', function () {
        const $btn = $(this);
        if ($btn.prop('disabled')) return; // prevent double-click while a request is already in flight
        const id = $btn.data('id');

        confirmDialog({
            title: 'Send this task to Automation?',
            html: 'This will create a scheduled social post from the approved production output. Only Instagram/Facebook image posts are currently supported.',
            icon: 'question',
            confirmText: 'Send to Automation',
            color: '#212529'
        }).then(res => {
            if (!res.isConfirmed) return;

            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Sending…');

            $.ajax({
                url: 'api/social-content-production/send-to-automation.php',
                type: 'POST',
                contentType: 'application/json',
                headers: { 'X-CSRF-Token': CSRF_TOKEN },
                data: JSON.stringify({ productionId: id }),
                dataType: 'json'
            }).done(function (res) {
                if (!res || !res.success) {
                    notify('danger', (res && res.message) || 'Unable to send this task to Automation.');
                    $btn.prop('disabled', false).html('<i class="ri-send-plane-line"></i> Send to Automation');
                    return;
                }
                notify('success', res.message || 'Sent to Automation.');
                loadTasks(); // re-fetches automationStatus so the row updates to the "Sent" badge
            }).fail(function () {
                notify('danger', 'Network error while sending to Automation.');
                $btn.prop('disabled', false).html('<i class="ri-send-plane-line"></i> Send to Automation');
            });
        });
    });

    // ------------------------------------------------------------------
    // DETAIL / HISTORY
    // ------------------------------------------------------------------
    $(document).on('click', '.scp-view', function () {
        const id = $(this).data('id');
        $.ajax({ url: 'api/social-content-production/get-tasks.php', data: { id: id }, dataType: 'json' })
            .done(function (res) {
                if (!res || !res.success) { notify('danger', (res && res.message) || 'Failed to load task.'); return; }
                renderDetail(res.data);
                $('#scpDetailModal').modal('show');
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
        $('#scpDetailScope').html(`
            <div class="d-flex flex-wrap gap-3 fs-13">
                <span>Client: <b>${esc(task.clientName)}</b></span>
                <span>${esc(task.platformName)} · ${esc(task.featureName)}</span>
                <span>Date: <b>${fmtDate(task.contentDate)}</b></span>
                <span>Status: ${statusBadge(task.status)}</span>
                <span>Editor: <b>${task.editorName ? esc(task.editorName) : 'Unassigned'}</b></span>
                <span>Due: ${dueCell(task)}</span>
            </div>
        `);

        $('#scpDetailBrief').html(renderBrief(task));
        $('#scpDetailOutput').html(renderOutput(task));

        const history = task.history || [];
        if (!history.length) {
            $('#scpDetailHistory').html('<div class="text-muted fs-13">No history yet.</div>');
            return;
        }

        $('#scpDetailHistory').html(history.map(h => `
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

    // ------------------------------------------------------------------
    // SHARED MUTATION CALL
    // ------------------------------------------------------------------
    function manageTask(payload, onSuccess) {
        $.ajax({
            url: 'api/social-content-production/manage-task.php',
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

    // ------------------------------------------------------------------
    // EVENTS
    // ------------------------------------------------------------------
    $('#scpRefreshBtn').on('click', loadTasks);
    $('#scpStatus, #scpEditor, #scpClient, #scpMonth').on('change', loadTasks);
    $('#scpOverdue').on('change', loadTasks);

    loadTasks();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
