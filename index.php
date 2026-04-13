<?php
session_start();

if (isset($_SESSION['userId'])) {
    header('Location: /mamix/dashboard');
    exit();
}

header('Location: /mamix/login');
exit();
