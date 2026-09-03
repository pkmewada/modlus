<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Csrf.php';
require_once __DIR__ . '/../../includes/SocialContentProductionEngine.php';

if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    requireValidCsrfToken();
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
        exit;
    }

    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $action = isset($input['action']) ? trim($input['action']) : '';
    $userId = (int)$_SESSION['userId'];

    $engine = new SocialContentProductionEngine($con);

    switch ($action) {
        case 'assign':
            $task = $engine->assign(
                $id,
                $input['editorId'] ?? 0,
                $userId,
                'admin',
                $input['dueAt'] ?? null,
                $input['remark'] ?? null
            );
            break;

        case 'due_date':
            $task = $engine->setDueAt($id, $input['dueAt'] ?? null, $userId, 'admin', $input['remark'] ?? null);
            break;

        case 'approve':
            $task = $engine->review($id, 'approve', $userId, 'admin', $input['remark'] ?? null);
            break;

        case 'request_correction':
            $task = $engine->review($id, 'request_correction', $userId, 'admin', $input['remark'] ?? null);
            break;

        case 'mark_ready':
            $task = $engine->markReady($id, $userId, 'admin');
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
            exit;
    }

    echo json_encode(['success' => true, 'data' => $task]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
