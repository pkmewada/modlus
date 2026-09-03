<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/SocialContentProductionEngine.php';

if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $engine = new SocialContentProductionEngine($con);

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id > 0) {
        $task = $engine->getTask($id);
        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Production task not found.']);
            exit;
        }
        echo json_encode(['success' => true, 'data' => $task]);
        exit;
    }

    $filters = [
        'status' => isset($_GET['status']) ? trim($_GET['status']) : '',
        'editorId' => isset($_GET['editorId']) ? (int)$_GET['editorId'] : 0,
        'clientId' => isset($_GET['clientId']) ? (int)$_GET['clientId'] : 0,
        'month' => isset($_GET['month']) ? trim($_GET['month']) : '',
        'overdue' => isset($_GET['overdue']) && $_GET['overdue'] === '1',
    ];

    $tasks = $engine->listForManager($filters);
    echo json_encode(['success' => true, 'data' => $tasks]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
