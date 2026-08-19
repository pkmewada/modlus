<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/leadEngine.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
| Only HR/Admin (userId) can access this endpoint.
*/

if (empty($_SESSION['userId'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Validate agreementId
|--------------------------------------------------------------------------
*/
$agreementId = (int)($_GET['agreementId'] ?? 0);

if ($agreementId <= 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid agreement.'
    ]);
    exit;
}

$leadEngine = new LeadEngine($con);

/*
|--------------------------------------------------------------------------
| Load Agreement Submission
|--------------------------------------------------------------------------
*/
$submission = $leadEngine->getSubmissionByAgreementId($agreementId);

if (!$submission) {
    echo json_encode([
        'success' => false,
        'message' => 'Submission not found.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Return JSON Response
|--------------------------------------------------------------------------
*/
echo json_encode([
    'success' => true,
    'data' => [
        'agreementId' => (int)$submission['agreementId'],
        'fullName' => $submission['fullName'] ?? '',
        'email' => $submission['email'] ?? '',
        'finalPrice' => (float)($submission['finalPrice'] ?? 0),
        'businessDocument' => SITE_URL . '/uploads/onboarding/documents/' . $submission['businessDocument'],
        'signatureFile' => SITE_URL . '/uploads/onboarding/signatures/' . $submission['signatureFile'],
        'signatoryName' => $submission['signatoryName'] ?? '',
        'submittedAt' => $submission['submittedAt'] ?? '',
        'reviewStatus' => $submission['reviewStatus'] ?? 'pending',
        'agreementContent' => $submission['agreementContent'] ?? '',
        'reviewRemark' => $submission['reviewRemark'] ?? '',
        'agreementStatus' => $submission['agreementStatus'] ?? ''
    ]
]);