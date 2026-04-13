<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

function getMailLogPath(): string
{
    $logDirectory = dirname(__DIR__) . '/logs';

    if (!is_dir($logDirectory)) {
        mkdir($logDirectory, 0777, true);
    }

    return $logDirectory . '/mail.log';
}

function writeMailLog(string $message): void
{
    file_put_contents(getMailLogPath(), $message . PHP_EOL, FILE_APPEND);
}

function buildOtpHtmlTemplate(string $otp): string
{
    $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify your email - OTP Code</title>
</head>
<body style="margin:0;padding:24px;background-color:#f4f6f8;font-family:Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;">
        <tr>
            <td style="padding:32px;">
                <div style="font-size:24px;font-weight:700;color:#111827;margin-bottom:24px;">Modlus CRM</div>
                <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Hello,</p>
                <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
                    Thank you for signing up with Modlus CRM.
                </p>
                <p style="margin:0 0 12px;font-size:16px;line-height:1.6;">
                    Your verification code is:
                </p>
                <div style="margin:0 0 20px;padding:16px 20px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;display:inline-block;">
                    <span style="font-size:32px;font-weight:700;letter-spacing:6px;color:#111827;">{$safeOtp}</span>
                </div>
                <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#4b5563;">
                    This code is valid for 5 minutes.
                </p>
                <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#4b5563;">
                    If you did not request this, please ignore this email.
                </p>
                <p style="margin:0;font-size:15px;line-height:1.6;">
                    Regards,<br>
                    Modlus Team
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}

function buildOtpTextTemplate(string $otp): string
{
    return "Hello,\n\n"
        . "Thank you for signing up with Modlus CRM.\n\n"
        . "Your OTP is {$otp}. Valid for 5 minutes.\n\n"
        . "If you did not request this, please ignore this email.\n\n"
        . "Regards,\n"
        . "Modlus Team";
}

function sendOtpEmail(string $toEmail, string $otp): bool
{
    $mail = new PHPMailer(true);

    try {
        writeMailLog(date('Y-m-d H:i:s') . ' - Attempting mail to: ' . $toEmail);

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = GMAIL_USERNAME;
        $mail->Password = GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function ($str, $level): void {
            writeMailLog(date('Y-m-d H:i:s') . ' - DEBUG: ' . trim((string) $str));
        };

        $mail->setFrom(GMAIL_USERNAME, 'Modlus CRM');
        $mail->addReplyTo(GMAIL_USERNAME, 'Support');
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Verify your email - OTP Code';
        $mail->Body = buildOtpHtmlTemplate($otp);
        $mail->AltBody = buildOtpTextTemplate($otp);

        if ($mail->send()) {
            writeMailLog(date('Y-m-d H:i:s') . ' - SUCCESS: Mail sent to ' . $toEmail);
            return true;
        }

        writeMailLog(date('Y-m-d H:i:s') . ' - ERROR: ' . $mail->ErrorInfo);
        return false;
    } catch (Exception $e) {
        writeMailLog(date('Y-m-d H:i:s') . ' - EXCEPTION: ' . $e->getMessage());
        return false;
    }
}
