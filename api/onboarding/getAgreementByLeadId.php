<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadEngine.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
if (
    empty($_SESSION['userId'])
) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ]);

    exit();
}

/*
|--------------------------------------------------------------------------
| Lead Id
|--------------------------------------------------------------------------
*/
$leadId =
    (int)($_GET['leadId'] ?? 0);

if ($leadId <= 0) {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid lead.'
    ]);

    exit();
}

/*
|--------------------------------------------------------------------------
| Load Agreement
|--------------------------------------------------------------------------
*/
$leadEngine =
    new LeadEngine(
        $con
    );

$agreement =
    $leadEngine->getAgreementByLeadId(
        $leadId
    );

echo json_encode([

    'success' => true,

    'data' => [

        'agreementContent' =>
            $agreement['agreementContent'] ?? '',

        'agreementStatus' =>
            $agreement['agreementStatus'] ?? 'draft',

        'createdAt' =>
            $agreement['createdAt'] ?? null,

        'sentAt' =>
            $agreement['sentAt'] ?? null,

        'agreementViewedAt' =>
            $agreement['agreementViewedAt'] ?? null,

        'agreementAcceptedAt' =>
            $agreement['agreementAcceptedAt'] ?? null
    ]
]);