<?php

// Override for any environment (production or otherwise): if set, this wins
// over every other rule below. Trailing slashes are normalized off so
// BASE_URL . '/uploads/...' never produces a double slash.
$configuredBaseUrl = trim((string)getenv('MODLUS_BASE_URL'));

if ($configuredBaseUrl !== '') {
    define('BASE_URL', rtrim($configuredBaseUrl, '/'));
} elseif (PHP_SAPI === 'cli') {
    // CLI/cron (e.g. cron/instagramScheduler.php) has no HTTP_HOST and no
    // web-facing DOCUMENT_ROOT — the dynamic logic below would otherwise
    // derive BASE_URL from the server's filesystem layout (e.g.
    // http://localhost/domains/modlus.in/public_html), which Meta's Graph
    // API cannot fetch media from. Fall back to the known production
    // domain instead of guessing from server-local paths.
    define('BASE_URL', 'https://modlus.in');
} else {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $projectRoot = realpath(dirname(__DIR__));
    $basePath = '';

    if ($documentRoot !== false && $projectRoot !== false && strpos($projectRoot, $documentRoot) === 0) {
        $relativePath = trim(str_replace('\\', '/', substr($projectRoot, strlen($documentRoot))), '/');
        $basePath = $relativePath === '' ? '' : '/' . $relativePath;
    }

    define('BASE_URL', $protocol . '://' . $host . $basePath);
}

// ✅ Separate asset URL
define('ASSET_URL', BASE_URL . '/dist');

define('UPLOAD_URL', BASE_URL . '/uploads');


if (!defined('SITE_URL')) {

    $protocol =
        (
            !empty($_SERVER['HTTPS']) &&
            $_SERVER['HTTPS'] !== 'off'
        )
        ? 'https'
        : 'http';

    $host =
        $_SERVER['HTTP_HOST'] ?? 'localhost';

    define(
        'SITE_URL',
        BASE_URL
    );
}

if (!defined('ENCRYPTION_KEY')) {
    // Override in production via the MODLUS_ENCRYPTION_KEY environment variable.
    define('ENCRYPTION_KEY', getenv('MODLUS_ENCRYPTION_KEY') ?: 'default_key_123');
}
