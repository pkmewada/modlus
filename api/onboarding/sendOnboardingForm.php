<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/leadEngine.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['userId'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['formId'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$leadEngine = new LeadEngine($con);
$result = $leadEngine->sendOnboardingFormToClient($input['formId'], $_SESSION['userId']);

echo json_encode($result);