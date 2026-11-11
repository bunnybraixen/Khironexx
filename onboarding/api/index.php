<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../models/OnboardingModel.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$resource = (string)($_GET['resource'] ?? '');
$model = new OnboardingModel($onboardingDb);
if ($resource === 'practicantes') {
    $rows = $model->fetchPracticantes();
} elseif ($resource === 'usuarios') {
    $rows = $model->fetchUsuarios();
} else {
    http_response_code(400);
    echo json_encode(['error' => 'unknown_resource', 'available' => ['practicantes', 'usuarios']]);
    exit;
}

echo json_encode(['data' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);