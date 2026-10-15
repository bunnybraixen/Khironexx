<?php
// Script para crear/actualizar esquema desde PHP usando AppSetupUtility.
// Uso (CLI):
// php installer/scripts/setup_db.php --db-name=gestor_postulaciones --admin-name="Admin" --admin-email=admin@local --admin-pass=secret --save-config=1

$scriptsDir = __DIR__; // c:\xampp\htdocs\khironex\installer\scripts
$installerDir = dirname($scriptsDir); // c:\xampp\htdocs\khironex\installer
$projectRoot = dirname($installerDir); // c:\xampp\htdocs\khironex
$utilPath = $installerDir . '/Util/AppSetupUtility.php';
$configPath = $projectRoot . '/config/khironex.php';

if (!file_exists($utilPath)) {
    echo "Falta AppSetupUtility en: $utilPath\n";
    exit(1);
}

require_once $utilPath;

$rawConfig = file_exists($configPath) ? include $configPath : [];

$opts = [];
foreach ($argv as $arg) {
    if (strpos($arg, '--') === 0 && strpos($arg, '=') !== false) {
        $p = substr($arg, 2);
        [$k, $v] = explode('=', $p, 2);
        $opts[$k] = $v;
    }
}

$dbHost = $opts['db-host'] ?? ($rawConfig['db']['host'] ?? 'localhost');
$dbPort = $opts['db-port'] ?? ($rawConfig['db']['port'] ?? '3306');
$dbUser = $opts['db-user'] ?? ($rawConfig['db']['user'] ?? 'root');
$dbPass = $opts['db-pass'] ?? ($rawConfig['db']['pass'] ?? '');
$dbName = $opts['db-name'] ?? ($rawConfig['db']['name'] ?? 'khironex');
$saveConfig = isset($opts['save-config']) && in_array($opts['save-config'], ['1', 'true', 'yes'], true);

echo "Conectando a MySQL en $dbHost:$dbPort como $dbUser\n";
$mysqli = new mysqli($dbHost, $dbUser, $dbPass, '', (int)$dbPort);

if ($mysqli->connect_errno) {
    echo "Error de conexion: " . $mysqli->connect_error . "\n";
    exit(1);
}

// Crear base si no existe.
$createDbSql = "CREATE DATABASE IF NOT EXISTS `" . $mysqli->real_escape_string($dbName) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
if (!$mysqli->query($createDbSql)) {
    echo "No se pudo crear la base de datos: " . $mysqli->error . "\n";
    exit(1);
}

if (!$mysqli->select_db($dbName)) {
    echo "No se pudo seleccionar la base de datos $dbName: " . $mysqli->error . "\n";
    exit(1);
}

echo "Ejecutando esquema con AppSetupUtility::runSchema() en base $dbName...\n";
$err = AppSetupUtility::runSchema($mysqli);
if ($err !== null) {
    echo "Error al crear esquema: $err\n";
    exit(1);
}

echo "Esquema aplicado correctamente.\n";

// Crear/actualizar admin si se pasa por parametro.
if (!empty($opts['admin-email']) && !empty($opts['admin-pass'])) {
    $adminName = $opts['admin-name'] ?? 'Administrador';
    $adminEmail = $opts['admin-email'];
    $adminPass = $opts['admin-pass'];

    echo "Creando/actualizando admin $adminEmail...\n";
    $messages = [];
    $errors = [];
    $userId = AppSetupUtility::createOrUpdateAdmin($mysqli, $adminName, $adminEmail, $adminPass, $messages, $errors);
    foreach ($messages as $m) {
        echo "- $m\n";
    }
    foreach ($errors as $e) {
        echo "! $e\n";
    }
    if ($userId) {
        echo "Admin creado/actualizado (id=$userId).\n";
    }
}

// Guardar configuracion si se solicita.
if ($saveConfig) {
    $newConfig = $rawConfig;
    if (!isset($newConfig['db']) || !is_array($newConfig['db'])) {
        $newConfig['db'] = [];
    }
    $newConfig['db']['host'] = $dbHost;
    $newConfig['db']['port'] = (string)$dbPort;
    $newConfig['db']['name'] = $dbName;
    $newConfig['db']['user'] = $dbUser;
    $newConfig['db']['pass'] = $dbPass;

    if (AppSetupUtility::writeConfig($configPath, $newConfig)) {
        echo "Configuracion guardada en $configPath\n";
    } else {
        echo "No se pudo escribir la configuracion en $configPath\n";
    }
}

echo "Listo. Si la aplicacion debe usar esta base, actualiza config/khironex.php o ejecuta con --save-config=1.\n";

return 0;

