<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Csrf.php';
require_once __DIR__ . '/../../includes/socialContentEngine.php';
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
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'A content entry id is required.']);
        exit;
    }

    $userId = (int)$_SESSION['userId'];
    $contentEngine = new SocialContentEngine($con);
    $productionEngine = new SocialContentProductionEngine($con);

    // ownership: the entry is looked up strictly by its own primary key —
    // clientId/platformId/etc are never taken from the request, only ever
    // read back from the stored row itself (same pattern as save-entry.php
    // and the existing social-content-production APIs)
    mysqli_begin_transaction($con);
    try {
        $entry = $contentEngine->completeEntry($id, $userId);

        // idempotent: reuse an existing task instead of racing createTask()'s
        // own clash check — safe against double-click / retry / refresh
        $task = $productionEngine->getTaskByContentId($id);
        if (!$task) {
            $task = $productionEngine->createTask($id, $userId, 'admin', 'Auto-created: Data Entry marked ready.');
        }

        mysqli_commit($con);
    } catch (Exception $e) {
        mysqli_rollback($con);
        throw $e;
    }

    echo json_encode(['success' => true, 'data' => ['entry' => $entry, 'production' => $task]]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
