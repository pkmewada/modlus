<?php
include __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
/*
|--------------------------------------------------------------------------
| Build Old Candidate Folder Name
| Example:
| Praveen Mewada + ID 1 = praveenMewada_1
|--------------------------------------------------------------------------
*/
function buildEmployeeFolderName(string $fullName, int $id): string
{
    $fullName = preg_replace('/[^a-zA-Z0-9 ]/', '', $fullName);
    $parts = preg_split('/\s+/', trim($fullName));

    if (!$parts || empty($parts[0])) {
        return 'employee_' . $id;
    }

    $folder = strtolower(array_shift($parts));

    foreach ($parts as $part) {
        $folder .= ucfirst(strtolower($part));
    }

    return $folder . '_' . $id;
}

/*
|--------------------------------------------------------------------------
| Validate Request
|--------------------------------------------------------------------------
*/
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid ID'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch Employee Row
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare($con, "
    SELECT *
    FROM employeeusers
    WHERE candidateRecordId = ?
    ORDER BY id DESC
    LIMIT 1
");

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$user   = mysqli_fetch_assoc($result);

if (!$user) {
    echo json_encode([
        'success' => false,
        'message' => 'Employee record not found'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Restore Old Folder Path Logic
|--------------------------------------------------------------------------
*/
$employeeId = (int)($user['id'] ?? 0);
$fullName   = (string)($user['fullName'] ?? '');

$folderName = buildEmployeeFolderName($fullName, $employeeId);

/*
|--------------------------------------------------------------------------
| Public Path For JS Modal
|--------------------------------------------------------------------------
*/
$user['folderPath'] =
    UPLOAD_URL . '/candidates/' . $folderName . '/';

/*
|--------------------------------------------------------------------------
| Fetch Verification Status
|--------------------------------------------------------------------------
*/
$verify = [];

$stmt2 = mysqli_prepare($con, "
    SELECT fieldName, verifyStatus, reviewRemark
    FROM employeeProfileVerification
    WHERE employeeUserId = ?
");

mysqli_stmt_bind_param($stmt2, "i", $employeeId);
mysqli_stmt_execute($stmt2);

$res2 = mysqli_stmt_get_result($stmt2);

while ($row = mysqli_fetch_assoc($res2)) {

    $verify[$row['fieldName']] = [
        'status' => !empty($row['verifyStatus'])
            ? $row['verifyStatus']
            : 'Pending',

        'remark' => $row['reviewRemark'] ?? ''
    ];
}


mysqli_stmt_close($stmt);
mysqli_stmt_close($stmt2);
/*
|--------------------------------------------------------------------------
| Final Response
|--------------------------------------------------------------------------
*/
echo json_encode([
    'success' => true,
    'data'    => $user,
    'verify'  => $verify
]);