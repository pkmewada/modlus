<?php

require_once __DIR__ . '/PayrollEngine.php';
require_once __DIR__ . '/CompanySettings.php';
require_once __DIR__ . '/SalarySlipRenderer.php';
require_once __DIR__ . '/mailer.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class PayrollApprovalEngine
{
    private mysqli $con;
    private PayrollEngine $payrollEngine;

    public function __construct(mysqli $con)
    {
        $this->con = $con;
        $this->payrollEngine = new PayrollEngine($con);
    }

    public function ensureTables(): void
    {
        mysqli_query(
            $this->con,
            "CREATE TABLE IF NOT EXISTS payrollSalarySlips (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                employeeId INT UNSIGNED NOT NULL,
                periodStart DATE NOT NULL,
                periodEnd DATE NOT NULL,
                periodMonth VARCHAR(20) NOT NULL DEFAULT '',
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                calculationJson LONGTEXT NOT NULL,
                grossEarnings DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                totalDeductions DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                totalReimbursements DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                netPay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                pdfPath VARCHAR(255) NOT NULL DEFAULT '',
                rejectionRemark TEXT NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                submittedBy INT UNSIGNED NOT NULL DEFAULT 0,
                submittedAt DATETIME NULL,
                reviewedBy INT UNSIGNED NOT NULL DEFAULT 0,
                reviewedAt DATETIME NULL,
                isActive TINYINT(1) NOT NULL DEFAULT 1,
                createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_payroll_slip_employee_period (employeeId, periodStart, periodEnd),
                INDEX idx_payroll_slip_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        mysqli_query(
            $this->con,
            "CREATE TABLE IF NOT EXISTS payrollSalarySlipPayments (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                salarySlipId INT UNSIGNED NOT NULL,
                paymentAmount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                paymentMode VARCHAR(40) NOT NULL DEFAULT '',
                transactionReference VARCHAR(120) NOT NULL DEFAULT '',
                transactionDate DATE NOT NULL,
                remarks TEXT NULL,
                createdBy INT UNSIGNED NOT NULL DEFAULT 0,
                createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_payroll_payment_slip (salarySlipId)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
    
    public function sendSalarySlipPreviewEmail(int $employeeId, string $periodStart, string $periodEnd): array
    {
        // Get employee details
        $employee = $this->getEmployeeDetails($employeeId);
        if (!$employee) {
            return ['success' => false, 'message' => 'Employee not found.'];
        }
    
        $email = trim($employee['emailAddress'] ?? '');
        if ($email === '') {
            return ['success' => false, 'message' => 'Employee email address is not available.'];
        }
    
        // Get or create salary slip calculation
        $calculation = $this->payrollEngine->calculateSalarySlip($employeeId, $periodStart, $periodEnd);
        if (empty($calculation['success'])) {
            return [
                'success' => false,
                'message' => $calculation['message'] ?? 'Unable to calculate salary slip.',
            ];
        }
    
        $data = (array)$calculation['data'];
        $employeeName = trim($employee['fullName'] ?? 'Employee');
    
        // Use the central mailer function
        $mailSent = sendSalarySlipPreviewEmail(
            $employeeId,
            $email,
            $employeeName,
            $periodStart,
            $periodEnd,
            $data
        );
    
        return [
            'success' => $mailSent,
            'message' => $mailSent 
                ? 'Salary slip preview sent successfully to ' . htmlspecialchars($employeeName) . '.'
                : 'Unable to send email. Please check mail configuration.',
            'employeeName' => $employeeName,
            'email' => $email,
        ];
    }
    
    /**
 * Get employee details by ID
 * 
 * @param int $employeeId
 * @return array|null
 */
private function getEmployeeDetails(int $employeeId): ?array
{
    $stmt = mysqli_prepare(
        $this->con,
        "SELECT id, fullName, employeeCode, emailAddress, departmentName, designationName 
         FROM employeeusers 
         WHERE id = ? 
         AND employmentStatus = 'Active'
         LIMIT 1"
    );

    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 'i', $employeeId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

    public function submitForApproval(int $employeeId, string $periodStart, string $periodEnd, int $submittedBy): array
    {
        $this->ensureTables();

        $existing = $this->getExistingSlip($employeeId, $periodStart, $periodEnd);

        if ($existing && in_array((string)$existing['status'], ['pending', 'approved'], true)) {
            return [
                'success' => false,
                'message' => (string)$existing['status'] === 'approved'
                    ? 'Salary slip is already approved for this employee and period.'
                    : 'Salary slip is already submitted for approval for this employee and period.',
            ];
        }

        $calculation = $this->payrollEngine->calculateSalarySlip($employeeId, $periodStart, $periodEnd);

        if (empty($calculation['success'])) {
            return [
                'success' => false,
                'message' => (string)($calculation['message'] ?? 'Unable to calculate salary slip.'),
            ];
        }

        $data = (array)$calculation['data'];
        $periodMonth = date('F', strtotime($periodStart));
        $calculationJson = json_encode($data, JSON_UNESCAPED_SLASHES);

        if ($calculationJson === false) {
            return [
                'success' => false,
                'message' => 'Unable to prepare salary slip snapshot.',
            ];
        }

        $gross = (float)($data['earnings']['grossEarnings'] ?? 0);
        $deductions = (float)($data['deductions']['totalDeductions'] ?? 0);
        $reimbursements = (float)($data['reimbursements']['totalReimbursements'] ?? 0);
        $netPay = (float)($data['netPay'] ?? 0);

        if ($existing && (string)$existing['status'] === 'rejected') {
            $stmt = mysqli_prepare(
                $this->con,
                "UPDATE payrollSalarySlips
                 SET status = 'pending',
                     calculationJson = ?,
                     grossEarnings = ?,
                     totalDeductions = ?,
                     totalReimbursements = ?,
                     netPay = ?,
                     pdfPath = '',
                     rejectionRemark = NULL,
                     version = version + 1,
                     submittedBy = ?,
                     submittedAt = NOW(),
                     reviewedBy = 0,
                     reviewedAt = NULL
                 WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                'sddddii',
                $calculationJson,
                $gross,
                $deductions,
                $reimbursements,
                $netPay,
                $submittedBy,
                $existing['id']
            );
        } else {
            $stmt = mysqli_prepare(
                $this->con,
                "INSERT INTO payrollSalarySlips (
                    employeeId,
                    periodStart,
                    periodEnd,
                    periodMonth,
                    status,
                    calculationJson,
                    grossEarnings,
                    totalDeductions,
                    totalReimbursements,
                    netPay,
                    submittedBy,
                    submittedAt
                ) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, NOW())"
            );

            mysqli_stmt_bind_param(
                $stmt,
                'issssddddi',
                $employeeId,
                $periodStart,
                $periodEnd,
                $periodMonth,
                $calculationJson,
                $gross,
                $deductions,
                $reimbursements,
                $netPay,
                $submittedBy
            );
        }

        $saved = mysqli_stmt_execute($stmt);
        $slipId = $existing && (string)$existing['status'] === 'rejected'
            ? (int)$existing['id']
            : (int)mysqli_insert_id($this->con);
        mysqli_stmt_close($stmt);

        return [
            'success' => $saved,
            'message' => $saved
                ? 'Salary slip submitted for Super Admin approval.'
                : 'Unable to submit salary slip for approval.',
            'salarySlipId' => $slipId,
        ];
    }

    public function getExistingSlip(int $employeeId, string $periodStart, string $periodEnd): ?array
    {
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT *
             FROM payrollSalarySlips
             WHERE employeeId = ?
             AND periodStart = ?
             AND periodEnd = ?
             AND isActive = 1
             ORDER BY id DESC
             LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, 'iss', $employeeId, $periodStart, $periodEnd);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        return $row ?: null;
    }

    public function listSlips(string $status = '', string $month = ''): array
    {
        $this->ensureTables();

        $where = ['ps.isActive = 1'];
        $types = '';
        $params = [];

        if ($status !== '') {
            $where[] = 'ps.status = ?';
            $types .= 's';
            $params[] = $status;
        }

        if ($month !== '') {
            $where[] = "DATE_FORMAT(ps.periodStart, '%Y-%m') = ?";
            $types .= 's';
            $params[] = $month;
        }

        $sql = "
            SELECT
                ps.*,
                eu.fullName,
                eu.employeeCode,
                eu.departmentName,
                eu.designationName,
                eu.emailAddress,
                COALESCE(payments.paidAmount, 0) AS paidAmount
            FROM payrollSalarySlips ps
            INNER JOIN employeeusers eu ON eu.id = ps.employeeId
            LEFT JOIN (
                SELECT salarySlipId, SUM(paymentAmount) AS paidAmount
                FROM payrollSalarySlipPayments
                GROUP BY salarySlipId
            ) payments ON payments.salarySlipId = ps.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY ps.submittedAt DESC, ps.id DESC
        ";

        $stmt = mysqli_prepare($this->con, $sql);

        if ($types !== '') {
            $this->bindParams($stmt, $types, $params);
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $row['balanceAmount'] = max(0, (float)$row['netPay'] - (float)$row['paidAmount']);
            $rows[] = $row;
        }

        mysqli_stmt_close($stmt);

        return $rows;
    }

    public function listEmployeeMonthStatus(string $periodStart, string $periodEnd): array
    {
        $this->ensureTables();

        $stmt = mysqli_prepare(
            $this->con,
            "SELECT
                eu.id AS employeeId,
                eu.fullName,
                eu.employeeCode,
                eu.departmentName,
                eu.designationName,
                ps.id AS salarySlipId,
                ps.status,
                ps.netPay,
                ps.pdfPath,
                ps.rejectionRemark,
                ps.submittedAt,
                ps.reviewedAt
             FROM employeeusers eu
             LEFT JOIN payrollSalarySlips ps
                ON ps.employeeId = eu.id
                AND ps.periodStart = ?
                AND ps.periodEnd = ?
                AND ps.isActive = 1
             WHERE eu.employmentStatus = 'Active'
             ORDER BY eu.fullName ASC"
        );

        mysqli_stmt_bind_param($stmt, 'ss', $periodStart, $periodEnd);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $row['status'] = $row['status'] ?: 'not_created';
            $rows[] = $row;
        }

        mysqli_stmt_close($stmt);

        return $rows;
    }

    public function approveSlip(int $salarySlipId, int $reviewedBy, bool $sendEmail = true): array
    {
        $this->ensureTables();
        $slip = $this->getSlip($salarySlipId);

        if (!$slip) {
            return ['success' => false, 'message' => 'Salary slip not found.'];
        }

        if ((string)$slip['status'] === 'approved') {
            return ['success' => false, 'message' => 'Salary slip is already approved.'];
        }

        if ((string)$slip['status'] !== 'pending') {
            return ['success' => false, 'message' => 'Only pending salary slips can be approved.'];
        }

        $data = json_decode((string)$slip['calculationJson'], true);

        if (!is_array($data)) {
            return ['success' => false, 'message' => 'Salary slip snapshot is invalid.'];
        }

        $pdfPath = $this->saveSalarySlipPdf($slip, $data);

        if ($pdfPath === '') {
            return ['success' => false, 'message' => 'Unable to save approved salary slip PDF.'];
        }

        $stmt = mysqli_prepare(
            $this->con,
            "UPDATE payrollSalarySlips
             SET status = 'approved',
                 pdfPath = ?,
                 rejectionRemark = NULL,
                 reviewedBy = ?,
                 reviewedAt = NOW()
             WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, 'sii', $pdfPath, $reviewedBy, $salarySlipId);
        $saved = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $mailSent = $saved && $sendEmail
            ? $this->sendApprovedSalarySlipEmail($salarySlipId, $slip, $pdfPath)
            : false;

        return [
            'success' => $saved,
            'message' => $saved
                ? ($mailSent ? 'Salary slip approved and emailed to employee.' : 'Salary slip approved, but email could not be sent.')
                : 'Unable to approve salary slip.',
            'pdfPath' => $pdfPath,
            'mailSent' => $mailSent,
        ];
    }

    public function sendApprovedSlipEmail(int $salarySlipId): bool
    {
        $slip = $this->getSlip($salarySlipId);

        if (!$slip || (string)$slip['status'] !== 'approved' || trim((string)$slip['pdfPath']) === '') {
            return false;
        }

        return $this->sendApprovedSalarySlipEmail($salarySlipId, $slip, (string)$slip['pdfPath']);
    }

    public function rejectSlip(int $salarySlipId, int $reviewedBy, string $remark): array
    {
        $this->ensureTables();
        $remark = trim($remark);

        if ($remark === '') {
            return ['success' => false, 'message' => 'Rejection remark is required.'];
        }

        $slip = $this->getSlip($salarySlipId);

        if (!$slip) {
            return ['success' => false, 'message' => 'Salary slip not found.'];
        }

        if ((string)$slip['status'] !== 'pending') {
            return ['success' => false, 'message' => 'Only pending salary slips can be rejected.'];
        }

        $stmt = mysqli_prepare(
            $this->con,
            "UPDATE payrollSalarySlips
             SET status = 'rejected',
                 rejectionRemark = ?,
                 reviewedBy = ?,
                 reviewedAt = NOW()
             WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, 'sii', $remark, $reviewedBy, $salarySlipId);
        $saved = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return [
            'success' => $saved,
            'message' => $saved ? 'Salary slip rejected with remark.' : 'Unable to reject salary slip.',
        ];
    }

    public function addPayment(array $payload, int $createdBy): array
    {
        $this->ensureTables();
    
        $salarySlipId = (int)($payload['salarySlipId'] ?? 0);
        $amount = (float)($payload['paymentAmount'] ?? 0);
        $mode = trim((string)($payload['paymentMode'] ?? ''));
        $reference = trim((string)($payload['transactionReference'] ?? ''));
        $date = trim((string)($payload['transactionDate'] ?? ''));
        $remarks = trim((string)($payload['remarks'] ?? ''));
    
        if (
            $salarySlipId <= 0 ||
            $amount <= 0 ||
            $mode === '' ||
            $reference === '' ||
            $date === ''
        ) {
            return [
                'success' => false,
                'message' => 'Payment amount, mode, reference, and date are required.'
            ];
        }
    
        if (strtotime($date) === false) {
            return [
                'success' => false,
                'message' => 'Invalid payment date.'
            ];
        }
    
        $slip = $this->getSlip($salarySlipId);
    
        if (!$slip || (string)$slip['status'] !== 'approved') {
            return [
                'success' => false,
                'message' => 'Payment can be recorded only for approved salary slips.'
            ];
        }
    
        $paidAmount = $this->getPaidAmount($salarySlipId);
    
        $balanceAmount = max(
            0,
            (float)$slip['netPay'] - $paidAmount
        );
    
        if ($amount > $balanceAmount) {
            return [
                'success' => false,
                'message' => 'Payment amount cannot be greater than balance amount.'
            ];
        }
    
        if ($this->hasDuplicatePaymentReference($salarySlipId, $reference)) {
            return [
                'success' => false,
                'message' => 'This payment transaction reference is already recorded for this salary slip.'
            ];
        }
    
        $stmt = mysqli_prepare(
            $this->con,
            "INSERT INTO payrollSalarySlipPayments (
                salarySlipId,
                paymentAmount,
                paymentMode,
                transactionReference,
                transactionDate,
                remarks,
                createdBy
            ) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
    
        mysqli_stmt_bind_param(
            $stmt,
            'idssssi',
            $salarySlipId,
            $amount,
            $mode,
            $reference,
            $date,
            $remarks,
            $createdBy
        );
    
        $saved = mysqli_stmt_execute($stmt);
    
        mysqli_stmt_close($stmt);
    
        if ($saved) {
    
            // Calculate updated paid amount after this payment
            $totalPaidAmount = $this->getPaidAmount($salarySlipId);
    
            $remainingBalance = max(
                0,
                (float)$slip['netPay'] - $totalPaidAmount
            );
    
            // Mark expenses as paid only after salary is fully paid
            if ($remainingBalance <= 0) {
    
                $calculation = json_decode(
                    (string)$slip['calculationJson'],
                    true
                );
    
                $expenseIds = $calculation['reimbursements']['expenseIds'] ?? [];
    
                if (!empty($expenseIds) && is_array($expenseIds)) {
    
                    $expenseIds = array_map('intval', $expenseIds);
                    $expenseIds = array_filter($expenseIds);
    
                    if (!empty($expenseIds)) {
    
                        $idList = implode(',', $expenseIds);
    
                        mysqli_query(
                            $this->con,
                            "UPDATE employeeExpenses
                             SET paymentStatus = 'paid'
                             WHERE id IN ($idList)
                             AND paymentStatus = 'unpaid'"
                        );
                    }
                }
            }
        }
    
        return [
            'success' => $saved,
            'message' => $saved
                ? 'Payment transaction recorded successfully.'
                : 'Unable to record payment transaction.',
        ];
    }

    public function getSlip(int $salarySlipId): ?array
    {
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT
                ps.*,
                eu.fullName,
                eu.employeeCode,
                eu.emailAddress,
                eu.departmentName,
                eu.designationName
             FROM payrollSalarySlips ps
             INNER JOIN employeeusers eu ON eu.id = ps.employeeId
             WHERE ps.id = ?
             AND ps.isActive = 1
             LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, 'i', $salarySlipId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        return $row ?: null;
    }

    private function saveSalarySlipPdf(array $slip, array $data): string
    {
        $monthFolder = date('F', strtotime((string)$slip['periodStart']));
        $uploadDir = dirname(__DIR__) . '/uploads/payroll/' . $monthFolder . '/';

        if (
            !is_dir($uploadDir) &&
            !mkdir($uploadDir, 0755, true) &&
            !is_dir($uploadDir)
        ) {
            return '';
        }

        $employeeName = $this->sanitizeFilePart((string)($slip['fullName'] ?? 'Employee'));
        $filename = $employeeName . '_' . $monthFolder . '_Salaryslip.pdf';
        $relativePath = 'uploads/payroll/' . $monthFolder . '/' . $filename;
        $fullPath = $uploadDir . $filename;

        $html = renderSalarySlipHtml($data, getCompanySettings($this->con));
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return file_put_contents($fullPath, $dompdf->output()) === false ? '' : $relativePath;
    }

    private function sendApprovedSalarySlipEmail(int $salarySlipId, array $slip, string $pdfPath): bool
    {
        $email = trim((string)($slip['emailAddress'] ?? ''));

        if ($email === '') {
            return false;
        }

        $employeeName = trim((string)($slip['fullName'] ?? 'Employee'));
        $month = date('F Y', strtotime((string)$slip['periodStart']));
        $fullPdfPath = dirname(__DIR__) . '/' . ltrim($pdfPath, '/');

        return sendLoggedMail(
            'payroll',
            $salarySlipId,
            'salarySlipApproved',
            $email,
            $employeeName,
            'Salary Slip - ' . $month,
            function () use ($email, $employeeName, $month, $fullPdfPath) {
                $mail = createMailer('MQlus Payroll');
                $mail->addAddress($email, $employeeName);
                $mail->Subject = 'Salary Slip - ' . $month;
                $mail->Body = '<p>Dear ' . htmlspecialchars($employeeName, ENT_QUOTES, 'UTF-8') . ',</p>'
                    . '<p>Your salary slip for ' . htmlspecialchars($month, ENT_QUOTES, 'UTF-8') . ' is attached.</p>'
                    . '<p>Regards,<br>Payroll Team</p>';
                $mail->AltBody = 'Your salary slip for ' . $month . ' is attached.';

                if (is_file($fullPdfPath)) {
                    $mail->addAttachment($fullPdfPath, basename($fullPdfPath));
                }

                return $mail->send();
            }
        );
    }

    private function getPaidAmount(int $salarySlipId): float
    {
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT COALESCE(SUM(paymentAmount), 0) AS paidAmount
             FROM payrollSalarySlipPayments
             WHERE salarySlipId = ?"
        );

        mysqli_stmt_bind_param($stmt, 'i', $salarySlipId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : ['paidAmount' => 0];
        mysqli_stmt_close($stmt);

        return (float)($row['paidAmount'] ?? 0);
    }

    private function hasDuplicatePaymentReference(int $salarySlipId, string $reference): bool
    {
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT id
             FROM payrollSalarySlipPayments
             WHERE salarySlipId = ?
             AND transactionReference = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, 'is', $salarySlipId, $reference);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $exists = $result && mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        return (bool)$exists;
    }

    private function sanitizeFilePart(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9]+/', '_', trim($value));
        $value = trim((string)$value, '_');

        return $value === '' ? 'Employee' : $value;
    }

    private function bindParams(mysqli_stmt $stmt, string $types, array $values): bool
    {
        $params = [$types];

        foreach ($values as $key => $value) {
            $params[] = &$values[$key];
        }

        return call_user_func_array([$stmt, 'bind_param'], $params);
    }
}
