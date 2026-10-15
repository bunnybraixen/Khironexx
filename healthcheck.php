<?php

declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

echo "HEALTHCHECK ROOT OK\n";
echo "php_version=" . PHP_VERSION . "\n";
echo "sapi=" . PHP_SAPI . "\n";
echo "document_root=" . ($_SERVER['DOCUMENT_ROOT'] ?? '') . "\n";
echo "script_filename=" . ($_SERVER['SCRIPT_FILENAME'] ?? '') . "\n";
echo "cwd=" . getcwd() . "\n";

$configPath = __DIR__ . '/config/khironex.php';
echo "config_exists=" . (is_file($configPath) ? 'yes' : 'no') . "\n";

if (is_file($configPath)) {
    $cfg = require $configPath;
    $db = is_array($cfg['db'] ?? null) ? $cfg['db'] : [];
    $host = (string)($db['host'] ?? '');
    $port = (int)($db['port'] ?? 3306);
    $name = (string)($db['name'] ?? '');
    $user = (string)($db['user'] ?? '');

    echo "db_host=" . $host . "\n";
    echo "db_port=" . $port . "\n";
    echo "db_name=" . $name . "\n";
    echo "db_user=" . $user . "\n";

    if (extension_loaded('mysqli')) {
        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = @new mysqli($host, $user, (string)($db['pass'] ?? ''), $name, $port);
        if ($conn->connect_error) {
            echo "db_connect=FAIL\n";
            echo "db_errno=" . $conn->connect_errno . "\n";
            echo "db_error=" . $conn->connect_error . "\n";
        } else {
            echo "db_connect=OK\n";
            $conn->set_charset('utf8mb4');
            $res = $conn->query('SELECT COUNT(*) AS total FROM usuarios');
            if ($res instanceof mysqli_result) {
                $row = $res->fetch_assoc();
                echo "usuarios_count=" . (int)($row['total'] ?? 0) . "\n";
            }
        }
    } else {
        echo "db_connect=SKIP (mysqli missing)\n";
    }
}
