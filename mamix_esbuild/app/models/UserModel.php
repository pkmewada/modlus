<?php

require_once __DIR__ . '/../../includes/auth-functions.php';

class UserModel
{
    private $db;

    public function __construct()
    {
        global $con;

        $this->db = $con;
    }

    public function getUserByEmail($email)
    {
        $sql = 'SELECT id, password, otp FROM users WHERE email = ? LIMIT 1';
        $stmt = mysqli_prepare($this->db, $sql);

        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $userId, $passwordHash, $otp);

        if (!mysqli_stmt_fetch($stmt)) {
            mysqli_stmt_close($stmt);
            return false;
        }

        mysqli_stmt_close($stmt);

        return [
            'id' => $userId,
            'password' => $passwordHash,
            'otp' => $otp,
        ];
    }

    public function createUser($fullName, $email, $passwordHash, $otp)
    {
        $sql = 'INSERT INTO users (fullName, email, password, otp) VALUES (?, ?, ?, ?)';
        $stmt = mysqli_prepare($this->db, $sql);

        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'ssss', $fullName, $email, $passwordHash, $otp);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $result;
    }

    public function updateOtp($email, $otp, $otpExpiresAt = null)
    {
        $sql = 'UPDATE users SET otp = ?, otpExpiresAt = ? WHERE email = ?';
        $stmt = mysqli_prepare($this->db, $sql);

        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'sss', $otp, $otpExpiresAt, $email);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $result;
    }

    public function verifyOtp($email, $otpCode)
    {
        $sql = 'SELECT id, otp FROM users WHERE email = ? LIMIT 1';
        $stmt = mysqli_prepare($this->db, $sql);

        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $userId, $storedOtp);

        if (!mysqli_stmt_fetch($stmt)) {
            mysqli_stmt_close($stmt);
            return false;
        }

        mysqli_stmt_close($stmt);

        if (trim((string) $storedOtp) !== $otpCode) {
            return false;
        }

        $update = 'UPDATE users SET isVerified = 1, otp = NULL WHERE id = ?';
        $stmt2 = mysqli_prepare($this->db, $update);

        if (!$stmt2) {
            return false;
        }

        mysqli_stmt_bind_param($stmt2, 'i', $userId);
        $result = mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);

        return $result;
    }

    public function markUserVerified($email)
    {
        // DEV MODE OTP BYPASS: mark the user verified without comparing OTP values.
        $sql = 'UPDATE users SET isVerified = 1, otp = NULL WHERE email = ?';
        $stmt = mysqli_prepare($this->db, $sql);

        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 's', $email);
        $result = mysqli_stmt_execute($stmt);
        $affectedRows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        return $result && $affectedRows >= 0;
    }
}
