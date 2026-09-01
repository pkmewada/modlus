<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../app/models/AssetModel.php';
require_once __DIR__ . '/../../app/models/AssetAssignmentModel.php';

header('Content-Type: application/json');

try {

    $assetId = (int)$_POST['assetId'];
    $employeeId = (int)$_POST['employeeId'];

    if (!$assetId || !$employeeId) {
        throw new Exception('Invalid data');
    }

    $assignmentModel = new AssetAssignmentModel($con);
    $assetModel = new AssetModel($con);

    // ASSIGN ENTRY
    $assignmentModel->assignAsset($assetId, $employeeId);

    // UPDATE STATUS
    $assetModel->updateStatus($assetId, 'assigned');

    echo json_encode([
        'success' => true,
        'message' => 'Asset assigned successfully'
    ]);

} catch (Throwable $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}