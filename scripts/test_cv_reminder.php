<?php

/**
 * Script de prueba para verificar la automatización de recordatorio de CV
 * 
 * Uso: 
 *   php scripts/test_cv_reminder.php
 */

echo "======================================\n";
echo "TEST: Automatización de Recordatorio de CV\n";
echo "======================================\n\n";

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/app/bootstrap.php';
require_once $projectRoot . '/app/Support/web_helpers.php';

$config = $GLOBALS['khironex_config'] ?? [];
$dbConfig = $config['db'] ?? [];

echo "[1/6] Intentando conectar a la base de datos...\n";
$db = new mysqli(
    $dbConfig['host'] ?? 'localhost',
    $dbConfig['user'] ?? 'root',
    $dbConfig['pass'] ?? '',
    $dbConfig['name'] ?? 'khironex',
    (int)($dbConfig['port'] ?? 3306)
);

if ($db->connect_error) {
    echo "❌ ERROR de conexión: " . $db->connect_error . "\n";
    exit(1);
}
echo "✓ Conexión exitosa\n";
$db->set_charset("utf8mb4");

echo "\n[2/6] Verificando tabla 'relatores'...\n";
$result = $db->query("SHOW TABLES LIKE 'relatores'");
if (!$result || $result->num_rows === 0) {
    echo "❌ Tabla 'relatores' no existe\n";
    exit(1);
}
echo "✓ Tabla 'relatores' existe\n";

echo "\n[3/6] Verificando columnas requeridas...\n";
$columns = db_table_columns($db, 'relatores');
echo "Columnas encontradas: " . count($columns) . "\n";

$requiredColumns = ['email', 'cv_path'];
foreach ($requiredColumns as $col) {
    if (in_array($col, $columns, true)) {
        echo "  ✓ " . $col . "\n";
    } else {
        echo "  ❌ " . $col . " (FALTA)\n";
    }
}

if (!in_array('last_cv_reminder_sent', $columns, true)) {
    echo "  ⚠ last_cv_reminder_sent (NECESARIA PARA AUTOMATIZACIÓN)\n";
    echo "    Ejecuta: ALTER TABLE relatores ADD COLUMN last_cv_reminder_sent DATETIME DEFAULT NULL;\n";
} else {
    echo "  ✓ last_cv_reminder_sent\n";
}

echo "\n[4/6] Verificando relatores en el sistema...\n";
$relatorIdCol = relator_id_column($db);
$relatorNameCol = relator_name_column($db);
echo "  Columna de ID detectada: " . $relatorIdCol . "\n";
echo "  Columna de nombre detectada: " . $relatorNameCol . "\n";

$count = $db->query("SELECT COUNT(*) as total FROM relatores");
$totalRelatores = $count->fetch_assoc()['total'];
echo "  Total de relatores: " . $totalRelatores . "\n";

if ($totalRelatores === 0) {
    echo "  ⚠ No hay relatores en el sistema\n";
}

echo "\n[5/6] Verificando relatores sin recordatorio...\n";
$query = "SELECT " . $relatorIdCol . ", " . $relatorNameCol . ", email 
          FROM relatores 
          WHERE email IS NOT NULL AND email != ''
            AND last_cv_reminder_sent IS NULL
          LIMIT 5";
$result = $db->query($query);
if ($result) {
    $withoutReminder = $result->num_rows;
    echo "  Relatores sin recordatorio previo: " . $withoutReminder . "\n";
    echo "  Primeros 5:\n";
    while ($row = $result->fetch_assoc()) {
        echo "    - " . htmlspecialchars($row[$relatorNameCol]) . " (" . htmlspecialchars($row['email']) . ")\n";
    }
}

echo "\n[6/6] Verificando configuración de PHP mail()...\n";
if (function_exists('mail')) {
    echo "  ✓ Función mail() disponible\n";
} else {
    echo "  ❌ Función mail() NO disponible\n";
}

// Verificar php.ini
$mailFunction = ini_get('sendmail_path') ?: ini_get('SMTP');
if ($mailFunction) {
    echo "  ✓ Configuración de mail: " . substr($mailFunction, 0, 50) . "...\n";
} else {
    echo "  ⚠ No hay configuración de SMTP detectada. Verifica php.ini\n";
}

echo "\n======================================\n";
echo "TEST COMPLETADO\n";
echo "======================================\n\n";

echo "Resumen:\n";
if ($totalRelatores > 0 && $withoutReminder > 0) {
    echo "✓ Sistema listo para enviar recordatorios\n";
    echo "\nPróximos pasos:\n";
    echo "1. Ejecuta: php scripts/send_relator_cv_reminder.php\n";
    echo "2. O configura la automatización según: AUTOMATIZACION_RECORDATORIO_CV.md\n";
} elseif ($totalRelatores === 0) {
    echo "⚠ Agrega relatores al sistema antes de ejecutar la automatización\n";
} else {
    echo "ℹ Todos los relatores ya han recibido recordatorios\n";
    echo "Próximo envío programado en 1 año desde el último recordatorio\n";
}

$db->close();
