<?php

require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../includes/db.php';

require_once __DIR__ . '/../includes/leave-balance.php';

require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json');

/*
|--------------------------------------------------------------------------
| Response Helper
|--------------------------------------------------------------------------
*/

function respond(
    $success,
    $message
) {

    echo json_encode([

        'success' => $success,

        'message' => $message

    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Session
|--------------------------------------------------------------------------
*/

$adminId =
    (int)(
        $_SESSION['userId'] ?? 0
    );

if ($adminId <= 0) {

    respond(
        false,
        'Invalid session'
    );
}

/*
|--------------------------------------------------------------------------
| Inputs
|--------------------------------------------------------------------------
*/

$id =
    (int)(
        $_POST['id'] ?? 0
    );

$status =
    strtolower(
        trim(
            $_POST['status'] ?? ''
        )
    );

$allowed = [

    'approved',
    'rejected'
];

if (

    $id <= 0 ||

    !in_array(
        $status,
        $allowed,
        true
    )

) {

    respond(
        false,
        'Invalid request'
    );
}

/*
|--------------------------------------------------------------------------
| Transaction
|--------------------------------------------------------------------------
*/

mysqli_begin_transaction($con);

try {

    /*
    |--------------------------------------------------------------------------
    | Fetch Leave
    |--------------------------------------------------------------------------
    */

    $stmt = mysqli_prepare(

        $con,

        "

        SELECT

            la.id,
            la.status,

            la.employeeId,
            la.leaveTypeId,

            la.totalDays,

            la.fromDate,
            la.toDate,

            eu.emailAddress,
            eu.fullName,

            lt.name AS leaveTypeName,
            lt.allowNegative

        FROM leaveApplications la

        LEFT JOIN employeeusers eu
            ON eu.id = la.employeeId

        LEFT JOIN leaveTypes lt
            ON lt.id = la.leaveTypeId

        WHERE la.id = ?

        LIMIT 1

        FOR UPDATE

        "
    );

    if (!$stmt) {

        throw new Exception(
            'Failed to prepare leave query'
        );
    }

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id
    );

    mysqli_stmt_execute($stmt);

    $result =
        mysqli_stmt_get_result($stmt);

    $leave =
        mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    if (!$leave) {

        throw new Exception(
            'Leave not found'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Already Processed
    |--------------------------------------------------------------------------
    */

    if (

        strtolower(
            $leave['status']
        ) !== 'pending'

    ) {

        throw new Exception(
            'Leave already processed'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Balance Validation
    |--------------------------------------------------------------------------
    */

    if ($status === 'approved') {

        $balance = getOrCreateBalance($con, (int)$leave['employeeId'], (int)$leave['leaveTypeId']);

        if (

            (int)($leave['allowNegative'] ?? 0) !== 1 &&
            (float)$balance['remainingLeaves']
            <
            (float)$leave['totalDays']

        ) {

            throw new Exception(
                'Insufficient leave balance'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update Leave Status
    |--------------------------------------------------------------------------
    */

    $updateStmt = mysqli_prepare(

        $con,

        "

        UPDATE leaveApplications

        SET status = ?

        WHERE id = ?

        "
    );

    if (!$updateStmt) {

        throw new Exception(
            'Failed to prepare update query'
        );
    }

    mysqli_stmt_bind_param(

        $updateStmt,

        "si",

        $status,
        $id
    );

    if (
        !mysqli_stmt_execute(
            $updateStmt
        )
    ) {

        throw new Exception(
            'Failed to update leave status'
        );
    }

    mysqli_stmt_close($updateStmt);

    /*
    |--------------------------------------------------------------------------
    | Update Leave Balance
    |--------------------------------------------------------------------------
    */

    if ($status === 'approved') {

        $balanceStmt = mysqli_prepare(

            $con,

            "

            UPDATE leaveBalances

            SET

                usedLeaves =
                    usedLeaves + ?,

                remainingLeaves =
                    remainingLeaves - ?

            WHERE

                employeeId = ?
                AND leaveTypeId = ?

            "
        );

        if (!$balanceStmt) {

            throw new Exception(
                'Failed to prepare balance query'
            );
        }

        mysqli_stmt_bind_param(

            $balanceStmt,

            "ddii",

            $leave['totalDays'],
            $leave['totalDays'],

            $leave['employeeId'],
            $leave['leaveTypeId']
        );

        if (
            !mysqli_stmt_execute(
                $balanceStmt
            )
        ) {

            throw new Exception(
                'Failed to update balance'
            );
        }

        mysqli_stmt_close($balanceStmt);
    }

    mysqli_commit($con);

} catch (Exception $e) {

    mysqli_rollback($con);

    respond(
        false,
        $e->getMessage()
    );
}

/*
|--------------------------------------------------------------------------
| Send Mail
|--------------------------------------------------------------------------
*/

try {

    if (
        !empty(
            $leave['emailAddress']
        )
    ) {

        if ($status === 'approved') {

            sendLeaveApprovedEmail(

                $id,

                $leave['emailAddress'],

                $leave['fullName'],

                $leave['leaveTypeName'],

                $leave['fromDate'],

                $leave['toDate']
            );

        } else {

            sendLeaveRejectedEmail(

                $id,

                $leave['emailAddress'],

                $leave['fullName'],

                $leave['leaveTypeName'],

                $leave['fromDate'],

                $leave['toDate']
            );
        }
    }

} catch (Throwable $e) {

    error_log(
        'Leave Status Mail Error: ' .
        $e->getMessage()
    );
}

/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/

respond(
    true,
    'Leave status updated successfully.'
);
