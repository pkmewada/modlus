<?php
function getDbConnection()
{
    static $connection = null;

    if ($connection === null) {
        $host = '127.0.0.1';
        $user = 'root';
        $pass = '';
        $db = 'modlus';

        $connection = mysqli_connect($host, $user, $pass, $db, 3307);

        if (!$connection) {
            die('Connection failed: ' . mysqli_connect_error());
        }
    }

    return $connection;
}

$con = getDbConnection();

define('GMAIL_USERNAME', 'pkmewada@gmail.com');
define('GMAIL_APP_PASSWORD', 'qyrnhxdsvdztrwyn');

?>