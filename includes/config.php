<?php

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
