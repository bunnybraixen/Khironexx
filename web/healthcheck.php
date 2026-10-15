<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

function out(string $label, $value): void
{
    if (is_bool($value)) {
        $value = $value ? 'true' : 'false';
    } elseif ($value === null) {
        $value = 'null';
    } elseif (is_array($value)) {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    echo str_pad($label, 28, ' ', STR_PAD_RIGHT) . ': ' . (string)$value . PHP_EOL;
}

function maskSecret(string $value): string
{
    $len = strlen($value);
    if ($len <= 2) {
        return str_repeat('*', $len);
    }
    return substr($value, 0, 1) . str_repeat('*', max(0, $len - 2)) . substr($value, -1);
}

function checkTableExists(mysqli $db, string $table): bool
{
    $safe = $db->real_escape_string($table);
    $res = $db->query("SHOW TABLES LIKE '{$safe}'");
    return $res instanceof mysqli_result && (bool)$res->fetch_row();
}

echo "Khironex healthcheck" . PHP_EOL;
echo str_repeat('=', 72) . PHP_EOL;

out('timestamp', date('c'));
out('php_version', PHP_VERSION);
out('sapi', PHP_SAPI);
out('loaded_mysqli', extension_loaded('mysqli'));
out('loaded_mbstring', extension_loaded('mbstring'));
out('loaded_json', extension_loaded('json'));

$root = dirname(__DIR__);
$configPath = $root . '/config/khironex.php';
out('config_exists', is_file($configPath));

if (!is_file($configPath)) {
    out('fatal', 'No existe config/khironex.php');
    exit;
}

$config = require $configPath;
if (!is_array($config)) {
    out('fatal', 'config/khironex.php no devuelve un array');
    exit;
}

$dbConf = is_array($config['db'] ?? null) ? $config['db'] : [];
$host = (string)($dbConf['host'] ?? 'localhost');
$port = (int)($dbConf['port'] ?? 3306);
$name = (string)($dbConf['name'] ?? '');
$user = (string)($dbConf['user'] ?? '');
$pass = (string)($dbConf['pass'] ?? '');

out('config.installed', (bool)($config['installed'] ?? false));
out('config.base_url', (string)($config['base_url'] ?? ''));
out('db.host', $host);
out('db.port', $port);
out('db.name', $name);
out('db.user', $user);
out('db.pass_masked', maskSecret($pass));

if (!extension_loaded('mysqli')) {
    out('fatal', 'Extension mysqli no cargada');
    exit;
}

mysqli_report(MYSQLI_REPORT_OFF);
$db = @new mysqli($host, $user, $pass, $name, $port);

if ($db->connect_error) {
    out('db_connect', 'FAIL');
    out('db_errno', $db->connect_errno);
    out('db_error', $db->connect_error);
    exit;
}

out('db_connect', 'OK');
$db->set_charset('utf8mb4');

$requiredTables = [
    'usuarios',
    'roles',
    'usuarios_roles',
    'practicantes',
    'postulantes',
    'capacitaciones',
    'relatores',
];

$missing = [];
foreach ($requiredTables as $table) {
    if (!checkTableExists($db, $table)) {
        $missing[] = $table;
    }
}
out('missing_tables', $missing);

$relatorCols = [];
$resCols = $db->query('SHOW COLUMNS FROM relatores');
if ($resCols instanceof mysqli_result) {
    while ($row = $resCols->fetch_assoc()) {
        $relatorCols[] = $row['Field'] ?? '';
    }
}
out('relatores.columns', $relatorCols);

$usersCount = null;
$resCount = $db->query('SELECT COUNT(*) AS total FROM usuarios');
if ($resCount instanceof mysqli_result) {
    $row = $resCount->fetch_assoc();
    $usersCount = (int)($row['total'] ?? 0);
}
out('usuarios.count', $usersCount);

out('status', 'HEALTHCHECK_OK');
