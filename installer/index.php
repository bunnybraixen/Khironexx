<?php

require_once __DIR__ . '/../app/bootstrap.php';

require_once __DIR__ . '/Util/Connection.php';
require_once __DIR__ . '/Util/AppSetupUtility.php';

$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$basePath = $scriptDir === '' ? '' : $scriptDir;
$rootBase = $basePath;
if (basename($basePath) === 'installer') {
    $rootBase = rtrim(dirname($basePath), '/');
}

function joinBasePath($base, $path)
{
    $base = rtrim((string)$base, '/');
    $path = '/' . ltrim((string)$path, '/');
    return $base === '' ? $path : $base . $path;
}

$configDir = __DIR__ . '/../config';
$configPath = $configDir . '/khironex.php';
$appBase = $rootBase;

$step = $_GET['step'] ?? 'welcome';
$errors = [];
$messages = [];

function post($key, $default = '') {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}

function checkExtension($name) {
    return extension_loaded($name);
}

if (is_file($configPath)) {
    $config = require $configPath;
    if (is_array($config) && !empty($config['base_url'])) {
        $appBase = rtrim((string)$config['base_url'], '/');
    }
    if (is_array($config) && !empty($config['installed'])) {
        $dbConf = $config['db'] ?? [];
        $host = $dbConf['host'] ?? 'localhost';
        $port = $dbConf['port'] ?? '3306';
        $user = $dbConf['user'] ?? 'root';
        $pass = $dbConf['pass'] ?? '';
        $name = $dbConf['name'] ?? '';

        [$db, $err] = InstallerConnection::connect($host, $port, $user, $pass, null);
        if (!$err && $db && $name !== '') {
            $safe = $db->real_escape_string($name);
            $exists = $db->query("SHOW DATABASES LIKE '" . $safe . "'");
            if ($exists && $exists->num_rows > 0) {
                header('Location: ' . joinBasePath($appBase, 'dashboard/'));
                exit;
            }
            $messages[] = "La base de datos no existe. Vuelve a configurar la instalacion.";
        }
    }
}

if (isset($_GET['error']) && $_GET['error'] === 'missing_db') {
    $messages[] = "La base de datos indicada no existe. Configura una nueva o crea la base desde el instalador.";
}

