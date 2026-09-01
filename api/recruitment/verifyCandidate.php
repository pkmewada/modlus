<?php
include __DIR__ . '/../includes/db.php';

$id = (int)($_POST['id'] ?? 0);

mysqli_query($con,"
UPDATE employeeUsers
SET profileStatus='Verified'
WHERE candidateRecordId='$id'
");

echo json_encode(['success'=>true]);