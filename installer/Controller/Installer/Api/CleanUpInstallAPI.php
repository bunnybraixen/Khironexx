<?php

class CleanUpInstallAPI
{
    private function input()
    {
        $data = $_POST;
        if (!$data) {
            $raw = file_get_contents('php://input');
            if ($raw) {
                $json = json_decode($raw, true);
                if (is_array($json)) {
                    $data = $json;
                }
            }
        }
        return $data;
    }

    public function handle()
    {
        if (!isset($_SESSION['db'])) {
            return ['status' => 'error', 'message' => 'No hay configuracion de base de datos.'];
        }

        $data = $this->input();
        $configDir = dirname(__DIR__, 4) . '/config';
        $configPath = $configDir . '/khironex.php';
        $appName = trim((string)($data['app_name'] ?? 'Khironex'));
        $baseUrl = trim((string)($data['base_url'] ?? ''));
        if ($baseUrl === '') {
            $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
            if (basename($scriptDir) === 'installer') {
                $baseUrl = rtrim(dirname($scriptDir), '/');
            } else {
                $baseUrl = $scriptDir;
            }
            if ($baseUrl === '') {
                $baseUrl = '/';
            }
        }

        if (!is_dir($configDir) && !mkdir($configDir, 0755, true)) {
            return ['status' => 'error', 'message' => 'No se pudo crear el directorio de configuracion.'];
        }

        $config = [
            'installed' => true,
            'installed_at' => date('c'),
            'app_name' => $appName,
            'db' => $_SESSION['db'],
            'base_url' => $baseUrl,
        ];

        if (!AppSetupUtility::writeConfig($configPath, $config)) {
            return ['status' => 'error', 'message' => 'No se pudo escribir la configuracion.'];
        }

        session_destroy();
        return ['status' => 'ok', 'message' => 'Instalacion finalizada.'];
    }
}
