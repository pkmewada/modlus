<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/calendarEngine.php';

if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['client_id']) || !isset($input['plans'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
        exit;
    }

    $clientId = (int)$input['client_id'];
    $plans = $input['plans'];

    $engine = new CalendarEngine($con);
    $saved = $engine->saveCalendarPlan($clientId, $plans);

    echo json_encode([
        'success' => true,
        'message' => 'Calendar plan saved successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}