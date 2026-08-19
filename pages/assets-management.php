<?php
include __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<style>
.status-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid transparent;
}

.status-chip-success {
    color: rgb(var(--success-rgb));
    border-color: rgba(var(--success-rgb), 0.3);
}

.status-chip-info {
    color: rgb(var(--info-rgb));
    border-color: rgba(var(--info-rgb), 0.3);
}
</style>
<div class="main-content app-content">
    <div class="container-fluid">

        <!-- HEADER -->
        <div class="my-4 d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Asset Management</h1>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAssetModal">
                    <i class="ri-add-line me-1"></i> Add Asset
                </button>

                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#assignAssetModal">
                    Assign Asset
                </button>
            </div>
        </div>

        <!-- CONTROLS ROW -->
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
                                <select id="assetStatusFilter" class="form-select form-select-lg">
                                    <option value="">Status</option>
                                    <option value="available">Available</option>
                                    <option value="assigned">Assigned</option>
                                </select>
                            </div>
                            <div class="flex-fill"></div>
                            <div class="d-flex">
                                <input id="assetTableSearch" class="form-control form-control-sm"
                                    placeholder="Search assets..." autocomplete="off">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLE ROW -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="assetTable" data-ui-table="mamix" class="table table-hover text-nowrap">
                                <thead>
                                    <tr>
                                        <th>SNo</th>
                                        <th>Asset Code</th>
                                        <th>Asset Name</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Assigned To</th>
                                        <th>Condition</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- DATA WILL COME FROM AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- ADD ASSET MODAL -->
        <div class="modal fade" id="addAssetModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">

                    <form id="addAssetForm">

                        <div class="modal-header">
                            <h5 class="modal-title">Add Asset</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body">

                            <div class="row g-3">

                                <div class="col-md-6">
                                    <label class="form-label">Asset Name</label>
                                    <input type="text" name="assetName" class="form-control" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Category</label>
                                    <?php
                                $cat = mysqli_query($con, "
                                    SELECT id, categoryName 
                                    FROM assetCategory 
                                    ORDER BY categoryName ASC
                                ");
                            ?>

                                    <select name="categoryId" class="form-select" required>
                                        <option value="">Select Category</option>

                                        <?php if($cat && mysqli_num_rows($cat) > 0): ?>
                                        <?php while($c = mysqli_fetch_assoc($cat)): ?>
                                        <option value="<?= (int)$c['id'] ?>">
                                            <?= htmlspecialchars($c['categoryName']) ?>
                                        </option>
                                        <?php endwhile; ?>
                                        <?php else: ?>
                                        <option value="" disabled>No Categories Found</option>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Brand</label>
                                    <input type="text" name="brand" class="form-control">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Serial Number</label>
                                    <input type="text" name="serialNumber" class="form-control">
                                </div>

                            </div>

                        </div>

                        <div class="modal-footer">

                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                                Cancel
                            </button>

                            <button type="submit" class="btn btn-primary" id="saveAssetBtn">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="saveAssetSpinner"></span>
                                <span id="saveAssetText">Save Asset</span>
                            </button>

                        </div>

                    </form>

                </div>
            </div>
        </div>

        <!-- ASSIGN ASSET MODAL -->
        <div class="modal fade" id="assignAssetModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">

                    <form id="assignAssetForm">

                        <div class="modal-header">
                            <h5 class="modal-title">Assign Asset</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body">

                            <div class="mb-3">
                                <label class="form-label">Select Asset</label>
                                <select name="assetId" id="assetDropdown" class="form-select" required>
                                    <option value="">Loading...</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Select Employee</label>
                                <select name="employeeId" id="employeeDropdown" class="form-select" required>
                                    <option value="">Loading...</option>
                                </select>
                            </div>

                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>

                            <button type="submit" class="btn btn-success" id="assignBtn">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="assignSpinner"></span>
                                <span id="assignText">Assign</span>
                            </button>
                        </div>

                    </form>

                </div>
            </div>
        </div>

        <div class="modal fade" id="returnAssetModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">

                    <form id="returnAssetForm">

                        <input type="hidden" name="assetId" id="returnAssetId">

                        <div class="modal-header">
                            <h5 class="modal-title">Return Asset</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body">

                            <div class="mb-3">
                                <label class="form-label">Condition</label>
                                <select name="conditionStatus" class="form-select">
                                    <option value="good">Good</option>
                                    <option value="damaged">Damaged</option>
                                    <option value="repair">Needs Repair</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Remarks</label>
                                <textarea name="remarks" class="form-control"></textarea>
                            </div>

                        </div>

                        <div class="modal-footer">
                            <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>

                            <button type="submit" class="btn btn-danger" id="returnBtn">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="returnSpinner"></span>
                                <span id="returnText">Return Asset</span>
                            </button>
                        </div>

                    </form>

                </div>
            </div>
        </div>

        <!-- ASSET HISTORY MODAL -->
        <div class="modal fade" id="assetHistoryModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">Asset History</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div id="assetHistoryContent">
                            <p class="text-muted">Loading...</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.bootstrap5.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.6/pdfmake.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.print.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
        $(function() {

            // =========================================================
            // ✅ GLOBALS
            // =========================================================
            let table;

            // Toast helper (same as candidate module)
            function showToast(type, message) {
                if (typeof window.showToast === 'function') {
                    window.showToast(type, message);
                } else {
                    console.log(type.toUpperCase() + ': ' + message);
                }
            }

            // =========================================================
            // ✅ HELPER → LIVE ROW UPDATE
            // =========================================================
            function updateRow(assetId, newData) {

                let updated = false;

                table.rows().every(function() {
                    let d = this.data();

                    if (String(d.id) === String(assetId)) {
                        this.data($.extend({}, d, newData)).invalidate();
                        updated = true;
                    }
                });

                if (updated) {
                    table.draw(false);
                }
            }

            // =========================================================
            // ✅ DATATABLE INIT
            // =========================================================
            if ($.fn.DataTable.isDataTable('#assetTable')) {
                $('#assetTable').DataTable().destroy();
            }

            table = $('#assetTable').DataTable({
                processing: true,
                ajax: {
                    url: API_BASE + '/getAsset.php',
                    dataSrc: function(json) {
                        if (!json) return [];
                        if (Array.isArray(json)) return json;
                        if (json.success === false) {
                            showToast('danger', json.message || 'Failed to load assets');
                            return [];
                        }
                        return json.data || [];
                    },
                    error: function() {
                        showToast('danger', 'Failed to load assets');
                    }
                },

                columns: [{
                        data: null
                    }, // SNo
                    {
                        data: 'assetCode'
                    },
                    {
                        data: 'assetName'
                    },
                    {
                        data: 'categoryName',
                        defaultContent: '-'
                    },

                    {
                        data: 'status',
                        render: function(data) {
                            let badge = data === 'available' ? 'status-chip-success' :
                                'status-chip-info';
                            return `<span class="status-chip ${badge}">${data}</span>`;
                        }
                    },

                    {
                        data: 'assignedTo',
                        defaultContent: '-'
                    },
                    {
                        data: 'conditionStatus'
                    },

                    {
                        data: null,
                        orderable: false,
                        render: function(data) {
                    
                            let assignBtn = data.status === 'available' ?
                                `<a href="javascript:void(0);"
                                    class="btn btn-icon btn-sm btn-success-light btn-wave waves-effect waves-light assign-btn"
                                    data-id="${data.id}"
                                    title="Assign">
                                    <i class="ri-user-add-line"></i>
                                </a>` :
                                '';
                    
                            let returnBtn = data.status === 'assigned' ?
                                `<a href="javascript:void(0);"
                                    class="btn btn-icon btn-sm btn-info-light btn-wave waves-effect waves-light return-btn"
                                    data-id="${data.id}"
                                    title="Return">
                                    <i class="ri-arrow-go-back-line"></i>
                                </a>` :
                                '';
                    
                            let historyBtn = `
                                <a href="javascript:void(0);"
                                    class="btn btn-icon btn-sm btn-secondary-light btn-wave waves-effect waves-light history-btn"
                                    data-id="${data.id}"
                                    title="History">
                                    <i class="ri-history-line"></i>
                                </a>
                            `;
                    
                            let deleteBtn = `
                                <a href="javascript:void(0);"
                                    class="btn btn-icon btn-sm btn-danger-light btn-wave waves-effect waves-light delete-btn"
                                    data-id="${data.id}"
                                    title="Delete">
                                    <i class="ri-delete-bin-line"></i>
                                </a>
                            `;
                    
                            return `
                                ${assignBtn}
                                ${returnBtn}
                                ${historyBtn}
                                ${deleteBtn}
                            `;
                        }
                    }
                ],

                order: [],
                pageLength: 10,
                dom: "t<'row mt-3 align-items-center'<'col-md-5'i><'col-md-7'p>>",
                buttons: [{
                        extend: 'csvHtml5',
                        className: 'd-none buttons-csv',
                        exportOptions: {
                            columns: ':visible:not(:last-child)'
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        className: 'd-none buttons-pdf',
                        exportOptions: {
                            columns: ':visible:not(:last-child)'
                        }
                    }
                ],

                drawCallback: function() {
                    let api = this.api();
                    api.column(0).nodes().each(function(cell, i) {
                        cell.innerHTML = i + 1;
                    });
                }
            });

            $('#assetStatusFilter').on('change', function() {
                table.column(4).search($.trim($(this).val())).draw();
            });

            $('#assetTableSearch').on('keyup input', function() {
                table.search($.trim($(this).val())).draw();
            });

            $('.export-btn').on('click', function() {
                var type = $(this).data('type');
                if (type === 'csv') table.buttons('.buttons-csv').trigger();
                if (type === 'pdf') table.buttons('.buttons-pdf').trigger();
            });

            // =========================================================
            // ✅ ADD ASSET
            // =========================================================
            $('#addAssetForm').on('submit', function(e) {

                e.preventDefault();

                let formData = $(this).serialize();

                let btn = $('#saveAssetBtn');
                let spinner = $('#saveAssetSpinner');
                let text = $('#saveAssetText');

                btn.prop('disabled', true);
                spinner.removeClass('d-none');
                text.text('Saving...');

                $.ajax({
                    url: API_BASE + '/addAsset.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',

                    success: function(res) {

                        if (!res.success) {
                            showToast('danger', res.message);
                            return;
                        }

                        $('#addAssetModal').modal('hide');
                        $('#addAssetForm')[0].reset();

                        // ✅ Add new row instantly
                        if (res.data && res.data.id) {
                            table.row.add(res.data).draw(false);
                        } else {
                            table.ajax.reload(null, false);
                        }

                        showToast('success', 'Asset added successfully');
                    },

                    error: function() {
                        showToast('danger', 'Server error');
                    },

                    complete: function() {
                        btn.prop('disabled', false);
                        spinner.addClass('d-none');
                        text.text('Save Asset');
                    }
                });

            });

            // =========================================================
            // ✅ ASSIGN ASSET
            // =========================================================
            $('#assignAssetForm').on('submit', function(e) {

                e.preventDefault();

                let formData = $(this).serialize();

                let btn = $('#assignBtn');
                let spinner = $('#assignSpinner');
                let text = $('#assignText');

                btn.prop('disabled', true);
                spinner.removeClass('d-none');
                text.text('Assigning...');

                $.ajax({
                    url: API_BASE + '/assignAsset.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',

                    success: function(res) {

                        if (!res.success) {
                            showToast('danger', res.message);
                            return;
                        }

                        $('#assignAssetModal').modal('hide');

                        // ✅ SAFE CHECK
                        if (res.data && res.data.id) {
                            updateRow(res.data.id, res.data);
                        } else {
                            // fallback (rare case)
                            table.ajax.reload(null, false);
                        }

                        showToast('success', 'Asset assigned successfully');
                    },

                    error: function() {
                        showToast('danger', 'Server error');
                    },

                    complete: function() {
                        btn.prop('disabled', false);
                        spinner.addClass('d-none');
                        text.text('Assign');
                    }
                });

            });

            // =========================================================
            // ✅ INLINE ASSIGN BUTTON CLICK
            // =========================================================
            $(document).on('click', '.assign-btn', function() {

                let assetId = $(this).data('id');

                $('#assignAssetModal').modal('show');

                setTimeout(() => {
                    $('#assetDropdown').val(assetId);
                }, 300);
            });

            // =========================================================
            // ✅ LOAD DROPDOWNS
            // =========================================================
            $('#assignAssetModal').on('show.bs.modal', function() {

                $.ajax({
                    url: API_BASE + '/getAvailableAssets.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(res) {
                        if (!res.success) return;

                        let html = '<option value="">Select Asset</option>';
                        res.data.forEach(a => {
                            html +=
                                `<option value="${a.id}">${a.assetCode} - ${a.assetName}</option>`;
                        });

                        $('#assetDropdown').html(html);
                    }
                });

                $.ajax({
                    url: API_BASE + '/getEmployees.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(res) {
                        if (!res.success) return;

                        let html = '<option value="">Select Employee</option>';
                        res.data.forEach(e => {
                            html +=
                                `<option value="${e.id}">${e.fullName}</option>`;
                        });

                        $('#employeeDropdown').html(html);
                    }
                });

            });

            // =========================================================
            // ✅ RETURN FLOW
            // =========================================================
            $(document).on('click', '.return-btn', function() {
                let assetId = $(this).data('id');
                $('#returnAssetId').val(assetId);
                $('#returnAssetModal').modal('show');
            });

            $('#returnAssetForm').on('submit', function(e) {

                e.preventDefault();

                let formData = $(this).serialize();

                let btn = $('#returnBtn');
                let spinner = $('#returnSpinner');
                let text = $('#returnText');

                btn.prop('disabled', true);
                spinner.removeClass('d-none');
                text.text('Processing...');

                $.ajax({
                    url: API_BASE + '/returnAsset.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',

                    success: function(res) {

                        if (!res.success) {
                            showToast('danger', res.message);
                            return;
                        }

                        $('#returnAssetModal').modal('hide');

                        if (res.data && res.data.id) {
                            updateRow(res.data.id, res.data);
                        } else {
                            table.ajax.reload(null, false);
                        }

                        showToast('success', 'Asset returned successfully');
                    },

                    error: function() {
                        showToast('danger', 'Server error');
                    },

                    complete: function() {
                        btn.prop('disabled', false);
                        spinner.addClass('d-none');
                        text.text('Return Asset');
                    }
                });

            });

            // =========================================================
            // ✅ ASSET HISTORY
            // =========================================================
            $(document).on('click', '.history-btn', function() {

                let id = $(this).data('id');
                let $deleteBtn = $(this);

                $('#assetHistoryModal').modal('show');
                $('#assetHistoryContent').html('Loading...');

                $.ajax({
                    url: API_BASE + '/getAssetHistory.php',
                    type: 'GET',
                    data: {
                        assetId: id
                    },
                    dataType: 'json',

                    success: function(res) {

                        console.log('History API:', res);

                        if (!res.success) {
                            $('#assetHistoryContent').html(
                                '<p class="text-danger">Failed to load history</p>');
                            return;
                        }

                        if (!res.data || res.data.length === 0) {
                            $('#assetHistoryContent').html(
                                '<p class="text-muted">No history found</p>');
                            return;
                        }

                        let html = '';

                        res.data.forEach((h, i) => {

                            let badge = h.status === 'Assigned' ?
                                'bg-warning' :
                                'bg-success';

                            html += `
            <div class="border rounded p-3 mb-2">

                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="badge ${badge}">${h.status}</span>
                    <small class="text-muted">${h.createdAt}</small>
                </div>

                <div><strong>Employee:</strong> ${h.employeeName || '-'}</div>



                ${
                    h.actualReturnDate
                        ? `<div><strong>Returned Date:</strong> ${h.actualReturnDate}</div>`
                        : ''
                }

                ${
                    h.remarks
                        ? `<div><strong>Remarks:</strong> ${h.remarks}</div>`
                        : ''
                }

            </div>
        `;
                        });

                        $('#assetHistoryContent').html(html);
                    }
                });

            });

            // =========================================================
            // ✅ DELETE ASSET (SweetAlert only)
            // =========================================================
            $(document).on('click', '.delete-btn', function() {

                let id = $(this).data('id');
                let $deleteBtn = $(this);

                Swal.fire({
                    title: 'Are you sure?',
                    text: "This asset will be deleted!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'Yes, delete it'
                }).then((result) => {

                    if (result.isConfirmed) {

                        $.ajax({
                            url: API_BASE + '/deleteAsset.php',
                            type: 'POST',
                            data: {
                                id: id
                            },
                            dataType: 'json',

                            success: function(res) {

                                if (!res.success) {
                                    showToast('danger', res.message);
                                    return;
                                }

                                // ✅ Remove only that row
                                let rowNode = $deleteBtn.closest('tr');
                                if (rowNode.length) {
                                    table.row(rowNode).remove().draw(false);
                                    table.column(0, {
                                        search: 'applied',
                                        order: 'applied'
                                    }).nodes().each(function(cell, i) {
                                        cell.innerHTML = i + 1;
                                    });
                                } else {
                                    table.ajax.reload(null, false);
                                }

                                showToast('success', 'Asset deleted successfully');
                            },

                            error: function() {
                                showToast('danger', 'Server error');
                            }
                        });
                    }
                });

            });

        });
        </script>
        <?php include __DIR__ . '/../includes/footer.php'; ?>