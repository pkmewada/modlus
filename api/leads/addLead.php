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

$rawInput =
    file_get_contents(
        'php://input'
    );


$payload =
    json_decode(
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


$fullName =
    trim(
        (string)($payload['fullName'] ?? '')
    );


$email =
    trim(
        (string)($payload['email'] ?? '')
    );


$phone =
    trim(
        (string)($payload['phone'] ?? '')
    );


$source =
    trim(
        (string)($payload['source'] ?? '')
    );


$orgName =
    trim(
        (string)($payload['orgName'] ?? '')
    );


$categoryId =
    (int)(
        $payload['categoryId'] ?? 0
    );


$planId =
    (int)(
        $payload['planId'] ?? 0
    );


$status = 'open';


$createdByCandidateId =
    !empty($_SESSION['candidateId'])
        ? (int)$_SESSION['candidateId']
        : (int)($_SESSION['userId'] ?? 'Admin');


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if (
    $fullName === '' ||
    $email === '' ||
    $phone === '' ||
    $source === '' ||
    $orgName === '' ||
    $categoryId <= 0 ||
    $planId <= 0
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
| Insert Lead
|--------------------------------------------------------------------------
*/


$insertStmt =
    mysqli_prepare(
        $con,
        "
        INSERT INTO leads
        (
            fullName,
            email,
            phone,
            source,
            orgName,
            categoryId,
            planId,
            status,
            createdByCandidateId
        )
        VALUES
        (
            ?,?,?,?,?,?,?,?,?
        )
        "
    );


if (!$insertStmt) {


    http_response_code(500);


    echo json_encode([
        'success' => false,
        'message' => 'Failed to add lead.'
    ]);


    exit();

}



mysqli_stmt_bind_param(
    $insertStmt,
    'sssssiisi',
    $fullName,
    $email,
    $phone,
    $source,
    $orgName,
    $categoryId,
    $planId,
    $status,
    $createdByCandidateId
);



$inserted =
    mysqli_stmt_execute(
        $insertStmt
    );


$newLeadId =
    mysqli_insert_id(
        $con
    );


mysqli_stmt_close(
    $insertStmt
);



if (
    !$inserted ||
    $newLeadId <= 0
) {


    http_response_code(500);


    echo json_encode([
        'success' => false,
        'message' => 'Failed to add lead.'
    ]);


    exit();

}


/*
|--------------------------------------------------------------------------
| Fetch Lead
|--------------------------------------------------------------------------
*/


$selectStmt =
    mysqli_prepare(
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

            c.categoryName,

            p.planName,

            eu.fullName AS employeeName

        FROM leads l

        LEFT JOIN leadCategories c
            ON c.id = l.categoryId

        LEFT JOIN leadPlans p
            ON p.id = l.planId

        LEFT JOIN employeeusers eu
            ON eu.id = l.createdByCandidateId

        WHERE l.id = ?

        LIMIT 1
        "
    );


if (!$selectStmt) {


    http_response_code(500);


    echo json_encode([
        'success' => false,
        'message' => 'Lead added, but failed to load lead details.'
    ]);


    exit();

}



mysqli_stmt_bind_param(
    $selectStmt,
    'i',
    $newLeadId
);



mysqli_stmt_execute(
    $selectStmt
);



$result =
    mysqli_stmt_get_result(
        $selectStmt
    );


$lead =
    $result
        ? mysqli_fetch_assoc($result)
        : null;



mysqli_stmt_close(
    $selectStmt
);



if (!$lead) {


    http_response_code(500);


    echo json_encode([
        'success' => false,
        'message' => 'Lead added, but failed to load lead details.'
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

    $newLeadId,

    "CREATE",

    "New lead created : " . $fullName,

    null,

    [

        "fullName" => $fullName,

        "email" => $email,

        "phone" => $phone,

        "source" => $source,

        "organization" => $orgName,

        "categoryId" => $categoryId,

        "planId" => $planId,

        "status" => $status

    ]

);


/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/


echo json_encode([

    'success' => true,

    'message' => 'Lead added successfully',

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


        'status' =>
            $lead['status'],


        'employeeName' =>
            $lead['employeeName'] ?? 'Admin',


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