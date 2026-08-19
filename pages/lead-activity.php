<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// =====================================================
// GET FILTER PARAMETERS
// =====================================================
$dateFrom = isset($_GET['dateFrom']) ? $_GET['dateFrom'] : '';
$dateTo = isset($_GET['dateTo']) ? $_GET['dateTo'] : '';
$employeeFilter = isset($_GET['employeeFilter']) ? (int)$_GET['employeeFilter'] : 0;
$statusFilter = isset($_GET['statusFilter']) ? $_GET['statusFilter'] : '';

// =====================================================
// BUILD WHERE CLAUSE
// =====================================================
$whereConditions = [];
$params = [];
$types = '';

if (!empty($dateFrom)) {
    $whereConditions[] = "DATE(al.createdAt) >= ?";
    $params[] = $dateFrom;
    $types .= 's';
}

if (!empty($dateTo)) {
    $whereConditions[] = "DATE(al.createdAt) <= ?";
    $params[] = $dateTo;
    $types .= 's';
}

if ($employeeFilter > 0) {
    $whereConditions[] = "al.createdBy = ?";
    $params[] = $employeeFilter;
    $types .= 'i';
}

if (!empty($statusFilter)) {
    $whereConditions[] = "al.actionType = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

$whereClause = !empty($whereConditions) ? 'AND ' . implode(' AND ', $whereConditions) : '';

// =====================================================
// MAIN QUERY
// =====================================================
$sql = "
SELECT
    al.*,
    l.fullName AS leadName,
    COALESCE(
        eu.fullName,
        u.fullName,
        'System'
    ) AS employeeName
FROM leadsActivityLogs al
LEFT JOIN leads l ON l.id = al.recordId
LEFT JOIN employeeusers eu ON eu.id = al.createdBy
LEFT JOIN users u ON u.id = al.createdBy
WHERE al.moduleName = 'Lead'
{$whereClause}
ORDER BY al.id DESC
";

// Prepare and execute with filters
if (!empty($params)) {
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($con, $sql);
    }
} else {
    $result = mysqli_query($con, $sql);
}

// =====================================================
// SUMMARY COUNTERS
// =====================================================
// Total Activities
$totalSql = "SELECT COUNT(*) as total FROM leadsActivityLogs WHERE moduleName = 'Lead'";
if (!empty($whereConditions)) {
    $totalSql = str_replace('WHERE moduleName = \'Lead\'', 'WHERE moduleName = \'Lead\' AND ' . implode(' AND ', $whereConditions), $totalSql);
}
$totalResult = mysqli_query($con, $totalSql);
$totalActivities = ($totalResult && mysqli_num_rows($totalResult) > 0) ? mysqli_fetch_assoc($totalResult)['total'] : 0;

// Action Types Breakdown
$actionSql = "
SELECT actionType, COUNT(*) as count 
FROM leadsActivityLogs 
WHERE moduleName = 'Lead' 
GROUP BY actionType
";
$actionResult = mysqli_query($con, $actionSql);
$actionStats = [];
if ($actionResult && mysqli_num_rows($actionResult) > 0) {
    while ($row = mysqli_fetch_assoc($actionResult)) {
        $actionStats[$row['actionType']] = $row['count'];
    }
}

// Get employees for filter
$employeeSql = "SELECT id, fullName FROM employeeusers ORDER BY fullName ASC";
$employeeResult = mysqli_query($con, $employeeSql);
$employees = [];
while ($row = mysqli_fetch_assoc($employeeResult)) {
    $employees[] = $row;
}

