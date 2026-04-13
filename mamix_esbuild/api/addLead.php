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

$fullName = trim((string) ($payload['fullName'] ?? ''));
$email = trim((string) ($payload['email'] ?? ''));
$phone = trim((string) ($payload['phone'] ?? ''));
$source = trim((string) ($payload['source'] ?? ''));
$status = 'new';

if ($fullName === '' || $email === '' || $phone === '' || $source === '') {
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

$insertStmt = mysqli_prepare($con, 'INSERT INTO leads (fullName, email, phone, source, status) VALUES (?, ?, ?, ?, ?)');

if (!$insertStmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to add lead.',
    ]);
    exit();
}

mysqli_stmt_bind_param($insertStmt, 'sssss', $fullName, $email, $phone, $source, $status);
$inserted = mysqli_stmt_execute($insertStmt);
$newLeadId = mysqli_insert_id($con);
mysqli_stmt_close($insertStmt);

if (!$inserted || $newLeadId <= 0) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to add lead.',
    ]);
    exit();
}

$selectStmt = mysqli_prepare($con, 'SELECT id, fullName, email, phone, source, status, createdAt FROM leads WHERE id = ? LIMIT 1');

if (!$selectStmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lead added, but failed to load lead details.',
    ]);
    exit();
}

mysqli_stmt_bind_param($selectStmt, 'i', $newLeadId);
mysqli_stmt_execute($selectStmt);
$result = mysqli_stmt_get_result($selectStmt);
$lead = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($selectStmt);

if (!$lead) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lead added, but failed to load lead details.',
    ]);
    exit();
}

echo json_encode([
    'success' => true,
    'message' => 'Lead added successfully',
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
