<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
$candidateId =
    (int)($_SESSION['candidateId'] ?? 0);

if ($candidateId <= 0) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Request Validation
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);

    exit;
}

$rawInput =
    file_get_contents(
        'php://input'
    );

$payload =
    json_decode(
        (string)$rawInput,
        true
    );

if (!is_array($payload)) {

    $payload = $_POST;
}

/*
|--------------------------------------------------------------------------
| Inputs
|--------------------------------------------------------------------------
*/
$leadId =
    (int)($payload['leadId'] ?? 0);

$status =
    trim(
        (string)(
            $payload['status']
            ?? ''
        )
    );

$remark =
    trim(
        (string)(
            $payload['remark']
            ?? ''
        )
    );

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/
if (
    $leadId <= 0 ||
    $status === '' ||
    $remark === ''
) {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'All fields are required.'
    ]);

    exit;
}

if (
    !in_array(
        $status,
        ['not_interested'],
        true
    )
) {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid status.'
    ]);

    exit;
}

if ($nextPriceIncrementDate === '') {
    $nextPriceIncrementDate = null;
}

/*
|--------------------------------------------------------------------------
| Save Remark
|--------------------------------------------------------------------------
*/
$stmt =
    mysqli_prepare(
        $con,
        "
        INSERT INTO leadStatusRemarks
        (
            leadId,
            status,
            remark,
            createdByCandidateId
        )
        VALUES
        (
            ?, ?, ?, ?
        )
        "
    );

if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Failed to save remark.'
    ]);

    exit;
}

mysqli_stmt_bind_param(
    $stmt,
    'issi',
    $leadId,
    $status,
    $remark,
    $candidateId
);

$saved =
    mysqli_stmt_execute(
        $stmt
    );

mysqli_stmt_close(
    $stmt
);

if (!$saved) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Failed to save remark.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/
echo json_encode([
    'success' => true,
    'message' => 'Remark saved successfully.'
]);