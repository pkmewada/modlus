<?php
include __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$allowedStatuses = ['new', 'contacted', 'qualified', 'closed'];
$updateError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leadId = (int) ($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');

    if ($leadId > 0 && in_array($status, $allowedStatuses, true)) {
        $updateStmt = mysqli_prepare($con, 'UPDATE leads SET status = ? WHERE id = ?');

        if ($updateStmt) {
            mysqli_stmt_bind_param($updateStmt, 'si', $status, $leadId);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);
            redirectTo('leads');
        } else {
            $updateError = 'Unable to update lead status right now.';
        }
    } else {
        $updateError = 'Invalid lead status update request.';
    }
}

$result = null;
$selectStmt = mysqli_prepare($con, 'SELECT * FROM leads ORDER BY id DESC');

if ($selectStmt) {
    mysqli_stmt_execute($selectStmt);
    $result = mysqli_stmt_get_result($selectStmt);
} else {
    $updateError = 'Unable to load leads right now.';
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css">

<!-- Prism CSS -->
<link rel="stylesheet" href="/mamix/mamix_esbuild/dist/assets/libs/prismjs/themes/prism-coy.min.css">
<style>
.lead-status-select {
    min-width: 140px;
    font-weight: 600;
    border-width: 1px;
}

.lead-status-new {
 
    border-color: #000000;
    color: #000000;
}

.lead-status-contacted {
    
    border-color: #9ec5fe;
    color: #0d6efd;
}

.lead-status-qualified {
    
    border-color: #ffda6a;
    color: #b35c00;
}

.lead-status-closed {
 
    border-color: #75b798;
    color: #146c43;
}

.leads-table-filters {
    display: inline-flex;
    gap: 0.5rem;
    margin-left: 0.75rem;
    vertical-align: middle;
}

.leads-table-filters .form-select {
    min-width: 140px;
}
</style>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content app-content">
    <div class="container-fluid">
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Leads</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Leads</li>
                </ol>
            </div>
            <div>
                <button type="button" class="btn btn-primary btn-wave waves-effect waves-light" data-bs-toggle="modal"
                    data-bs-target="#addLeadModal">
                    <i class="ri-user-add-line align-middle me-1"></i>Add Lead
                </button>
            </div>
        </div>

        <?php if ($updateError !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($updateError, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <div class="btn-list">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-outline-primary dropdown-toggle"
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                            Export
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item export-btn" data-type="csv"
                                                    href="javascript:void(0);">CSV</a></li>
                                            <li><a class="dropdown-item export-btn" data-type="pdf"
                                                    href="javascript:void(0);">PDF</a></li>
                                        </ul>
                                    </div>
                                </div>
                                <select id="statusFilter" class="form-select form-select-lg">
                                    <option value="">Status</option>
                                </select>
                                <select id="sourceFilter" class="form-select form-select-lg">
                                    <option value="">Source</option>
                                </select>
                            </div>
                            <div class="flex-fill"></div>
                            <div class="d-flex">
                                <input id="tableSearch" class="form-control form-control-sm"
                                    placeholder="Search leads..." autocomplete="off">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title">
                            Leads DataTable
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="leads-datatable" data-ui-table="mamix" class="table table-hover text-nowrap">
                                <thead>
                                    <tr>
                                        <th>SNo</th>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>Source</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                    <?php $sno = 1; ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $sno++; ?></td>
                                        <td><?php echo htmlspecialchars($row['fullName'], ENT_QUOTES, 'UTF-8'); ?>
                                            <small
                                                class="d-block text-muted"><?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['phone'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['source'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <div class="btn-group" data-id="<?php echo (int) $row['id']; ?>">
                                                <button type="button"
                                                    class="btn btn-sm dropdown-toggle lead-status-btn lead-status-<?php echo htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-bs-toggle="dropdown" aria-expanded="false"
                                                    data-status="<?php echo htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo htmlspecialchars(ucfirst($row['status']), ENT_QUOTES, 'UTF-8'); ?>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php foreach ($allowedStatuses as $statusOption): ?>
                                                    <li><a class="dropdown-item change-status"
                                                            href="javascript:void(0);"
                                                            data-status="<?php echo htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(ucfirst($statusOption), ENT_QUOTES, 'UTF-8'); ?></a>
                                                    </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars(date('d M Y h:i A', strtotime($row['createdAt'])), ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td>
                                            <a href="javascript:void(0);"
                                                class="btn btn-icon btn-sm btn-info-light btn-wave waves-effect waves-light edit-lead-btn"
                                                data-id="<?php echo (int) $row['id']; ?>"
                                                data-fullname="<?php echo htmlspecialchars($row['fullName'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-email="<?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-phone="<?php echo htmlspecialchars($row['phone'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-source="<?php echo htmlspecialchars($row['source'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                            <a href="javascript:void(0);"
                                                class="btn btn-icon btn-sm btn-danger-light btn-wave waves-effect waves-light delete-lead-btn modal-effect"
                                                data-bs-effect="effect-super-scaled"
                                                data-id="<?php echo (int) $row['id']; ?>">
                                                <i class="ri-delete-bin-line"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
      

        <div class="modal fade" id="addLeadModal" tabindex="-1" aria-labelledby="addLeadModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addLeadModalLabel">Add Lead</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="addLeadForm" novalidate>
                        <input type="hidden" id="leadId" name="id" value="">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="modal-fullName" class="form-label text-default">Full Name</label>
                                    <input type="text" class="form-control" id="modal-fullName" name="fullName"
                                        placeholder="Enter Full Name" required>
                                    <div class="invalid-feedback">Full name is required.</div>
                                </div>
                                <div class="col-12">
                                    <label for="modal-email" class="form-label text-default">Email</label>
                                    <input type="email" class="form-control" id="modal-email" name="email" 
                                        placeholder="Enter Email Address" required>
                                    <div class="invalid-feedback">A valid email is required.</div>
                                </div>
                                <div class="col-12">
                                    <label for="modal-phone" class="form-label text-default">Phone</label>
                                    <input type="text" class="form-control" id="modal-phone" name="phone" 
                                        placeholder="Enter Phone Number" required>
                                    <div class="invalid-feedback">Phone is required.</div>
                                </div>
                                <div class="col-12">
                                    <label for="modal-source" class="form-label text-default">Source</label>
                                    <input type="text" class="form-control" id="modal-source" name="source" 
                                        placeholder="Enter Source" required>
                                    <div class="invalid-feedback">Source is required.</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="addLeadSubmitBtn">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="addLeadSubmitSpinner"
                                    role="status" aria-hidden="true"></span>
                                <span id="addLeadSubmitText">Save Lead</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="deleteConfirmModal" data-bs-effect="effect-super-scaled">
            <div class="modal-dialog modal-dialog-centered text-center" role="document">
                <div class="modal-content modal-content-demo">
                    <div class="modal-header">
                        <h6 class="modal-title">Delete Lead</h6><button aria-label="Close" class="btn-close"
                            data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-start">
                        <h6>Are you sure you want to delete this lead?</h6>
                        <p class="text-muted mb-0">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-danger" id="confirmDeleteBtn">Delete</button> <button
                            class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($selectStmt) {
    mysqli_stmt_close($selectStmt);
} ?>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/responsive/2.3.0/js/dataTables.responsive.min.js"></script>
        <script src="https://cdn.datatables.net/responsive/2.3.0/js/responsive.bootstrap.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.bootstrap5.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.6/pdfmake.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>
        <script src="/mamix/mamix_esbuild/dist/assets/libs/sweetalert2/sweetalert2.min.js"></script>
        <script src="/mamix/mamix_esbuild/dist/assets/js/custom.js"></script>

        <script>
        $(function() {
            var statusColumnIndex = 4;
            var sourceColumnIndex = 3;
            var addLeadApiUrl = '/mamix/mamix_esbuild/api/addLead.php';
            var updateLeadApiUrl = '/mamix/mamix_esbuild/api/updateLead.php';
            var updateLeadStatusApiUrl = '/mamix/mamix_esbuild/api/updateLeadStatus.php';
            var deleteLeadApiUrl = '/mamix/mamix_esbuild/api/deleteLead.php';

            var table = $('#leads-datatable').DataTable({
                responsive: true,
                order: [],
                pageLength: 10,
                dom: "t<'row mt-3'<'col-md-5'i><'col-md-7'p>>",
                columnDefs: [{
                    targets: 0,
                    orderable: false,
                    searchable: false
                }, {
                    targets: 6,
                    orderable: false,
                    searchable: false
                }],
                buttons: [{
                        extend: 'csvHtml5',
                        className: 'd-none buttons-csv',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6]
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        className: 'd-none buttons-pdf',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6]
                        }
                    }
                ]
            });

            function populateFilter(selectEl, columnIndex) {
                var uniqueValues = table.column(columnIndex).data().unique().sort().toArray();

                $.each(uniqueValues, function(_, value) {
                    var cleanedValue = $('<div>').html(value).text().trim();
                    if (cleanedValue !== '') {
                        selectEl.append($('<option>', {
                            value: cleanedValue,
                            text: cleanedValue
                        }));
                    }
                });
            }

            function getStatusDropdownHtml(id, status) {
                var statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
                return '<div class="btn-group" data-id="' + id + '">' +
                    '<button type="button" class="btn btn-sm dropdown-toggle lead-status-btn lead-status-' +
                    status + '" data-bs-toggle="dropdown" aria-expanded="false" data-status="' + status + '">' +
                    statusLabel +
                    '</button>' +
                    '<ul class="dropdown-menu">' +
                    '<li><a class="dropdown-item change-status" href="javascript:void(0);" data-status="new">New</a></li>' +
                    '<li><a class="dropdown-item change-status" href="javascript:void(0);" data-status="contacted">Contacted</a></li>' +
                    '<li><a class="dropdown-item change-status" href="javascript:void(0);" data-status="qualified">Qualified</a></li>' +
                    '<li><a class="dropdown-item change-status" href="javascript:void(0);" data-status="closed">Closed</a></li>' +
                    '</ul>' +
                    '</div>';
            }

            var sourceFilter = $('#sourceFilter');
            var statusFilter = $('#statusFilter');

            populateFilter(sourceFilter, sourceColumnIndex);

            var seenStatuses = {};
            $('#leads-datatable .lead-status-btn').each(function() {
                var value = $(this).data('status');
                if (value && !seenStatuses[value]) {
                    seenStatuses[value] = true;
                    statusFilter.append($('<option>', {
                        value: value,
                        text: value.charAt(0).toUpperCase() + value.slice(1)
                    }));
                }
            });

            function addOptionIfMissing(selectEl, value, text) {
                if (!value) {
                    return;
                }

                if (selectEl.find('option[value="' + value.replace(/"/g, '\\"') + '"]').length === 0) {
                    selectEl.append($('<option>', {
                        value: value,
                        text: text || value
                    }));
                }
            }

            sourceFilter.on('change', function() {
                var value = $(this).val();
                table.column(sourceColumnIndex).search(value ? '^' + $.fn.dataTable.util.escapeRegex(
                        value) +
                    '$' : '', true, false).draw();
            });

            statusFilter.on('change', function() {
                table.draw();
            });

            $('#leads-datatable').on('click', '.change-status', function(event) {
                event.preventDefault();
                var status = $(this).data('status');
                var parentGroup = $(this).closest('.btn-group');
                var leadId = parentGroup.data('id');
                var button = parentGroup.find('.lead-status-btn');

                if (!leadId || !status) {
                    return;
                }

                $.ajax({
                    url: updateLeadStatusApiUrl,
                    method: 'POST',
                    contentType: 'application/json',
                    dataType: 'json',
                    data: JSON.stringify({
                        id: leadId,
                        status: status
                    })
                }).done(function(res) {
                    if (res && res.success) {
                        var label = status.charAt(0).toUpperCase() + status.slice(1);
                        button.text(label)
                            .removeClass(
                                'lead-status-new lead-status-contacted lead-status-qualified lead-status-closed'
                                )
                            .addClass('lead-status-btn lead-status-' + status)
                            .attr('data-status', status);

                        if (typeof window.showToast === 'function') {
                            window.showToast('success', 'Status updated');
                        }

                        table.draw(false);
                    } else {
                        if (typeof window.showToast === 'function') {
                            window.showToast('danger', (res && res.message) ? res.message :
                                'Failed to update status');
                        }
                    }
                }).fail(function(xhr) {
                    var message = 'Failed to update status';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    if (typeof window.showToast === 'function') {
                        window.showToast('danger', message);
                    }
                });
            });

            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                if (settings.nTable.id !== 'leads-datatable') {
                    return true;
                }

                var selectedStatus = statusFilter.val();
                if (!selectedStatus) {
                    return true;
                }

                var rowNode = table.row(dataIndex).node();
                var rowStatus = $(rowNode).find('.lead-status-btn').data('status');
                return rowStatus === selectedStatus;
            });

            $('#leads-datatable_wrapper .dataTables_length select').addClass('form-select form-select-sm');

            $('.export-btn').on('click', function() {
                var type = $(this).data('type');
                if (type === 'csv') {
                    table.button('.buttons-csv').trigger();
                }
                if (type === 'pdf') {
                    table.button('.buttons-pdf').trigger();
                }
            });

            $('#tableSearch').on('keyup', function() {
                table.search(this.value).draw();
            });

            table.on('order.dt search.dt draw.dt', function() {
                var info = table.page.info();
                table.column(0, {
                    search: 'applied',
                    order: 'applied',
                    page: 'current'
                }).nodes().each(function(cell, index) {
                    cell.innerHTML = info.start + index + 1;
                });
            }).draw();

            var addLeadForm = $('#addLeadForm');
            var submitButton = $('#addLeadSubmitBtn');
            var submitSpinner = $('#addLeadSubmitSpinner');
            var submitText = $('#addLeadSubmitText');

            addLeadForm.on('submit', function(event) {
                event.preventDefault();
                event.stopPropagation();

                var formEl = this;
                formEl.classList.add('was-validated');

                if (!formEl.checkValidity()) {
                    if (typeof window.showToast === 'function') {
                        window.showToast('warning', 'Please fill all required fields');
                    }
                    return;
                }

                var isEdit = $('#leadId').val() !== '';
                var apiUrl = isEdit ? updateLeadApiUrl : addLeadApiUrl;
                var successMessage = isEdit ? 'Lead updated successfully' : 'Lead added successfully';
                var failMessage = isEdit ? 'Failed to update lead' : 'Failed to add lead';

                var payload = {
                    fullName: $.trim($('#modal-fullName').val()),
                    email: $.trim($('#modal-email').val()),
                    phone: $.trim($('#modal-phone').val()),
                    source: $.trim($('#modal-source').val())
                };

                if (isEdit) {
                    payload.id = $('#leadId').val();
                }

                submitButton.prop('disabled', true);
                submitSpinner.removeClass('d-none');
                submitText.text(isEdit ? 'Updating...' : 'Saving...');

                $.ajax({
                    url: apiUrl,
                    method: 'POST',
                    contentType: 'application/json',
                    dataType: 'json',
                    data: JSON.stringify(payload)
                }).done(function(response) {
                    if (response && response.success) {
                        var lead = response.data || {};
                        var leadId = lead.id || payload.id || 0;
                        var statusValue = lead.status || 'new';
                        var createdDate = lead.createdDate || '';
                        var statusLabel = statusValue.charAt(0).toUpperCase() + statusValue
                            .slice(1);
                        var statusHtml = getStatusDropdownHtml(leadId, statusValue);

                        if (isEdit) {
                            // Update existing row
                            var row = table.row(function(idx, data, node) {
                                return $(node).find('.edit-lead-btn').data('id') ==
                                    leadId;
                            });
                            if (row.any()) {
                                var actionsHtml =
                                    '<a href="javascript:void(0;" class="btn btn-icon btn-sm btn-info-light btn-wave waves-effect waves-light edit-lead-btn" data-id="' +
                                    leadId + '" data-fullname="' + (lead.fullName || payload
                                        .fullName) + '" data-email="' + (lead.email || payload
                                        .email) + '" data-phone="' + (lead.phone || payload
                                        .phone) + '" data-source="' + (lead.source || payload
                                        .source) +
                                    '"><i class="ri-edit-line"></i></a> <a href="javascript:void(0;" class="btn btn-icon btn-sm btn-danger-light btn-wave waves-effect waves-light delete-lead-btn" data-id="' +
                                    leadId + '"><i class="ri-delete-bin-line"></i></a>';
                                var nameWithEmail = (lead.fullName || payload.fullName) +
                                    '<small class="d-block text-muted">' + (lead.email ||
                                        payload.email) + '</small>';
                                row.data([
                                    '',
                                    nameWithEmail,
                                    lead.phone || payload.phone,
                                    lead.source || payload.source,
                                    // Keep existing status HTML
                                    row.data()[4],
                                    createdDate || row.data()[5],
                                    actionsHtml
                                ]).draw(false);
                            }
                        } else {
                            // Add new row
                            var actionsHtml =
                                '<a href="javascript:void(0;" class="btn btn-icon btn-sm btn-info-light btn-wave waves-effect waves-light edit-lead-btn" data-id="' +
                                leadId + '" data-fullname="' + (lead.fullName || payload
                                    .fullName) + '" data-email="' + (lead.email || payload
                                    .email) + '" data-phone="' + (lead.phone || payload.phone) +
                                '" data-source="' + (lead.source || payload.source) +
                                '"><i class="ri-edit-line"></i></a> <a href="javascript:void(0;" class="btn btn-icon btn-sm btn-danger-light btn-wave waves-effect waves-light delete-lead-btn" data-id="' +
                                leadId + '"><i class="ri-delete-bin-line"></i></a>';

                            var nameWithEmail = (lead.fullName || payload.fullName) +
                                '<small class="d-block text-muted">' + (lead.email || payload
                                    .email) + '</small>';

                            var newRow = [
                                '',
                                nameWithEmail,
                                lead.phone || payload.phone,
                                lead.source || payload.source,
                                statusHtml,
                                createdDate,
                                actionsHtml
                            ];

                            var existingRows = table.rows().data().toArray();
                            table.clear();
                            table.rows.add([newRow].concat(existingRows)).draw(false);
                        }

                        addOptionIfMissing(sourceFilter, lead.source || payload.source, lead
                            .source ||
                            payload.source);
                        addOptionIfMissing(statusFilter, statusValue, statusLabel);

                        formEl.reset();
                        formEl.classList.remove('was-validated');
                        $('#addLeadModal').modal('hide');
                        if (typeof window.showToast === 'function') {
                            window.showToast('success', response.message || successMessage);
                        }
                    } else {
                        if (typeof window.showToast === 'function') {
                            window.showToast('danger', (response && response.message) ? response
                                .message : failMessage);
                        }
                    }
                }).fail(function(xhr) {
                    var message = failMessage;
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    if (typeof window.showToast === 'function') {
                        window.showToast('danger', message);
                    }
                }).always(function() {
                    submitButton.prop('disabled', false);
                    submitSpinner.addClass('d-none');
                    submitText.text(isEdit ? 'Update Lead' : 'Save Lead');
                });
            });

            // Edit Lead
            $('#leads-datatable').on('click', '.edit-lead-btn', function() {
                console.log('Edit button clicked');
                var btn = $(this);
                var id = btn.data('id');
                console.log('Edit ID:', id);
                var fullName = btn.data('fullname');
                var email = btn.data('email');
                var phone = btn.data('phone');
                var source = btn.data('source');

                $('#leadId').val(id);
                $('#modal-fullName').val(fullName);
                $('#modal-email').val(email);
                $('#modal-phone').val(phone);
                $('#modal-source').val(source);

                $('#addLeadModalLabel').text('Edit Lead');
                $('#addLeadSubmitText').text('Update Lead');

                $('#addLeadModal').modal('show');
            });

            // Delete Lead
            $('#leads-datatable').on('click', '.delete-lead-btn', function() {
                console.log('Delete button clicked');
                var btn = $(this);
                var id = btn.data('id');
                console.log('Delete ID:', id);

                var modal = $('#deleteConfirmModal');
                var effect = modal.data('bs-effect');
                if (effect) {
                    modal.addClass(effect);
                }
                modal.data('deleteId', id).modal('show');
            });

            // Remove effect class when modal is hidden
            $('#deleteConfirmModal').on('hidden.bs.modal', function() {
                var modal = $(this);
                var effect = modal.data('bs-effect');
                if (effect) {
                    modal.removeClass(effect);
                }
            });

            // Add effect class when addLeadModal is shown
            $('#addLeadModal').on('show.bs.modal', function() {
                var modal = $(this);
                var effect = modal.data('bs-effect');
                if (effect) {
                    modal.addClass(effect);
                }
                // Reset for add mode if leadId is empty
                if ($('#leadId').val() === '') {
                    $('#addLeadForm')[0].reset();
                    $('#addLeadForm')[0].classList.remove('was-validated');
                    $('#addLeadModalLabel').text('Add Lead');
                    $('#addLeadSubmitText').text('Save Lead');
                }
            });

            // Remove effect class when addLeadModal is hidden
            $('#addLeadModal').on('hidden.bs.modal', function() {
                var modal = $(this);
                var effect = modal.data('bs-effect');
                if (effect) {
                    modal.removeClass(effect);
                }
                // Clear leadId to ensure add mode next time
                $('#leadId').val('');
            });

            // Confirm Delete
            $('#confirmDeleteBtn').on('click', function() {
                var id = $('#deleteConfirmModal').data('deleteId');
                console.log('Confirmed delete for ID:', id);
                $('#deleteConfirmModal').modal('hide');
                $.ajax({
                    url: deleteLeadApiUrl,
                    method: 'POST',
                    contentType: 'application/json',
                    dataType: 'json',
                    data: JSON.stringify({
                        id: id
                    })
                }).done(function(response) {
                    console.log('Delete response:', response);
                    if (response && response.success) {
                        var tr = $('.delete-lead-btn[data-id="' + id + '"]').closest('tr');
                        table.row(tr).remove().draw(false);
                        if (typeof window.showToast === 'function') {
                            window.showToast('success', response.message ||
                                'Lead deleted successfully');
                        }
                    } else {
                        if (typeof window.showToast === 'function') {
                            window.showToast('danger', (response && response.message) ? response
                                .message : 'Failed to delete lead');
                        }
                    }
                }).fail(function(xhr) {
                    console.log('Delete failed:', xhr);
                    var message = 'Failed to delete lead';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    if (typeof window.showToast === 'function') {
                        window.showToast('danger', message);
                    }
                });
            });
        });
        </script>
        <?php if ($result) {
    mysqli_free_result($result);
} ?>
        <?php include __DIR__ . '/../includes/footer.php'; ?>