<?php
session_start();

require_once __DIR__ . '/includes/config.php';

if (isset($_SESSION['userId'])) {
    header('Location: ' . BASE_URL . '/dashboard');
    exit();
}

header('Location: ' . BASE_URL . '/login');
exit();