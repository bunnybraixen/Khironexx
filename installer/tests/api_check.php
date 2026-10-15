<?php

$baseUrl = getenv('KHIRONEX_INSTALLER_URL') ?: 'http://localhost/installer/';
$baseUrl = rtrim($baseUrl, '/') . '/';

function request($api)
{
    global $baseUrl;
    $url = $baseUrl . '?api=' . $api;
    $content = @file_get_contents($url);
    if ($content === false) {
        echo "[ERROR] No se pudo contactar $url\n";
        return false;
    }
    $data = json_decode($content, true);
    if (!is_array($data)) {
        echo "[ERROR] Response invalido de $api: $content\n";
        return false;
    }
    echo "[OK] $api -> " . ($data['status'] ?? 'no-status') . "\n";
    return true;
}

$all = true;
$apis = ['system-check', 'version', 'config', 'instance', 'installation-instance', 'register'];
foreach ($apis as $api) {
    $all = $all && request($api);
}

exit($all ? 0 : 1);
