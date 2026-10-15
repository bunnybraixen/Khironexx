<?php

class InstanceAPI
{
    public function handle()
    {
        $baseUrl = '';
        $configPath = dirname(__DIR__, 4) . '/config/khironex.php';
        if (is_file($configPath)) {
            $config = require $configPath;
            if (is_array($config) && !empty($config['base_url'])) {
                $baseUrl = rtrim((string)$config['base_url'], '/');
            }
        }

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

        return [
            'status' => 'ok',
            'instance' => [
                'app' => 'Khironex',
                'edition' => 'starter-5.x-adapted',
                'base_url' => $baseUrl,
            ],
        ];
    }
}
