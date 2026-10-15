<?php

class ConfigFileAPI
{
    public function handle()
    {
        $configPath = dirname(__DIR__, 4) . '/config/khironex.php';
        $installed = false;
        if (is_file($configPath)) {
            $config = require $configPath;
            $installed = is_array($config) && !empty($config['installed']);
        }
        return [
            'status' => 'ok',
            'config_exists' => is_file($configPath),
            'installed' => $installed,
        ];
    }
}
