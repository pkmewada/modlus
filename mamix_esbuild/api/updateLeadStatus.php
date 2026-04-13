<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['userId'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.',
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.',
    ]);
    exit();
}

$rawInput = file_get_contents('php://input');
$payload = json_decode((string) $rawInput, true);

if (!is_array($payload)) {
    $payload = $_POST;
}

$id = (int) ($payload['id'] ?? 0);
$status = trim((string) ($payload['status'] ?? ''));
$allowedStatuses = ['new', 'contacted', 'qualified', 'closed'];

if ($id <= 0 || !in_array($status, $allowedStatuses, true)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status update request.',
    ]);
    exit();
}

$updateStmt = mysqli_prepare($con, 'UPDATE leads SET status = ? WHERE id = ?');

if (!$updateStmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update lead status.',
    ]);
    exit();
}

mysqli_stmt_bind_param($updateStmt, 'si', $status, $id);
$updated = mysqli_stmt_execute($updateStmt);
mysqli_stmt_close($updateStmt);

if (!$updated) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update lead status.',
    ]);
    exit();
}

echo json_encode([
    'success' => true,
    'message' => 'Status updated successfully',
    'data' => [
        'id' => $id,
        'status' => $status,
    ],
]);