// Get unique action types for filter
$actionTypesSql = "SELECT DISTINCT actionType FROM leadsActivityLogs WHERE moduleName = 'Lead' ORDER BY actionType";
$actionTypesResult = mysqli_query($con, $actionTypesSql);
$actionTypes = [];
while ($row = mysqli_fetch_assoc($actionTypesResult)) {
    $actionTypes[] = $row['actionType'];
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';

?>

<style>
    .summary-card {
        background: #fff;
        border-radius: 12px;
        padding: 16px 20px;
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    .summary-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.06);
    }
    .summary-card .number {
        font-size: 24px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.2;
    }
    .summary-card .label {
        font-size: 12px;
        color: #64748b;
        font-weight: 500;
        margin-top: 2px;
    }
    .summary-card .icon {
        font-size: 20px;
        opacity: 0.3;
    }
    .summary-card.primary .number { color: #0b8ba8; }
    .summary-card.success .number { color: #16a34a; }
    .summary-card.warning .number { color: #d97706; }
    .summary-card.danger .number { color: #dc2626; }
    .summary-card.info .number { color: #6366f1; }
    .summary-card.purple .number { color: #8b5cf6; }

    .filter-section {
        background: #fff;
        border-radius: 12px;
        padding: 16px 20px;
        border: 1px solid #e2e8f0;
        margin-bottom: 24px;
    }
    .filter-section .filter-label {
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        margin-bottom: 4px;
    }
    .filter-section .form-control,
    .filter-section .form-select {
        font-size: 13px;
        border-radius: 8px;
        border-color: #e2e8f0;
        height: 38px;
    }
    .filter-section .form-control:focus,
    .filter-section .form-select:focus {
        border-color: #0b8ba8;
        box-shadow: 0 0 0 3px rgba(11, 139, 168, 0.1);
    }
    .btn-filter {
        padding: 8px 24px;
        background: #0b8ba8;
        color: #fff;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.3s ease;
        cursor: pointer;
        height: 38px;
    }
    .btn-filter:hover {
        background: #0d9ec0;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(11, 139, 168, 0.25);
    }
    .btn-reset-filter {
        padding: 8px 20px;
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        height: 38px;
    }
    .btn-reset-filter:hover {
        background: #e2e8f0;
        color: #0f172a;
    }

    .activity-badge {
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
        display: inline-block;
    }
    .activity-badge.created { background: #dbeafe; color: #1d4ed8; }
    .activity-badge.updated { background: #fef3c7; color: #b45309; }
    .activity-badge.deleted { background: #fee2e2; color: #dc2626; }
    .activity-badge.status_change { background: #d1fae5; color: #065f46; }
    .activity-badge.remark_added { background: #ede9fe; color: #6d28d9; }
    .activity-badge.converted { background: #d1fae5; color: #065f46; }
    .activity-badge.imported { background: #e0f2fe; color: #0369a1; }

    .change-item {
        padding: 2px 6px;
        background: #f8fafc;
        border-radius: 4px;
        margin-bottom: 2px;
        font-size: 12px;
        line-height: 1.6;
        word-break: break-all;
        max-width: 280px;
    }
    .change-item .old-value {
        color: #dc2626;
        text-decoration: line-through;
        margin-right: 4px;
    }
    .change-item .new-value {
        color: #16a34a;
        font-weight: 600;
    }
    .change-item .arrow {
        color: #94a3b8;
        margin: 0 4px;
    }
    .change-item .field-name {
        font-weight: 600;
        color: #475569;
        margin-right: 4px;
    }

    .lead-name-cell {
        max-width: 150px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .lead-name-cell .lead-id {
        font-size: 11px;
        color: #94a3b8;
    }

    .desc-cell {
        max-width: 200px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    @media (max-width: 768px) {
        .summary-cards {
            grid-template-columns: repeat(2, 1fr);
        }
        .filter-section .row > div {
            margin-bottom: 10px;
        }
        .change-item {
            max-width: 180px;
        }
    }
    @media (max-width: 480px) {
        .summary-cards {
            grid-template-columns: 1fr;
        }
    }

    /* DataTable override */
    #activityTable td {
        vertical-align: middle;
        padding: 8px 10px;
    }
    #activityTable th {
        padding: 10px;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #64748b;
        font-weight: 700;
    }
    #activityTable tbody tr:hover {
        background: #f8fafc;
    }
    
    /* Responsive table wrapper */
    .table-responsive-custom {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .table-responsive-custom table {
        min-width: 900px;
        width: 100%;
    }
</style>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Lead Activity Logs</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="leads">Leads</a></li>
                    <li class="breadcrumb-item active">Activity Logs</li>
                </ol>
            </div>
            <div>
                <a href="leads" class="btn btn-outline-primary btn-wave btn-sm">
                    <i class="ri-arrow-left-line me-1"></i> Back to Leads
                </a>
            </div>
        </div>

        <!-- =====================================================
        SUMMARY CARDS
        ===================================================== -->
        <div class="summary-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 20px;">
            <div class="summary-card primary">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="number"><?= number_format($totalActivities) ?></div>
                        <div class="label">Total Activities</div>
                    </div>
                    <div class="icon"><i class="ri-file-list-3-line"></i></div>
                </div>
            </div>

            <?php foreach ($actionStats as $action => $count): ?>
                <?php
                $iconMap = [
                    'created' => 'ri-add-circle-line',
                    'updated' => 'ri-edit-2-line',
                    'deleted' => 'ri-delete-bin-line',
                    'status_change' => 'ri-arrow-left-right-line',
                    'remark_added' => 'ri-chat-3-line',
                    'converted' => 'ri-exchange-line',
                    'imported' => 'ri-upload-cloud-2-line'
                ];
                $icon = $iconMap[$action] ?? 'ri-information-line';
                $colorClass = match($action) {
                    'created' => 'success',
                    'updated' => 'warning',
                    'deleted' => 'danger',
                    'status_change' => 'info',
                    'remark_added' => 'purple',
                    'converted' => 'success',
                    'imported' => 'info',
                    default => 'primary'
                };
                $label = ucfirst(str_replace('_', ' ', $action));
                ?>
                <div class="summary-card <?= $colorClass ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="number"><?= number_format($count) ?></div>
                            <div class="label"><?= $label ?></div>
                        </div>
                        <div class="icon"><i class="<?= $icon ?>"></i></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- =====================================================
        FILTER SECTION
        ===================================================== -->
        <div class="filter-section">
            <form method="GET" action="" class="row g-2 align-items-end">
                <div class="col-md-2 col-sm-6">
                    <div class="filter-label">Date From</div>
                    <input type="date" name="dateFrom" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-2 col-sm-6">
                    <div class="filter-label">Date To</div>
                    <input type="date" name="dateTo" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-2 col-sm-6">
                    <div class="filter-label">Employee</div>
                    <select name="employeeFilter" class="form-select form-select-sm">
                        <option value="0">All Employees</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= $employeeFilter == $emp['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['fullName'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <div class="filter-label">Action Type</div>
                    <select name="statusFilter" class="form-select form-select-sm">
                        <option value="">All Actions</option>
                        <?php foreach ($actionTypes as $type): ?>
                            <option value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" <?= $statusFilter == $type ? 'selected' : '' ?>>
                                <?= ucfirst(str_replace('_', ' ', $type)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-3">
                    <button type="submit" class="btn-filter w-100 btn-sm">
                         Filter
                    </button>
                </div>
                
                <div class="col-md-2 col-sm-3">
                   <a href="lead-activity" class="btn btn-filter w-100 btn-lg">Reset</a>
                </div>
                
                
            </form>
        </div>

        <!-- =====================================================
        ACTIVITY TABLE
        ===================================================== -->
        <div class="card custom-card">
            <div class="card-body p-0">
                <div class="table-responsive-custom">
                    <table id="activityTable" class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="min-width: 130px;">Date</th>
                                <th style="min-width: 120px;">Lead</th>
                                <th style="min-width: 100px;">Action</th>
                                <th style="min-width: 160px;">Description</th>
                                <th style="min-width: 200px;">Changes</th>
                                <th style="min-width: 100px;">User</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>

                                    <?php
                                    $oldData = !empty($row['oldData']) ? json_decode($row['oldData'], true) : [];
                                    $newData = !empty($row['newData']) ? json_decode($row['newData'], true) : [];

                                    $leadName = $row['leadName'] ?? ($oldData['fullName'] ?? '-');

                                    // Action badge class
                                    $badgeClass = match($row['actionType']) {
                                        'created' => 'created',
                                        'updated' => 'updated',
                                        'deleted' => 'deleted',
                                        'status_change' => 'status_change',
                                        'remark_added' => 'remark_added',
                                        'converted' => 'converted',
                                        'imported' => 'imported',
                                        default => 'created'
                                    };

                                    $actionLabel = ucfirst(str_replace('_', ' ', $row['actionType']));
                                    
                                    // Format changes for display
                                    $changesHtml = '';
                                    if (!empty($newData)) {
                                        foreach ($newData as $key => $value) {
                                            $oldValue = $oldData[$key] ?? '-';
                                            $newValue = is_array($value) ? json_encode($value) : (string)$value;
                                            $fieldLabel = ucfirst(str_replace('_', ' ', $key));
                                            
                                            $changesHtml .= '<div class="change-item">';
                                            $changesHtml .= '<span class="field-name">' . htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8') . ':</span>';
                                            $changesHtml .= '<span class="old-value">' . htmlspecialchars((string)$oldValue, ENT_QUOTES, 'UTF-8') . '</span>';
                                            $changesHtml .= '<span class="arrow">→</span>';
                                            $changesHtml .= '<span class="new-value">' . htmlspecialchars($newValue, ENT_QUOTES, 'UTF-8') . '</span>';
                                            $changesHtml .= '</div>';
                                        }
                                    } else {
                                        $changesHtml = '<span class="text-muted" style="font-size:12px;">No Changes</span>';
                                    }
                                    ?>

                                    <tr>
                                        <td style="white-space: nowrap; font-size: 13px;">
                                            <?= date('d M Y', strtotime($row['createdAt'])) ?>
                                            <br>
                                            <small class="text-muted"><?= date('h:i A', strtotime($row['createdAt'])) ?></small>
                                        </td>

                                        <td class="lead-name-cell">
                                            <span title="<?= htmlspecialchars($leadName, ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($leadName, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                            <?php if (!empty($row['recordId'])): ?>
                                                <div class="lead-id">#<?= $row['recordId'] ?></div>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="activity-badge <?= $badgeClass ?>">
                                                <?= htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>

                                        <td class="desc-cell" title="<?= htmlspecialchars($row['description'] ?? '-', ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($row['description'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                        </td>

                                        <td>
                                            <?= $changesHtml ?>
                                        </td>

                                        <td style="white-space: nowrap; font-size: 13px;">
                                            <?= htmlspecialchars($row['employeeName'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                    </tr>

                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="ri-inbox-line fs-4 d-block mb-2"></i>
                                        No activity logs found
                                        <?php if (!empty($dateFrom) || !empty($dateTo) || $employeeFilter > 0 || !empty($statusFilter)): ?>
                                            <br><small>Try adjusting your filters</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>

<script>
$(function () {
    $("#activityTable").DataTable({
        pageLength: 25,
        order: [[0, "desc"]],
        columnDefs: [
            { orderable: false, targets: [2, 4, 5] },
            { width: "130px", targets: 0 },
            { width: "120px", targets: 1 },
            { width: "100px", targets: 2 },
            { width: "160px", targets: 3 },
            { width: "200px", targets: 4 },
            { width: "100px", targets: 5 }
        ],
        language: {
            emptyTable: "No activity logs found"
        },
        responsive: true,
        autoWidth: false,
        scrollX: true
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>