function respondJson($payload, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

if (isset($_GET['api'])) {
    $api = (string)$_GET['api'];
    require_once __DIR__ . '/Controller/Installer/Api/SystemCheckAPI.php';
    require_once __DIR__ . '/Controller/Installer/Api/DatabaseConfigAPI.php';
    require_once __DIR__ . '/Controller/Installer/Api/AdminUserAPI.php';
    require_once __DIR__ . '/Controller/Installer/Api/CleanUpInstallAPI.php';
    require_once __DIR__ . '/Controller/Installer/Api/InstallerDataRegistrationAPI.php';
    require_once __DIR__ . '/Controller/Installer/Api/InstanceAPI.php';
    require_once __DIR__ . '/Controller/Installer/Api/InstallationInstanceAPI.php';
    require_once __DIR__ . '/Controller/Installer/Api/VersionAPI.php';
    require_once __DIR__ . '/Controller/Installer/Api/ConfigFileAPI.php';

    switch ($api) {
        case 'system-check':
            $controller = new SystemCheckAPI();
            respondJson($controller->handle());
            break;
        case 'db-config':
            $controller = new DatabaseConfigAPI();
            respondJson($controller->handle());
            break;
        case 'admin-user':
            $controller = new AdminUserAPI();
            respondJson($controller->handle());
            break;
        case 'cleanup':
            $controller = new CleanUpInstallAPI();
            respondJson($controller->handle());
            break;
        case 'register':
            $controller = new InstallerDataRegistrationAPI();
            respondJson($controller->handle());
            break;
        case 'instance':
            $controller = new InstanceAPI();
            respondJson($controller->handle());
            break;
        case 'installation-instance':
            $controller = new InstallationInstanceAPI();
            respondJson($controller->handle());
            break;
        case 'version':
            $controller = new VersionAPI();
            respondJson($controller->handle());
            break;
        case 'config':
            $controller = new ConfigFileAPI();
            respondJson($controller->handle());
            break;
        default:
            respondJson(['status' => 'error', 'message' => 'API no encontrada.'], 404);
    }
}

$vueAppJs = __DIR__ . '/client/dist/js/app.js';
$vueVendorJs = __DIR__ . '/client/dist/js/chunk-vendors.js';
$vueAppCss = __DIR__ . '/client/dist/css/app.css';
$vueVendorCss = __DIR__ . '/client/dist/css/chunk-vendors.css';
if (!isset($_GET['step']) && is_file($vueAppJs)) {
    $assetBase = $basePath . '/client/dist';
    ?><!doctype html>
    <html lang="es">
      <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Khironex - Instalador</title>
        <?php if (is_file($vueVendorCss)): ?>
          <link rel="stylesheet" href="<?php echo htmlspecialchars($assetBase . '/css/chunk-vendors.css'); ?>">
        <?php endif; ?>
        <?php if (is_file($vueAppCss)): ?>
          <link rel="stylesheet" href="<?php echo htmlspecialchars($assetBase . '/css/app.css'); ?>">
        <?php endif; ?>
      </head>
      <body>
        <div id="app"></div>
        <?php if (is_file($vueVendorJs)): ?>
          <script src="<?php echo htmlspecialchars($assetBase . '/js/chunk-vendors.js'); ?>"></script>
        <?php endif; ?>
        <script src="<?php echo htmlspecialchars($assetBase . '/js/app.js'); ?>"></script>
      </body>
    </html>
    <?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 'db') {
        $host = post('db_host', 'localhost');
        $port = post('db_port', '3306');
        $name = post('db_name', 'khironex');
        $user = post('db_user', 'root');
        $pass = post('db_pass', '');
        $createDb = post('db_create', '') === 'yes';

        [$db, $err] = InstallerConnection::connect($host, $port, $user, $pass, null);
        if ($err) {
            $errors[] = "No se pudo conectar: " . $err;
        } else {
            if ($createDb) {
                if (!$db->query("CREATE DATABASE IF NOT EXISTS `" . $db->real_escape_string($name) . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
                    $errors[] = "No se pudo crear la base de datos: " . $db->error;
                }
            }
            if (!$errors) {
                $db->select_db($name);
                $_SESSION['db'] = [
                    'host' => $host,
                    'port' => $port,
                    'name' => $name,
                    'user' => $user,
                    'pass' => $pass,
                ];
                header('Location: ' . joinBasePath($appBase, 'installer/?step=admin'));
                exit;
            }
        }
    }

    if ($step === 'admin') {
        $fullName = post('admin_name');
        $email = post('admin_email');
        $password = post('admin_password');
        $password2 = post('admin_password2');

        if ($fullName === '' || $email === '' || $password === '') {
            $errors[] = "Completa todos los campos.";
        }
        if ($password !== $password2) {
            $errors[] = "Las contrasenas no coinciden.";
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Email invalido.";
        }
        if (!isset($_SESSION['db'])) {
            $errors[] = "Falta configuracion de base de datos.";
        }

        if (!$errors) {
            $dbConf = $_SESSION['db'];
            [$db, $err] = InstallerConnection::connect($dbConf['host'], $dbConf['port'], $dbConf['user'], $dbConf['pass'], $dbConf['name']);
            if ($err) {
                $errors[] = "Conexion fallida: " . $err;
            } else {
                try {
                    $schemaErr = AppSetupUtility::runSchema($db);
                    if ($schemaErr) {
                        $errors[] = "Error creando tablas: " . $schemaErr;
                    } else {
                        AppSetupUtility::createOrUpdateAdmin($db, $fullName, $email, $password, $messages, $errors);
                    }
                } catch (Throwable $e) {
                    $errors[] = "Error al crear administrador: " . $e->getMessage();
                }
            }
        }

        if (!$errors) {
            if (!is_dir($configDir) && !mkdir($configDir, 0755, true)) {
                $errors[] = "No se pudo crear el directorio de configuracion.";
            } else {
                $config = [
                    'installed' => true,
                    'installed_at' => date('c'),
                    'app_name' => 'Khironex',
                    'db' => $_SESSION['db'],
                    'base_url' => '',
                ];
                if (!AppSetupUtility::writeConfig($configPath, $config)) {
                    $errors[] = "No se pudo escribir la configuracion.";
                }
            }
        }

        if (!$errors) {
            session_destroy();
            header('Location: ' . joinBasePath($appBase, 'installer/?step=finish'));
            exit;
        }
    }
}

$requirements = [
    ['PHP >= 8.0', version_compare(PHP_VERSION, '8.0.0', '>=')],
    ['Extension mysqli', checkExtension('mysqli')],
    ['Extension mbstring', checkExtension('mbstring')],
    ['Extension openssl', checkExtension('openssl')],
    ['Extension json', checkExtension('json')],
    ['Directorio config escribible', is_writable($configDir) || (!file_exists($configDir) && is_writable(__DIR__ . '/..'))],
];

?>
<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <title>Khironex - Instalador</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(joinBasePath($appBase, 'installer/assets/style.css')); ?>">
  </head>
  <body>
    <div class="page">
      <div class="glow"></div>
      <div class="wrap">
        <div class="hero">
          <div>
            <div class="hero-brand">
              <img src="<?php echo htmlspecialchars(joinBasePath($appBase, 'img/logo.png')); ?>" alt="Khironex">
              <div>
                <div class="pill">Instalacion guiada</div>
                <div class="hero-brand-title">Khironex Installer</div>
              </div>
            </div>
            <h1 class="hero-title">Bienvenido a Khironex</h1>
            <p class="hero-subtitle">
              Configura tu base de datos y deja el sistema listo en minutos. Este instalador
              organiza los modulos base de RRHH, Administracion y Capacitaciones.
            </p>
          </div>
          <div class="hero-card">
            <h4>Estructura base del sistema</h4>
            <p>
              Unifica datos, garantiza trazabilidad y habilita reportes confiables desde el inicio.
            </p>
            <div class="hero-card-glow"></div>
          </div>
        </div>

        <div class="card">
          <div class="header">
            <div class="title">Instalador Web de Khironex</div>
            <div class="muted">Version inicial</div>
          </div>
          <div class="steps">
            <div class="step <?php echo $step === 'welcome' ? 'active' : ''; ?>">Bienvenida</div>
            <div class="step <?php echo $step === 'requirements' ? 'active' : ''; ?>">Requisitos</div>
            <div class="step <?php echo $step === 'db' ? 'active' : ''; ?>">Base de datos</div>
            <div class="step <?php echo $step === 'admin' ? 'active' : ''; ?>">Admin</div>
            <div class="step <?php echo $step === 'finish' ? 'active' : ''; ?>">Final</div>
          </div>

        <?php if ($errors): ?>
          <div class="error"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
        <?php endif; ?>

        <?php if ($messages): ?>
          <div class="notice"><?php echo implode('<br>', array_map('htmlspecialchars', $messages)); ?></div>
        <?php endif; ?>

          <?php if ($step === 'welcome'): ?>
            <p>Khironex es un sistema web centralizado para RRHH, Administracion y Relatores.</p>
            <p>Este instalador configurara la base de datos y el usuario administrador.</p>
            <div class="divider"></div>
            <div class="actions">
              <a class="btn" href="<?php echo htmlspecialchars(joinBasePath($appBase, 'installer/?step=requirements')); ?>">Continuar</a>
            </div>
          <?php elseif ($step === 'requirements'): ?>
            <div class="grid">
              <?php foreach ($requirements as [$label, $ok]): ?>
                <div class="<?php echo $ok ? 'success' : 'error'; ?>">
                  <?php echo htmlspecialchars($label); ?>: <?php echo $ok ? 'OK' : 'Falta'; ?>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="divider"></div>
            <div class="actions">
              <a class="btn secondary" href="<?php echo htmlspecialchars(joinBasePath($appBase, 'installer/?step=welcome')); ?>">Volver</a>
              <a class="btn" href="<?php echo htmlspecialchars(joinBasePath($appBase, 'installer/?step=db')); ?>">Continuar</a>
            </div>
          <?php elseif ($step === 'db'): ?>
            <form method="post">
              <div class="grid two">
                <div class="field">
                  <label>Host</label>
                  <input name="db_host" value="<?php echo htmlspecialchars(post('db_host', 'localhost')); ?>">
                </div>
                <div class="field">
                  <label>Puerto</label>
                  <input name="db_port" value="<?php echo htmlspecialchars(post('db_port', '3306')); ?>">
                </div>
                <div class="field">
                  <label>Nombre BD</label>
                  <input name="db_name" value="<?php echo htmlspecialchars(post('db_name', 'khironex')); ?>">
                </div>
                <div class="field">
                  <label>Usuario</label>
                  <input name="db_user" value="<?php echo htmlspecialchars(post('db_user', 'root')); ?>">
                </div>
                <div class="field">
                  <label>Password</label>
                  <input type="password" name="db_pass" value="<?php echo htmlspecialchars(post('db_pass')); ?>">
                </div>
                <div class="field">
                  <label>Crear BD si no existe</label>
                  <select name="db_create">
                    <option value="yes">Si</option>
                    <option value="no">No</option>
                  </select>
                </div>
              </div>
              <div class="divider"></div>
              <div class="actions">
                <a class="btn secondary" href="<?php echo htmlspecialchars(joinBasePath($appBase, 'installer/?step=requirements')); ?>">Volver</a>
                <button class="btn" type="submit">Guardar</button>
              </div>
            </form>
          <?php elseif ($step === 'admin'): ?>
            <form method="post">
              <div class="grid two">
                <div class="field">
                  <label>Nombre completo</label>
                  <input name="admin_name" value="<?php echo htmlspecialchars(post('admin_name')); ?>">
                </div>
                <div class="field">
                  <label>Email</label>
                  <input name="admin_email" value="<?php echo htmlspecialchars(post('admin_email')); ?>">
                </div>
                <div class="field">
                  <label>Contrasena</label>
                  <input type="password" name="admin_password">
                </div>
                <div class="field">
                  <label>Repetir contrasena</label>
                  <input type="password" name="admin_password2">
                </div>
              </div>
              <div class="divider"></div>
              <div class="actions">
                <a class="btn secondary" href="<?php echo htmlspecialchars(joinBasePath($appBase, 'installer/?step=db')); ?>">Volver</a>
                <button class="btn" type="submit">Instalar</button>
              </div>
            </form>
          <?php elseif ($step === 'finish'): ?>
            <div class="success">Instalacion completa. Ya puedes ingresar al sistema.</div>
            <div class="actions">
              <a class="btn" href="<?php echo htmlspecialchars(joinBasePath($appBase, 'dashboard/')); ?>">Ir a Khironex</a>
            </div>
          <?php else: ?>
            <div class="notice">Paso no reconocido.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </body>
</html>
