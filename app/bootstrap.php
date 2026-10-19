<?php

if (!defined('KHIRONEX_OB_STARTED')) {
    if (!headers_sent()) {
        ob_start();
    }
    define('KHIRONEX_OB_STARTED', true);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($GLOBALS['khironex_config'])) {
    $GLOBALS['khironex_config'] = [];
}

if (!function_exists('khironex_env')) {
    function khironex_env($key, $default = null)
    {
        $value = getenv((string)$key);
        return $value === false ? $default : $value;
    }
}

if (!function_exists('khironex_env_bool')) {
    function khironex_env_bool($key, $default = null)
    {
        $value = khironex_env($key, null);
        if ($value === null) {
            return $default;
        }
        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}

$configPath = __DIR__ . '/../config/khironex.php';
if (empty($GLOBALS['khironex_config']) && is_file($configPath)) {
    $config = require $configPath;
    if (is_array($config)) {
        $GLOBALS['khironex_config'] = $config;
    }
}

if (empty($GLOBALS['khironex_config']) || !is_array($GLOBALS['khironex_config'])) {
    $GLOBALS['khironex_config'] = [];
}

$installedOverride = khironex_env_bool('KHIRONEX_INSTALLED', null);
if ($installedOverride !== null) {
    $GLOBALS['khironex_config']['installed'] = $installedOverride;
}

$appNameOverride = trim((string)khironex_env('KHIRONEX_APP_NAME', ''));
if ($appNameOverride !== '') {
    $GLOBALS['khironex_config']['app_name'] = $appNameOverride;
}

$baseUrlOverride = trim((string)khironex_env('KHIRONEX_BASE_URL', ''));
if ($baseUrlOverride !== '') {
    $GLOBALS['khironex_config']['base_url'] = $baseUrlOverride;
}

if (!isset($GLOBALS['khironex_config']['db']) || !is_array($GLOBALS['khironex_config']['db'])) {
    $GLOBALS['khironex_config']['db'] = [];
}

$dbEnvMap = [
    'host' => 'KHIRONEX_DB_HOST',
    'port' => 'KHIRONEX_DB_PORT',
    'name' => 'KHIRONEX_DB_NAME',
    'user' => 'KHIRONEX_DB_USER',
    'pass' => 'KHIRONEX_DB_PASS',
];
foreach ($dbEnvMap as $dbKey => $envKey) {
    $override = khironex_env($envKey, null);
    if ($override !== null) {
        $GLOBALS['khironex_config']['db'][$dbKey] = (string)$override;
    }
}

if (!function_exists('khironex_config')) {
    function khironex_config($key = null, $default = null)
    {
        $config = $GLOBALS['khironex_config'] ?? [];
        if ($key === null) {
            return $config;
        }
        return array_key_exists($key, $config) ? $config[$key] : $default;
    }
}

if (!function_exists('base_url')) {
    function base_url()
    {
        $configured = trim((string)khironex_config('base_url', ''));
        // Treat "/" as "auto-detect". A forced root value breaks dashboard asset paths.
        if ($configured === '/' || $configured === './') {
            $configured = '';
        }
        if ($configured !== '') {
            if (preg_match('#^https?://#i', $configured)) {
                return rtrim($configured, '/');
            }
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $path = '/' . ltrim($configured, '/');
            return rtrim($scheme . '://' . $host . $path, '/');
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = str_replace('\\', '/', dirname($scriptName));
        if ($dir === '.' || $dir === '\\') {
            $dir = '';
        }
        $dir = rtrim($dir, '/');
        $base = $scheme . '://' . $host;
        if ($dir !== '') {
            $base .= $dir;
        }
        return rtrim($base, '/');
    }
}

if (!function_exists('url_path')) {
    function url_path($path = '')
    {
        $base = rtrim((string)base_url(), '/');
        if ($path === null) {
            $path = '';
        }
        $path = (string)$path;
        if ($path === '') {
            return $base . '/';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $firstChar = $path[0];
        if ($firstChar === '?' || $firstChar === '#') {
            return $base . '/' . $path;
        }
        $path = ltrim($path, '/');
        return $base . '/' . $path;
    }
}

if (!function_exists('upload_url')) {
    function upload_url($path)
    {
        // encode path and point to the special proxy route
        $p = urlencode(trim((string)$path, '/'));
        return route_url('uploaded') . '&path=' . $p;
    }
}

if (!function_exists('asset')) {
    function asset($path)
    {
        // for normal assets just use url_path
        if (!is_string($path) || strpos($path, 'uploads/') !== 0) {
            return url_path($path);
        }
        // if uploads directory is not under web/ (left in root), serve via proxy
        $root = dirname(__DIR__, 2);
        $webUploads = $root . '/web/uploads';
        if (!is_dir($webUploads)) {
            return upload_url($path);
        }
        // otherwise file should be in web/uploads and can use normal url
        return url_path($path);
    }
}

if (!function_exists('redirect')) {
    function redirect($path = '')
    {
        header('Location: ' . url_path($path));
        exit;
    }
}

if (!function_exists('route_url')) {
    function route_url($route = '')
    {
        if ($route === '' || $route === null) {
            return url_path('');
        }
        return url_path('?route=' . urlencode((string)$route));
    }
}

foreach ([__DIR__ . '/Support/web_helpers.php', __DIR__ . '/Support/exports.php', __DIR__ . '/Support/imports.php', __DIR__ . '/Support/dashboard.php', __DIR__ . '/Support/contactos.php', __DIR__ . '/Support/database_manager.php', __DIR__ . '/Support/postulantes.php', __DIR__ . '/Support/practicantes.php', __DIR__ . '/Support/sedes.php', __DIR__ . '/Support/requests.php', __DIR__ . '/Support/tickets.php', __DIR__ . '/Support/tareas.php'] as $supportFile) {
    if (is_file($supportFile)) {
        require_once $supportFile;
    }
}

if (!function_exists('bootstrap_assets')) {
    function bootstrap_assets()
    {
        $config = khironex_config();
        $frontend = is_array($config['frontend'] ?? null) ? $config['frontend'] : [];
        $bootstrap = is_array($frontend['bootstrap'] ?? null) ? $frontend['bootstrap'] : [];

        $css = trim((string)($bootstrap['css'] ?? 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css'));
        $js = trim((string)($bootstrap['js'] ?? 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'));

        return [
            'css' => $css,
            'js' => $js,
        ];
    }
}
