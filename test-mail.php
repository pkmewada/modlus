<?php

/*
|--------------------------------------------------------------------------
| TEST MAIL FILE - Onboarding Form Email Test
|--------------------------------------------------------------------------
|
| File Location:
| /test-mail.php
|
| Purpose:
| Verify:
| - sendOnboardingFormEmail() function working
| - mailer.php working
| - PHPMailer working
| - SMTP working
| - log writing working
| - sendLoggedMail() flow working
|
| Usage:
| 1. Update the test email address below
| 2. Run: php test-mail.php
| 3. OR access via browser: /test-mail.php
|
*/

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php';

/*
|--------------------------------------------------------------------------
| Helper - Debug Log
|--------------------------------------------------------------------------
*/

function debugLog($message)
{
    $logDir = __DIR__ . '/logs';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $logFile = $logDir . '/mail.log';
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;

    error_log($line, 3, $logFile);
}

/*
|--------------------------------------------------------------------------
| Start Debug
|--------------------------------------------------------------------------
*/

debugLog('======================================');
debugLog('TEST MAIL SCRIPT STARTED - Onboarding Form Email Test');

try {

    /*
    |--------------------------------------------------------------------------
    | Validate Mailer Functions
    |--------------------------------------------------------------------------
    */

    if (!function_exists('createMailer')) {
        debugLog('createMailer() NOT FOUND');
        die('<h2 style="color:red;">createMailer() not found</h2>');
    }

    if (!function_exists('sendLoggedMail')) {
        debugLog('sendLoggedMail() NOT FOUND');
        die('<h2 style="color:red;">sendLoggedMail() not found</h2>');
    }

    if (!function_exists('sendOnboardingFormEmail')) {
        debugLog('sendOnboardingFormEmail() NOT FOUND');
        die('<h2 style="color:red;">sendOnboardingFormEmail() not found</h2>');
    }

    debugLog('All mailer functions loaded successfully');

    /*
    |--------------------------------------------------------------------------
    | Test Data
    |--------------------------------------------------------------------------
    |
    | CHANGE THESE VALUES FOR TESTING
    |
    */

    // CHANGE THIS EMAIL - Where test email will be sent
    $testEmail = 'praveen.mqlus@gmail.com';
    
    // Test client data
    $testLeadId = 1;
    $testFullName = 'Praveen Test Client';
    $testClientCode = 'TEST001';
    $testFormLink = 'https://modlus.in/client-onboarding-form?token=test_token_123456';

    debugLog('Test Configuration:');
    debugLog('  Receiver Email: ' . $testEmail);
    debugLog('  Lead ID: ' . $testLeadId);
    debugLog('  Client Name: ' . $testFullName);
    debugLog('  Client Code: ' . $testClientCode);
    debugLog('  Form Link: ' . $testFormLink);

    /*
    |--------------------------------------------------------------------------
    | Test 1: Direct createMailer Test
    |--------------------------------------------------------------------------
    */

    debugLog('--- TEST 1: Direct Mailer Creation ---');

    try {
        $mail = createMailer('MQlus Test');
        
        if ($mail) {
            debugLog('✓ createMailer() successful');
            $mailerTestResult = '✅ createMailer() - SUCCESS';
        } else {
            debugLog('✗ createMailer() returned null');
            $mailerTestResult = '❌ createMailer() - FAILED';
        }
    } catch (Throwable $e) {
        debugLog('✗ createMailer() threw exception: ' . $e->getMessage());
        $mailerTestResult = '❌ createMailer() - EXCEPTION';
    }

    /*
    |--------------------------------------------------------------------------
    | Test 2: sendOnboardingFormEmail Function Test
    |--------------------------------------------------------------------------
    */

    debugLog('--- TEST 2: sendOnboardingFormEmail ---');

    $sendResult = false;
    $sendError = null;

    try {
        debugLog('Calling sendOnboardingFormEmail...');
        
        $sendResult = sendOnboardingFormEmail(
            $testLeadId,
            $testEmail,
            $testFullName,
            $testFormLink,
            $testClientCode
        );

        if ($sendResult) {
            debugLog('✓ sendOnboardingFormEmail() returned TRUE');
            $functionTestResult = '✅ sendOnboardingFormEmail() - SUCCESS';
        } else {
            debugLog('✗ sendOnboardingFormEmail() returned FALSE');
            $functionTestResult = '❌ sendOnboardingFormEmail() - FAILED';
        }

    } catch (Throwable $e) {
        debugLog('✗ sendOnboardingFormEmail() threw exception: ' . $e->getMessage());
        debugLog('  File: ' . $e->getFile() . ' Line: ' . $e->getLine());
        $sendError = $e->getMessage();
        $functionTestResult = '❌ sendOnboardingFormEmail() - EXCEPTION';
    }

    /*
    |--------------------------------------------------------------------------
    | Test 3: sendLoggedMail Test (if function exists)
    |--------------------------------------------------------------------------
    */

    debugLog('--- TEST 3: sendLoggedMail Test ---');

    $loggedMailTestResult = '⚠️ SKIPPED';

    try {
        // Test if sendLoggedMail works with a simple callback
        $testSubject = 'SendLoggedMail Test - ' . date('Y-m-d H:i:s');
        
        debugLog('Testing sendLoggedMail with simple callback...');
        
        // This is a simpler test - just to see if sendLoggedMail works
        $loggedMailResult = sendLoggedMail(
            'test',
            $testLeadId,
            'testEvent',
            $testEmail,
            $testFullName,
            $testSubject,
            function() use ($testEmail, $testFullName, $testSubject) {
                $mail = createMailer('MQlus Test');
                $mail->addAddress($testEmail);
                $mail->Subject = $testSubject;
                $mail->Body = "<h2>SendLoggedMail Test</h2><p>This is a test of sendLoggedMail function.</p>";
                $mail->AltBody = "SendLoggedMail Test";
                return $mail->send();
            }
        );
        
        if ($loggedMailResult) {
            debugLog('✓ sendLoggedMail() returned TRUE');
            $loggedMailTestResult = '✅ sendLoggedMail() - SUCCESS';
        } else {
            debugLog('✗ sendLoggedMail() returned FALSE');
            $loggedMailTestResult = '❌ sendLoggedMail() - FAILED';
        }
    } catch (Throwable $e) {
        debugLog('✗ sendLoggedMail() test failed: ' . $e->getMessage());
        $loggedMailTestResult = '❌ sendLoggedMail() - EXCEPTION';
    }

    /*
    |--------------------------------------------------------------------------
    | Results
    |--------------------------------------------------------------------------
    */

    // Determine overall status
    $overallStatus = '⚠️ PARTIAL';
    $statusColor = '#f59e0b'; // yellow
    
    if ($sendResult && $mailerTestResult === '✅ createMailer() - SUCCESS') {
        $overallStatus = '✅ SUCCESS';
        $statusColor = '#16a34a'; // green
    } elseif ($sendResult) {
        $overallStatus = '✅ PARTIAL SUCCESS (mail sent but mailer test failed)';
        $statusColor = '#f59e0b'; // yellow
    } elseif ($mailerTestResult === '✅ createMailer() - SUCCESS') {
        $overallStatus = '❌ MAILER OK BUT FUNCTION FAILED';
        $statusColor = '#dc2626'; // red
    }

    debugLog('======================================');
    debugLog('OVERALL STATUS: ' . $overallStatus);
    debugLog('======================================');

    /*
    |--------------------------------------------------------------------------
    | HTML Output
    |--------------------------------------------------------------------------
    */

    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Mailer Test - Onboarding Form</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f7fb; padding: 40px; }
            .container { max-width: 900px; margin: 0 auto; background: #fff; border-radius: 14px; padding: 40px; border: 1px solid #e5e7eb; }
            h1 { margin-top: 0; color: #111827; }
            .status-box { padding: 20px; border-radius: 8px; margin: 20px 0; border: 2px solid <?php echo $statusColor; ?>; background: <?php echo $statusColor . '10'; ?>; }
            .status-box .status-label { font-size: 18px; font-weight: bold; color: <?php echo $statusColor; ?>; }
            .test-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 20px 0; }
            .test-item { padding: 15px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; }
            .test-item.pass { border-color: #16a34a; background: #f0fdf4; }
            .test-item.fail { border-color: #dc2626; background: #fef2f2; }
            .test-item.warning { border-color: #f59e0b; background: #fffbeb; }
            .test-label { font-weight: 600; color: #374151; }
            .test-result { margin-top: 5px; font-family: monospace; }
            .test-result.success { color: #16a34a; }
            .test-result.fail { color: #dc2626; }
            .test-result.warning { color: #f59e0b; }
            .details { margin-top: 20px; padding: 15px; background: #f8fafc; border-radius: 8px; border: 1px solid #e5e7eb; }
            .details code { background: #e5e7eb; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
            .error-message { color: #dc2626; background: #fef2f2; padding: 10px; border-radius: 6px; border: 1px solid #fecaca; margin-top: 10px; }
            .log-link { margin-top: 20px; }
            .log-link a { color: #2563eb; text-decoration: none; }
            .log-link a:hover { text-decoration: underline; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>📧 Onboarding Form Email Test</h1>
            
            <div class="status-box">
                <div class="status-label"><?php echo $overallStatus; ?></div>
                <p style="margin-top: 10px; color: #4b5563;">
                    Test completed at: <?php echo date('Y-m-d H:i:s'); ?>
                </p>
            </div>

            <div class="test-grid">
                <div class="test-item <?php echo strpos($mailerTestResult, '✅') !== false ? 'pass' : (strpos($mailerTestResult, '❌') !== false ? 'fail' : 'warning'); ?>">
                    <div class="test-label">1. createMailer()</div>
                    <div class="test-result <?php echo strpos($mailerTestResult, '✅') !== false ? 'success' : (strpos($mailerTestResult, '❌') !== false ? 'fail' : 'warning'); ?>">
                        <?php echo $mailerTestResult; ?>
                    </div>
                </div>

                <div class="test-item <?php echo strpos($functionTestResult, '✅') !== false ? 'pass' : (strpos($functionTestResult, '❌') !== false ? 'fail' : 'warning'); ?>">
                    <div class="test-label">2. sendOnboardingFormEmail()</div>
                    <div class="test-result <?php echo strpos($functionTestResult, '✅') !== false ? 'success' : (strpos($functionTestResult, '❌') !== false ? 'fail' : 'warning'); ?>">
                        <?php echo $functionTestResult; ?>
                    </div>
                    <?php if ($sendError): ?>
                        <div class="error-message">
                            <strong>Error:</strong> <?php echo htmlspecialchars($sendError); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="test-item <?php echo strpos($loggedMailTestResult, '✅') !== false ? 'pass' : (strpos($loggedMailTestResult, '❌') !== false ? 'fail' : 'warning'); ?>" style="grid-column: span 2;">
                    <div class="test-label">3. sendLoggedMail()</div>
                    <div class="test-result <?php echo strpos($loggedMailTestResult, '✅') !== false ? 'success' : (strpos($loggedMailTestResult, '❌') !== false ? 'fail' : 'warning'); ?>">
                        <?php echo $loggedMailTestResult; ?>
                    </div>
                </div>
            </div>

            <div class="details">
                <h3>📋 Test Details</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb; font-weight: 600;">Receiver Email</td>
                        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;"><code><?php echo htmlspecialchars($testEmail); ?></code></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb; font-weight: 600;">Client Name</td>
                        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;"><?php echo htmlspecialchars($testFullName); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb; font-weight: 600;">Client Code</td>
                        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;"><code><?php echo htmlspecialchars($testClientCode); ?></code></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; font-weight: 600;">Form Link</td>
                        <td style="padding: 8px;"><code style="word-break: break-all;"><?php echo htmlspecialchars($testFormLink); ?></code></td>
                    </tr>
                </table>
            </div>

            <?php if ($sendResult): ?>
                <div style="margin-top: 20px; padding: 15px; background: #f0fdf4; border-radius: 8px; border: 1px solid #bbf7d0;">
                    <h3 style="color: #16a34a; margin: 0;">✅ Email Sent Successfully</h3>
                    <p style="margin-top: 10px; color: #4b5563;">
                        Check the inbox/spam folder of <code><?php echo htmlspecialchars($testEmail); ?></code>
                    </p>
                </div>
            <?php else: ?>
                <div style="margin-top: 20px; padding: 15px; background: #fef2f2; border-radius: 8px; border: 1px solid #fecaca;">
                    <h3 style="color: #dc2626; margin: 0;">❌ Email Sending Failed</h3>
                    <p style="margin-top: 10px; color: #4b5563;">
                        Check the logs below for more details.
                    </p>
                </div>
            <?php endif; ?>

            <div class="log-link">
                <h3>📁 Log Files</h3>
                <p>
                    <a href="logs/mail.log" target="_blank">📄 View mail.log</a>
                </p>
                <p>
                    <span style="color: #6b7280; font-size: 14px;">
                        Log file path: <code><?php echo __DIR__; ?>/logs/mail.log</code>
                    </span>
                </p>
                <p style="margin-top: 10px; color: #6b7280; font-size: 14px;">
                    <strong>Tip:</strong> Run <code>tail -f logs/mail.log</code> in terminal to watch logs in real-time.
                </p>
            </div>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 14px; color: #6b7280;">
                <p>
                    <strong>Debug Information:</strong><br>
                    PHP Version: <?php echo phpversion(); ?><br>
                    Date: <?php echo date('Y-m-d H:i:s'); ?><br>
                    Script: <?php echo __FILE__; ?>
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php

} catch (Throwable $e) {

    debugLog('FATAL ERROR: ' . $e->getMessage());
    debugLog('FILE: ' . $e->getFile());
    debugLog('LINE: ' . $e->getLine());

    echo '
        <!DOCTYPE html>
        <html>
        <head>
            <title>Mailer Test - Fatal Error</title>
            <style>
                body { font-family: Arial, sans-serif; background: #f5f7fb; padding: 40px; }
                .container { max-width: 800px; margin: 0 auto; background: #fff; border-radius: 14px; padding: 40px; border: 1px solid #e5e7eb; }
                .error-box { padding: 20px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; }
                .error-box h2 { color: #dc2626; margin-top: 0; }
                pre { background: #f8fafc; padding: 15px; border-radius: 8px; overflow: auto; border: 1px solid #e5e7eb; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="error-box">
                    <h2>💥 Fatal Error</h2>
                    <p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>
                    <p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>
                    <p><strong>Line:</strong> ' . htmlspecialchars($e->getLine()) . '</p>
                    <pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>
                </div>
                <p style="margin-top: 20px;">
                    Check <code>logs/mail.log</code> for more details.
                </p>
            </div>
        </body>
        </html>
    ';
}

debugLog('TEST MAIL SCRIPT COMPLETED');
debugLog('======================================');