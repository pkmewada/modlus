<?php

class AssetAssignmentModel
{
    private $con;

    public function __construct($db)
    {
        $this->con = $db;
    }

    /*
    |----------------------------------------------------------
    | Check if Asset Already Assigned
    |----------------------------------------------------------
    */
    public function isAlreadyAssigned(int $assetId): bool
    {
        $result = mysqli_query($this->con, "
            SELECT id 
            FROM assetAssignment 
            WHERE assetId = {$assetId} 
            AND status = 'assigned'
            LIMIT 1
        ");

        return mysqli_num_rows($result) > 0;
    }

    /*
    |----------------------------------------------------------
    | Assign Asset
    |----------------------------------------------------------
    */
    public function assignAsset(int $assetId, int $employeeId): bool
    {
        if ($this->isAlreadyAssigned($assetId)) {
            return false;
        }

        $stmt = mysqli_prepare($this->con, "
            INSERT INTO assetAssignment
            (assetId, employeeId, assignedDate, status)
            VALUES (?, ?, NOW(), 'assigned')
        ");

        mysqli_stmt_bind_param($stmt, 'ii', $assetId, $employeeId);

        $assigned = mysqli_stmt_execute($stmt);

        if ($assigned) {
            mysqli_query($this->con, "
                UPDATE assetMaster 
                SET status = 'assigned' 
                WHERE id = {$assetId}
            ");
        }

        return $assigned;
    }

    /*
    |----------------------------------------------------------
    | Return Asset
    |----------------------------------------------------------
    */
    public function returnAsset(int $assetId, string $remarks): bool
    {
        $stmt = mysqli_prepare($this->con, "
            UPDATE assetAssignment
            SET 
                status = 'returned',
                actualReturnDate = NOW(),
                remarks = ?
            WHERE assetId = ?
            AND status = 'assigned'
        ");

        mysqli_stmt_bind_param($stmt, 'si', $remarks, $assetId);

        $returned = mysqli_stmt_execute($stmt);

        if ($returned) {
            mysqli_query($this->con, "
                UPDATE assetMaster 
                SET status = 'available' 
                WHERE id = {$assetId}
            ");
        }

        return $returned;
    }
    /*
    |----------------------------------------------------------
    | Get Assignment History
    |----------------------------------------------------------
    */
    public function getHistory(int $assetId)
    {
        return mysqli_query($this->con, "
            SELECT 
                aa.*,
                e.fullName
            FROM assetAssignment aa
            LEFT JOIN employeeusers e 
                ON e.id = aa.employeeId
            WHERE aa.assetId = {$assetId}
            ORDER BY aa.id DESC
        ");
    }


    
}