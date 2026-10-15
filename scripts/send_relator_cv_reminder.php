<?php

/**
 * Script de automación: Enviar recordatorio anual a relatores para actualizar CV
 * 
 * Uso:
 *   php scripts/send_relator_cv_reminder.php
 * 
 * Para configurar como tarea programada (cron en Linux):
 *   0 9 * * 0 cd /path/to/khironex && php scripts/send_relator_cv_reminder.php
 * 
 * Para Windows (Task Scheduler):
 *   Action: Start a program
 *   Program: c:\xampp\php\php.exe
 *   Arguments: -f "c:\xampp\htdocs\khironex\scripts\send_relator_cv_reminder.php"
 */

// Conectar a la base de datos
$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/app/bootstrap.php';
require_once $projectRoot . '/app/Support/web_helpers.php';

$config = $GLOBALS['khironex_config'] ?? [];
$dbConfig = $config['db'] ?? [];

$db = new mysqli(
    $dbConfig['host'] ?? 'localhost',
    $dbConfig['user'] ?? 'root',
    $dbConfig['pass'] ?? '',
    $dbConfig['name'] ?? 'khironex',
    (int)($dbConfig['port'] ?? 3306)
);

if ($db->connect_error) {
    echo "Error de conexión: " . $db->connect_error . "\n";
    exit(1);
}

$db->set_charset("utf8mb4");

// Configuración de correo
define('MAIL_FROM', 'noreply@khironex.local');
define('MAIL_FROM_NAME', 'Sistema Khironex');
define('SUBJECT', 'Recordatorio: Actualiza tu CV en el sistema');

echo "[" . date('Y-m-d H:i:s') . "] Iniciando envío de recordatorios de CV...\n";

// Verificar si la columna existe
$columns = db_table_columns($db, 'relatores');
if (!in_array('last_cv_reminder_sent', $columns, true)) {
    echo "[" . date('Y-m-d H:i:s') . "] ADVERTENCIA: Columna 'last_cv_reminder_sent' no existe en la tabla 'relatores'.\n";
    echo "Ejecuta este SQL para crearla:\n";
    echo "ALTER TABLE relatores ADD COLUMN last_cv_reminder_sent DATETIME DEFAULT NULL;\n";
    exit(1);
}

// Buscar relatores sin recordatorio o que hace más de 1 año no reciben uno
$oneYearAgo = date('Y-m-d H:i:s', strtotime('-1 year'));
$relatorIdCol = relator_id_column($db);
$relatorNameCol = relator_name_column($db);

$query = "SELECT " . $relatorIdCol . ", " . $relatorNameCol . ", email, cv_path
          FROM relatores
          WHERE email IS NOT NULL 
            AND email != ''
            AND (last_cv_reminder_sent IS NULL OR last_cv_reminder_sent < '" . $db->real_escape_string($oneYearAgo) . "')
          ORDER BY " . $relatorNameCol . " ASC";

$result = $db->query($query);

if (!$result) {
    echo "Error en la consulta: " . $db->error . "\n";
    exit(1);
}

$totalRelatores = $result->num_rows;
$sent = 0;
$failed = 0;

if ($totalRelatores === 0) {
    echo "[" . date('Y-m-d H:i:s') . "] No hay relatores para enviar recordatorios.\n";
    exit(0);
}

echo "[" . date('Y-m-d H:i:s') . "] Se encontraron $totalRelatores relator(es) para recordatorio.\n";

while ($row = $result->fetch_assoc()) {
    $relatorId = (int)$row[$relatorIdCol];
    $relatorName = htmlspecialchars((string)($row[$relatorNameCol] ?? ''));
    $email = trim((string)($row['email'] ?? ''));
    $cvPath = trim((string)($row['cv_path'] ?? ''));

    if ($email === '') {
        echo "[" . date('Y-m-d H:i:s') . "] SALTADO: $relatorName - sin email\n";
        $failed++;
        continue;
    }

    // Preparar contenido del correo
    $headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    $hasCv = !empty($cvPath);
    $cvStatus = $hasCv ? 'Sí, tienes CV registrado' : 'No tienes CV registrado en el sistema';
    $cvStatusColor = $hasCv ? '#28a745' : '#dc3545';

    $body = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; }
        .header { background-color: #007bff; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { padding: 20px; }
        .status { padding: 10px; margin: 15px 0; border-left: 4px solid $cvStatusColor; background-color: #f5f5f5; }
        .button { display: inline-block; background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        .footer { background-color: #f5f5f5; padding: 15px; text-align: center; font-size: 12px; border-top: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Recordatorio de Actualización de CV</h1>
        </div>
        <div class="content">
            <p>Hola <strong>$relatorName</strong>,</p>
            
            <p>Te escribimos para hacer un recordatorio importante: como relator del sistema Khironex, es fundamental que mantengas tu CV actualizado.</p>
            
            <div class="status">
                <strong>Estado actual de tu CV:</strong><br>
                <span style="color: $cvStatusColor;">$cvStatus</span>
            </div>
            
            <p><strong>¿Por qué es importante?</strong></p>
            <ul>
                <li>Los clientes pueden revisar tu perfil</li>
                <li>Mejora tus oportunidades de capacitaciones</li>
                <li>Mantiene tu información profesional al día</li>
            </ul>
            
            <p><strong>¿Qué debes hacer?</strong></p>
            <ol>
                <li>Accede al sistema Khironex</li>
                <li>Ve a la sección de Relatores</li>
                <li>Edita tu perfil</li>
                <li>Carga o actualiza tu CV</li>
                <li>Guarda los cambios</li>
            </ol>
            
            <p style="text-align: center;">
                <a href="$baseUrl" class="button">Ir al Sistema</a>
            </p>
            
            <p>Si tienes dudas o problemas, por favor contacta al administrador del sistema.</p>
            
            <p>Saludos,<br>Sistema Khironex</p>
        </div>
        <div class="footer">
            <p>Este es un correo automático. Por favor, no respondas a esta dirección.</p>
            <p>Último recordatorio enviado: " . date('d/m/Y H:i:s') . "</p>
        </div>
    </div>
</body>
</html>
HTML;

    // Enviar correo
    if (mail($email, SUBJECT, $body, $headers)) {
        // Actualizar fecha del último envío
        $updateStmt = $db->prepare("UPDATE relatores SET last_cv_reminder_sent = NOW() WHERE " . $relatorIdCol . " = ?");
        if ($updateStmt) {
            $updateStmt->bind_param('i', $relatorId);
            if ($updateStmt->execute()) {
                echo "[" . date('Y-m-d H:i:s') . "] ENVIADO: $relatorName ($email)\n";
                $sent++;
            } else {
                echo "[" . date('Y-m-d H:i:s') . "] ERROR al actualizar: $relatorName\n";
                $failed++;
            }
        }
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] ERROR al enviar: $relatorName ($email)\n";
        $failed++;
    }
}

echo "\n[" . date('Y-m-d H:i:s') . "] === RESUMEN ===\n";
echo "Total procesados: $totalRelatores\n";
echo "Enviados exitosamente: $sent\n";
echo "Errores: $failed\n";
echo "[" . date('Y-m-d H:i:s') . "] Proceso finalizado.\n";

$db->close();
exit($failed > 0 ? 1 : 0);
