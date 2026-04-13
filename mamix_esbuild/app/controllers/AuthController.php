<?php

require_once __DIR__ . '/../../includes/auth-functions.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../../includes/sendOtp.php';

class AuthController
{
    private $userModel;
    private const OTP_VALID_MINUTES = 5;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function login()
    {
        session_start();
        $error = '';
        $emailValue = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $emailValue = getPost('email');
            $password = getPost('password');

            if ($emailValue === '' || $password === '') {
                $error = 'Please enter both email and password.';
            } else {
                $user = $this->userModel->getUserByEmail($emailValue);

                if ($user && password_verify($password, $user['password'])) {
                    if ($user['otp']) {
                        redirectTo('verifyotp?email=' . urlencode($emailValue));
                    } else {
                        session_regenerate_id(true);
                        $_SESSION['userId'] = $user['id'];
                        redirectTo('dashboard');
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
            }
        }

        // Include view
        include __DIR__ . '/../views/login.php';
    }

    public function signup()
    {
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $fullName = getPost('name');
            $email = getPost('email');
            $password = getPost('password');
            $confirmPassword = getPost('confirmPassword');

            if ($fullName === '' || $email === '' || $password === '' || $confirmPassword === '') {
                $error = 'Please complete all required fields.';
            } elseif ($password !== $confirmPassword) {
                $error = 'Passwords do not match.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $otp = generateOtp();

                if ($this->userModel->createUser($fullName, $email, $passwordHash, $otp)) {
                    $otpExpiresAt = date('Y-m-d H:i:s', time() + (self::OTP_VALID_MINUTES * 60));
                    $this->userModel->updateOtp($email, $otp, $otpExpiresAt);
                    $isSent = sendOtpEmail($email, $otp);

                    if ($this->shouldReturnJson()) {
                        if ($isSent) {
                            $this->sendJsonResponse(true, 'OTP sent successfully', ['email' => $email]);
                        }

                        $this->sendJsonResponse(false, 'Unable to send OTP. Try again later.', ['email' => $email, 'otp' => $otp]);
                    }

                    redirectTo('verifyotp?email=' . urlencode($email) . '&email_sent=' . ($isSent ? '1' : '0'));
                } else {
                    $error = 'Unable to register. Please try again.';

                    if ($this->shouldReturnJson()) {
                        $this->sendJsonResponse(false, $error);
                    }
                }
            }
        }

        // Include view
        include __DIR__ . '/../views/signup.php';
    }

    public function verifyOtp()
    {
        $error = '';
        $successMessage = '';
        $emailValue = getQuery('email');

        if ($emailValue === '' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            redirectTo('signup');
        }

        if (!isDevMode() && $_SERVER['REQUEST_METHOD'] === 'GET' && getQuery('email_sent') === '0') {
            $error = 'Failed to send OTP email. Please try resending or contact support.';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $emailValue = getPost('email', $emailValue);
            $action = getPost('action', 'verify');

            if ($action === 'resend') {
                if ($emailValue === '') {
                    $error = 'Unable to resend OTP because the email address is missing.';
                } else {
                    $otp = generateOtp();
                    $otpExpiresAt = date('Y-m-d H:i:s', time() + (self::OTP_VALID_MINUTES * 60));

                    if ($this->userModel->updateOtp($emailValue, $otp, $otpExpiresAt)) {
                        $isSent = sendOtpEmail($emailValue, $otp);
                        $successMessage = $isSent
                            ? 'A new 4-digit OTP has been sent to your email address.'
                            : 'OTP regenerated, but email delivery failed. Use this debug OTP: ' . $otp;
                    } else {
                        $error = 'Unable to resend OTP right now. Please try again.';
                    }
                }
            } else {
                $otpCode = getPost('otp1') . getPost('otp2') . getPost('otp3') . getPost('otp4');

                if ($emailValue === '' || $otpCode === '') {
                    $error = 'Please enter the verification code and make sure the email is included in the URL.';
                } elseif (!preg_match('/^\d{4}$/', $otpCode)) {
                    $error = 'Invalid OTP format. Please enter a 4-digit OTP.';
                } else {
                    if (isDevMode()) {
                        // DEV MODE OTP BYPASS: ignore actual OTP matching and allow verification
                        // when any 4-digit OTP is submitted.
                        if ($this->userModel->markUserVerified($emailValue)) {
                            redirectTo('login');
                        } else {
                            $error = 'Unable to verify this account right now. Please try again.';
                        }
                    } elseif ($this->userModel->verifyOtp($emailValue, $otpCode)) {
                        redirectTo('login');
                    } else {
                        $error = 'Invalid verification code. Please try again.';
                    }
                }
            }
        }

        // Include view
        include __DIR__ . '/../views/verifyotp.php';
    }

    private function shouldReturnJson(): bool
    {
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $format = getPost('format', getQuery('format'));

        return stripos($acceptHeader, 'application/json') !== false
            || strtolower($requestedWith) === 'xmlhttprequest'
            || strtolower((string) $format) === 'json';
    }

    private function sendJsonResponse(bool $success, string $message, array $extra = []): void
    {
        header('Content-Type: application/json');
        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message,
        ], $extra));
        exit();
    }
}
