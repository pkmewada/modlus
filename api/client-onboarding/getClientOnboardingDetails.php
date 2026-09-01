<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/leadEngine.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['userId'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$clientId = isset($_POST['clientId']) ? (int)$_POST['clientId'] : 0;

if (!$clientId) {
    echo json_encode(['success' => false, 'message' => 'Invalid client ID']);
    exit;
}

$leadEngine = new LeadEngine($con);
$details = $leadEngine->getClientOnboardingDetails($clientId);

echo json_encode(['success' => true, 'data' => $details]);