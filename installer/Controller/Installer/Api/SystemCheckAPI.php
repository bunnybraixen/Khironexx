<?php

class SystemCheckAPI
{
    public function handle()
    {
        $configDir = dirname(__DIR__, 4) . '/config';
        $checks = [
            ['label' => 'PHP >= 8.0', 'ok' => version_compare(PHP_VERSION, '8.0.0', '>=')],
            ['label' => 'Extension mysqli', 'ok' => extension_loaded('mysqli')],
            ['label' => 'Extension mbstring', 'ok' => extension_loaded('mbstring')],
            ['label' => 'Extension openssl', 'ok' => extension_loaded('openssl')],
            ['label' => 'Extension json', 'ok' => extension_loaded('json')],
            ['label' => 'Directorio config escribible', 'ok' => is_writable($configDir) || (!file_exists($configDir) && is_writable(dirname($configDir)))],
        ];

        $ok = true;
        foreach ($checks as $check) {
            if (!$check['ok']) {
                $ok = false;
                break;
            }
        }

        return [
            'status' => $ok ? 'ok' : 'error',
            'requirements' => $checks,
        ];
    }
}
