<?php
require_once __DIR__ . '/basic-config.php';


if (!function_exists('getDbConnection')) {
    function getDbConnection()
    {
        static $connection = null;

        if ($connection === null) {
            $serverHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
            $isLocalHost = preg_match('/^(localhost|127\.0\.0\.1|\[::1\]|10\.\d+\.\d+\.\d+|172\.(1[6-9]|2\d|3[01])\.\d+\.\d+|192\.168\.\d+\.\d+)(?::\d+)?$/', $serverHost) === 1;
            $normalizedDir = str_replace('\\', '/', __DIR__);
            $isLocalPath = preg_match('#/(xampp|wamp64)/(htdocs|www)/#i', $normalizedDir) === 1;
            $isLocalEnvironment = $isLocalHost || ($serverHost === '' && $isLocalPath);

            $defaults = $isLocalEnvironment
                ? [
                    'host' => 'localhost',
                    'user' => 'root',
                    'pass' => '',
                    'db' => 'modlus',
                    'port' => 3306,
                ]
                : [
                    'host' => 'localhost',
                    'user' => 'u438562206_modlus',
                    'pass' => 'u438562206_Modlus',
                    'db' => 'u438562206_modlus',
                    'port' => 3306,
                ];

            $host = getenv('MODLUS_DB_HOST') ?: $defaults['host'];
            $user = getenv('MODLUS_DB_USER') ?: $defaults['user'];
            $pass = getenv('MODLUS_DB_PASS') !== false ? getenv('MODLUS_DB_PASS') : $defaults['pass'];
            $db = getenv('MODLUS_DB_NAME') ?: $defaults['db'];
            $port = (int)(getenv('MODLUS_DB_PORT') ?: $defaults['port']);
            $ports = [$port];

            if ($isLocalEnvironment) {
                $ports = [3306, $port];
            }

            mysqli_report(MYSQLI_REPORT_OFF);

            foreach (array_unique($ports) as $connectionPort) {
                $connection = @mysqli_connect($host, $user, $pass, $db, (int)$connectionPort);
                if ($connection) {
                    break;
                }
            }

            if (!$connection) {
                die('Connection failed: ' . mysqli_connect_error());
            }
        }

        return $connection;
    }
}

$con = getDbConnection();
$config = getBasicConfig();

mysqli_query($con, "SET time_zone = '+05:30'");
date_default_timezone_set('Asia/Kolkata');
?>
