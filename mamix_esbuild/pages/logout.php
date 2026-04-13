<?php
require_once __DIR__ . '/../includes/auth-functions.php';
session_start();

destroySession();
redirectTo('login');
