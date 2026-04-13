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
$fullName = trim((string) ($payload['fullName'] ?? ''));
$email = trim((string) ($payload['email'] ?? ''));
$phone = trim((string) ($payload['phone'] ?? ''));
$source = trim((string) ($payload['source'] ?? ''));

if ($id <= 0 || $fullName === '' || $email === '' || $phone === '' || $source === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'All fields are required.',
    ]);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please enter a valid email address.',
    ]);
    exit();
}

$updateStmt = mysqli_prepare($con, 'UPDATE leads SET fullName = ?, email = ?, phone = ?, source = ? WHERE id = ?');

if (!$updateStmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update lead.',
    ]);
    exit();
}

mysqli_stmt_bind_param($updateStmt, 'ssssi', $fullName, $email, $phone, $source, $id);
$updated = mysqli_stmt_execute($updateStmt);
mysqli_stmt_close($updateStmt);

if (!$updated) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update lead.',
    ]);
    exit();
}

$selectStmt = mysqli_prepare($con, 'SELECT id, fullName, email, phone, source, status, createdAt FROM leads WHERE id = ? LIMIT 1');

if (!$selectStmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lead updated, but failed to load lead details.',
    ]);
    exit();
}

mysqli_stmt_bind_param($selectStmt, 'i', $id);
mysqli_stmt_execute($selectStmt);
$result = mysqli_stmt_get_result($selectStmt);
$lead = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($selectStmt);

if (!$lead) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lead updated, but failed to load lead details.',
    ]);
    exit();
}

echo json_encode([
    'success' => true,
    'message' => 'Lead updated successfully',
    'data' => [
        'id' => (int) $lead['id'],
        'fullName' => $lead['fullName'],
        'email' => $lead['email'],
        'phone' => $lead['phone'],
        'source' => $lead['source'],
        'status' => $lead['status'],
        'createdDate' => date('d M Y h:i A', strtotime((string) $lead['createdAt'])),
    ],
]);