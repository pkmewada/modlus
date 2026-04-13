<?php
require_once __DIR__ . '/auth-functions.php';

session_start();

if (empty($_SESSION['userId'])) {
    redirectTo('login');
}
