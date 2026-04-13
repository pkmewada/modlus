<?php

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
$path = preg_replace('#^/mamix(?:/mamix_esbuild)?#', '', $path);
$path = rtrim($path, '/');

if ($path === '') {
    $path = '/';
}

switch ($path) {
    case '/login':
        require_once __DIR__ . '/pages/login.php';
        break;

    case '/signup':
        require_once __DIR__ . '/pages/signup.php';
        break;

    case '/verifyotp':
        require_once __DIR__ . '/pages/verifyotp.php';
        break;

    case '/dashboard':
        require_once __DIR__ . '/pages/dashboard.php';
        break;

    case '/addlead':
        require_once __DIR__ . '/pages/addlead.php';
        break;

    case '/leads':
        require_once __DIR__ . '/pages/leads.php';
        break;

    case '/setup':
        require_once __DIR__ . '/pages/setup.php';
        break;

    case '/logout':
        require_once __DIR__ . '/pages/logout.php';
        break;

    default:
        http_response_code(404);
        echo '404 Not Found: Route "' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '" is not configured.';
        break;
}
?>
