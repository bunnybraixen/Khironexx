<?php

require_once __DIR__ . '/../app/bootstrap.php';

$onboardingConfig = khironex_config();
$onboardingIsApi = strpos(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/onboarding/api/') !== false;

if (empty($onboardingConfig['installed'])) {
    redirect($onboardingIsApi ? '../../installer/' : '../installer/');
}

$onboardingUser = $_SESSION['user'] ?? null;
if (!$onboardingUser || (int)($onboardingUser['id'] ?? 0) <= 0) {
    if ($onboardingIsApi) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'authentication_required']);
        exit;
    }
    redirect('../web/?route=login');
}

$onboardingDbConfig = $onboardingConfig['db'] ?? [];
$onboardingDb = @new mysqli(
    $onboardingDbConfig['host'] ?? 'localhost',
    $onboardingDbConfig['user'] ?? 'root',
    $onboardingDbConfig['pass'] ?? '',
    $onboardingDbConfig['name'] ?? '',
    (int)($onboardingDbConfig['port'] ?? 3306)
);

if ($onboardingDb->connect_error) {
    if ((int)$onboardingDb->connect_errno === 1049 || stripos((string)$onboardingDb->connect_error, 'Unknown database') !== false) {
        redirect($onboardingIsApi ? '../../installer/?step=db&error=missing_db' : '../installer/?step=db&error=missing_db');
    }
    http_response_code(500);
    echo $onboardingIsApi ? json_encode(['error' => 'database_unavailable']) : 'No fue posible conectar con la base de datos de Khironex.';
    exit;
}

$onboardingDb->set_charset('utf8mb4');

$onboardingRoles = fetchRoles($onboardingDb, (int)$onboardingUser['id']);
$onboardingCanAccessRrhh = requireRole('admin', $onboardingRoles)
    || requireRole('rrhh', $onboardingRoles)
    || requireRole('read', $onboardingRoles);
$onboardingCanManage = requireRole('admin', $onboardingRoles)
    || requireRole('rrhh', $onboardingRoles);

if (!$onboardingCanAccessRrhh) {
    http_response_code(403);
    if ($onboardingIsApi) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'forbidden']);
    } else {
        echo 'No tienes permisos para acceder a Onboarding.';
    }
    exit;
}