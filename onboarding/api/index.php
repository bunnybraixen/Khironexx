<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../models/OnboardingProcessModel.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$resource = (string)($_GET['resource'] ?? '');
$model = new OnboardingModel($onboardingDb);
if (!$model->isStorageReady()) {
    http_response_code(503);
    echo json_encode(['error' => 'onboarding_storage_unavailable', 'migration' => 'sql/onboarding_migration.sql']);
    exit;
}
if ($resource === 'practicantes') {
    $rows = $model->fetchPracticantes();
} elseif ($resource === 'usuarios') {
    $rows = $model->fetchUsuarios();
} elseif ($resource === 'processes') {
    $rows = $model->fetchProcesses($_GET);
} elseif ($resource === 'process') {
    $id = max(0, (int)($_GET['id'] ?? 0));
    $process = $model->fetchProcess($id);
    if (!$process) {
        http_response_code(404);
        echo json_encode(['error' => 'process_not_found']);
        exit;
    }
    $rows = [
        'process' => $process,
        'phases' => $model->fetchPhases($id),
        'tasks' => $model->fetchTaskSummary($id),
        'history' => $model->fetchHistory($id),
    ];
} else {
    http_response_code(400);
    echo json_encode(['error' => 'unknown_resource', 'available' => ['practicantes', 'usuarios', 'processes', 'process']]);
    exit;
}

echo json_encode(['data' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);