<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

try {

    $employeeId =
        (int)(
            $_GET['employeeId']
            ?? 0
        );

    $fromDate =
        trim(
            $_GET['fromDate']
            ?? ''
        );

    $toDate =
        trim(
            $_GET['toDate']
            ?? ''
        );

    if (
        $employeeId <= 0
    ) {

        throw new Exception(
            'Employee required'
        );
    }

    if (
        empty($fromDate)
        ||
        empty($toDate)
    ) {

        throw new Exception(
            'Date range required'
        );
    }

    $stmt = mysqli_prepare(

        $con,

        "SELECT

            attendanceDate,

            totalWorkingSeconds,

            totalBreakSeconds,

            attendanceStatus

         FROM employeeAttendance

         WHERE employeeId=?

         AND attendanceDate
         BETWEEN ? AND ?

         ORDER BY attendanceDate ASC"
    );

    mysqli_stmt_bind_param(

        $stmt,

        "iss",

        $employeeId,
        $fromDate,
        $toDate
    );

    mysqli_stmt_execute(
        $stmt
    );

    $result =
        mysqli_stmt_get_result(
            $stmt
        );

    $rows = [];

    $summary = [

        'workingSeconds' => 0,

        'breakSeconds' => 0,

        'presentDays' => 0,

        'halfDays' => 0
    ];

    while (
        $row =
            mysqli_fetch_assoc(
                $result
            )
    ) {

        $rows[] = $row;

        $summary[
            'workingSeconds'
        ] +=
            (int)$row[
                'totalWorkingSeconds'
            ];

        $summary[
            'breakSeconds'
        ] +=
            (int)$row[
                'totalBreakSeconds'
            ];

        if (
            $row['attendanceStatus']
            === 'present'
        ) {

            $summary[
                'presentDays'
            ]++;
        }

        if (
            $row['attendanceStatus']
            === 'half_day'
        ) {

            $summary[
                'halfDays'
            ]++;
        }
    }

    echo json_encode([

        'success' => true,

        'data' => $rows,

        'summary' => $summary
    ]);

} catch (Exception $e) {

    echo json_encode([

        'success' => false,

        'message' =>
            $e->getMessage()
    ]);
}