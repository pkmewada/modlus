<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../app/models/AssetModel.php';
require_once __DIR__ . '/../../app/models/AssetAssignmentModel.php';

header('Content-Type: application/json');

try {

    $assetId = (int)$_POST['assetId'];
    $condition = $_POST['conditionStatus'] ?? 'good';
    $remarks = $_POST['remarks'] ?? '';

    if (!$assetId) {
        throw new Exception('Invalid asset');
    }

    $assetModel = new AssetModel($con);
    $assignmentModel = new AssetAssignmentModel($con);

    // UPDATE ASSIGNMENT (MARK RETURNED)
    $assignmentModel->returnAsset($assetId, $condition, $remarks);

    // UPDATE ASSET STATUS
    $assetModel->updateStatus($assetId, 'available');

    echo json_encode([
        'success' => true,
        'message' => 'Asset returned successfully'
    ]);

} catch (Throwable $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}