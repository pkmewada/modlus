<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/permission-helper.php';
require_once __DIR__ . '/../includes/leadActivityLogger.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isLoggedIn()) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ]);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);

    exit;
}

$userType = getLoggedInUserType();
$permissionRoute = $userType === 'employee' ? '/emp-leads' : '/leads';

requireActionPermission($permissionRoute, 'import_leads');

/*
|--------------------------------------------------------------------------
| Static Values
|--------------------------------------------------------------------------
| Later change these IDs as per your database.
|--------------------------------------------------------------------------
*/

$staticCategoryId = 3;
$staticPlanId = 3;
$status = 'open';

$employeeId = (int)($_POST['employeeId'] ?? 0);

if ($userType === 'employee') {
    $employeeId = getLoggedInUserId();
}

if ($employeeId <= 0) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Please select employee.'
    ]);

    exit;
}

if (
    empty($_FILES['leadCsvFile'])
    || $_FILES['leadCsvFile']['error'] !== UPLOAD_ERR_OK
) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Please upload a valid CSV file.'
    ]);

    exit;
}

$fileName = $_FILES['leadCsvFile']['name'];
$fileTmpPath = $_FILES['leadCsvFile']['tmp_name'];
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if ($fileExt !== 'csv') {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Only CSV file is allowed.'
    ]);

    exit;
}

$handle = fopen($fileTmpPath, 'r');

if (!$handle) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to read CSV file.'
    ]);

    exit;
}

$header = fgetcsv($handle);

$requiredColumns = [
    'fullName',
    'email',
    'phone',
    'source',
    'orgName'
];

$header = array_map('trim', $header ?: []);

foreach ($requiredColumns as $column) {
    if (!in_array($column, $header, true)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid CSV format. Required columns: fullName, email, phone, source'
        ]);

        fclose($handle);
        exit;
    }
}

$columnMap = array_flip($header);

$insertStmt = mysqli_prepare(
    $con,
    "
    INSERT INTO leads
    (
        fullName,
        email,
        phone,
        source,
        orgName,
        categoryId,
        planId,
        status,
        createdByCandidateId
    )
    VALUES
    (
        ?, ?, ?, ?, ?, ?, ?, ?, ?
    )
    "
);

if (!$insertStmt) {
    fclose($handle);

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Failed to prepare import query.'
    ]);

    exit;
}

$imported = 0;
$skipped = 0;
$errors = [];

while (($row = fgetcsv($handle)) !== false) {

    $fullName = trim($row[$columnMap['fullName']] ?? '');
    $email = trim($row[$columnMap['email']] ?? '');
    $phone = trim($row[$columnMap['phone']] ?? '');
    $source = trim($row[$columnMap['source']] ?? '');
    $orgName = trim($row[$columnMap['orgName']] ?? '');

    if (
        $fullName === ''
        || $email === ''
        || $phone === ''
        || $source === ''
        || $orgName === ''
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $skipped++;
        continue;
    }

    mysqli_stmt_bind_param(
        $insertStmt,
        'sssssiisi',
        $fullName,
        $email,
        $phone,
        $source,
        $orgName,
        $staticCategoryId,
        $staticPlanId,
        $status,
        $employeeId
    );

    if (mysqli_stmt_execute($insertStmt)) {
        $imported++;
    } else {
        $skipped++;
        $errors[] = $email;
    }
}

mysqli_stmt_close($insertStmt);
fclose($handle);


/*
|--------------------------------------------------------------------------
| Activity Logger
|--------------------------------------------------------------------------
*/


if ($imported > 0) {


    saveActivityLog(

        $con,

        "Lead",

        null,

        "IMPORT",

        "Bulk lead import completed",

        null,

        [

            "fileName" =>
                $fileName,


            "assignedEmployeeId" =>
                $employeeId,


            "imported" =>
                $imported,


            "skipped" =>
                $skipped,


            "errors" =>
                $errors,


            "categoryId" =>
                $staticCategoryId,


            "planId" =>
                $staticPlanId,


            "status" =>
                $status

        ]

    );

}

echo json_encode([
    'success' => true,
    'message' => $imported . ' leads imported successfully. ' . $skipped . ' skipped.',
    'data' => [
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => $errors
    ]
]);
