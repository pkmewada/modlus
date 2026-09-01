<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadActivityLogger.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
if (
    empty($_SESSION['candidateId']) &&
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
| Request Method
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);

    exit();
}

/*
|--------------------------------------------------------------------------
| Payload
|--------------------------------------------------------------------------
*/
$rawInput = file_get_contents('php://input');

$payload = json_decode(
    (string)$rawInput,
    true
);

if (!is_array($payload)) {
    $payload = $_POST;
}

/*
|--------------------------------------------------------------------------
| Inputs
|--------------------------------------------------------------------------
*/
$id = (int)(
    $payload['id'] ?? 0
);

$fullName = trim(
    (string)($payload['fullName'] ?? '')
);

$email = trim(
    (string)($payload['email'] ?? '')
);

$phone = trim(
    (string)($payload['phone'] ?? '')
);

$source = trim(
    (string)($payload['source'] ?? '')
);


$orgName = trim(
    (string)($payload['orgName'] ?? '')
);

$categoryId = (int)(
    $payload['categoryId'] ?? 0
);

$planId = (int)(
    $payload['planId'] ?? 0
);

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/
if (
    $id <= 0
    || $fullName === ''
    || $email === ''
    || $phone === ''
    || $source === ''
    || $orgName === ''
    || $categoryId <= 0
    || $planId <= 0
) {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'All fields are required.'
    ]);

    exit();
}

if (
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )
) {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Please enter a valid email address.'
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| Fetch Old Lead Data For Activity Log
|--------------------------------------------------------------------------
*/

$oldStmt = mysqli_prepare(
    $con,
    "
    SELECT
        fullName,
        email,
        phone,
        source,
        orgName,
        categoryId,
        planId
    FROM leads
    WHERE id = ?
    LIMIT 1
    "
);


$oldLead = null;


if ($oldStmt) {


    mysqli_stmt_bind_param(
        $oldStmt,
        "i",
        $id
    );


    mysqli_stmt_execute(
        $oldStmt
    );


    $oldResult =
        mysqli_stmt_get_result(
            $oldStmt
        );


    $oldLead =
        mysqli_fetch_assoc(
            $oldResult
        );


    mysqli_stmt_close(
        $oldStmt
    );

}



/*
|--------------------------------------------------------------------------
| Update Lead
|--------------------------------------------------------------------------
*/
$updateStmt = mysqli_prepare(
    $con,
    "
    UPDATE leads
    SET
        fullName = ?,
        email = ?,
        phone = ?,
        source = ?,
        orgName = ?,
        categoryId = ?,
        planId = ?
    WHERE id = ?
    "
);

if (!$updateStmt) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Failed to update lead.'
    ]);

    exit();
}

mysqli_stmt_bind_param(
    $updateStmt,
    'sssssiii',
    $fullName,
    $email,
    $phone,
    $source,
    $orgName,
    $categoryId,
    $planId,
    $id
);

$updated = mysqli_stmt_execute(
    $updateStmt
);

mysqli_stmt_close(
    $updateStmt
);

if (!$updated) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Failed to update lead.'
    ]);

    exit();
}

/*
|--------------------------------------------------------------------------
| Fetch Updated Lead
|--------------------------------------------------------------------------
*/
$selectStmt = mysqli_prepare(
    $con,
    "
        SELECT
    
        l.id,
        l.fullName,
        l.email,
        l.phone,
        l.source,
        l.orgName,
        l.categoryId,
        l.planId,
        l.status,
        l.createdAt,
        l.createdByCandidateId,
    
        c.categoryName,
        p.planName,
    
        eu.fullName AS employeeName
    
    FROM leads l
    
    LEFT JOIN leadCategories c
        ON c.id = l.categoryId
    
    LEFT JOIN leadPlans p
        ON p.id = l.planId
    
    LEFT JOIN employeeusers eu
        ON eu.candidateRecordId = l.createdByCandidateId
    
    WHERE l.id = ?
    
    LIMIT 1
    "
);

if (!$selectStmt) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Lead updated, but failed to load lead details.'
    ]);

    exit();
}

mysqli_stmt_bind_param(
    $selectStmt,
    'i',
    $id
);

mysqli_stmt_execute(
    $selectStmt
);

$result = mysqli_stmt_get_result(
    $selectStmt
);

$lead = $result
    ? mysqli_fetch_assoc($result)
    : null;

mysqli_stmt_close(
    $selectStmt
);

if (!$lead) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Lead updated, but failed to load lead details.'
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| Activity Logger
|--------------------------------------------------------------------------
*/


saveActivityLog(

    $con,

    "Lead",

    $id,

    "UPDATE",

    "Lead updated : " . $fullName,

    $oldLead,

    [

        "fullName" => $fullName,

        "email" => $email,

        "phone" => $phone,

        "source" => $source,

        "organization" => $orgName,

        "categoryId" => $categoryId,

        "planId" => $planId

    ]

);
/*
|--------------------------------------------------------------------------
| Success Response
|--------------------------------------------------------------------------
*/
echo json_encode([

    'success' => true,

    'message' => 'Lead updated successfully',

    'data' => [

        'id' =>
            (int)$lead['id'],

        'fullName' =>
            $lead['fullName'],

        'email' =>
            $lead['email'],

        'phone' =>
            $lead['phone'],

        'source' =>
            $lead['source'],
            
        'orgName' =>
            $lead['orgName'],

        'categoryId' =>
            (int)$lead['categoryId'],

        'planId' =>
            (int)$lead['planId'],

        'categoryName' =>
            $lead['categoryName'] ?? '',

        'planName' =>
            $lead['planName'] ?? '',
            
        'createdByCandidateId' =>
            (int)($lead['createdByCandidateId'] ?? 0),
        
        'employeeName' =>
            $lead['employeeName'] ?? '',

        'status' =>
            $lead['status'],

        'createdDate' =>
            date(
                'd M Y h:i A',
                strtotime(
                    (string)$lead['createdAt']
                )
            )
    ]
]);
?>
