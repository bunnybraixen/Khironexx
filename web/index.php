<?php

if (!function_exists('khironex_runtime_log')) {
  function khironex_runtime_log($message, array $context = [])
  {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if (!empty($context)) {
      $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if ($json !== false) {
        $line .= ' ' . $json;
      }
    }

    $line .= PHP_EOL;
    $projectRoot = dirname(__DIR__);
    $logDir = $projectRoot . '/uploads/logs';
    $logFile = $logDir . '/runtime-' . date('Y-m-d') . '.log';
    $fallbackLogFile = $projectRoot . '/runtime.log';
    $tempFallbackLogFile = rtrim((string)sys_get_temp_dir(), '/\\') . '/khironex-runtime.log';

    $wrotePrimary = false;
    if (is_dir($logDir) || @mkdir($logDir, 0775, true)) {
      $wrotePrimary = @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX) !== false;
    }
    if (!$wrotePrimary) {
      $wroteFallback = @file_put_contents($fallbackLogFile, $line, FILE_APPEND | LOCK_EX) !== false;
      if (!$wroteFallback) {
        @file_put_contents($tempFallbackLogFile, $line, FILE_APPEND | LOCK_EX);
      }
    }

    error_log('[khironex] ' . trim($line));
  }
}

if (!function_exists('khironex_render_500')) {
  function khironex_render_500($incidentId = null)
  {
    if (!headers_sent()) {
      http_response_code(500);
      header('Content-Type: text/html; charset=utf-8');
    }
    $safeId = htmlspecialchars((string)$incidentId, ENT_QUOTES, 'UTF-8');
    echo '<h1>Error interno del servidor</h1>';
    echo '<p>Se produjo un error inesperado.</p>';
    if ($safeId !== '') {
      echo '<p>ID de incidente: <strong>' . $safeId . '</strong></p>';
    }
    echo '<p>Revisa los archivos de log: <strong>uploads/logs/runtime-' . date('Y-m-d') . '.log</strong>, <strong>runtime.log</strong> o <strong>' . htmlspecialchars((string)sys_get_temp_dir(), ENT_QUOTES, 'UTF-8') . '/khironex-runtime.log</strong>.</p>';
  }
}

set_exception_handler(function (Throwable $e) {
  $incidentId = uniqid('inc_', true);
  khironex_runtime_log('Unhandled exception', [
    'incident' => $incidentId,
    'type' => get_class($e),
    'message' => $e->getMessage(),
    'file' => $e->getFile(),
    'line' => $e->getLine(),
    'route' => $_GET['route'] ?? null,
    'uri' => $_SERVER['REQUEST_URI'] ?? null,
  ]);
  khironex_render_500($incidentId);
  exit;
});

register_shutdown_function(function () {
  $last = error_get_last();
  if (!$last) {
    return;
  }
  $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
  if (!in_array((int)$last['type'], $fatalTypes, true)) {
    return;
  }
  $incidentId = uniqid('fat_', true);
  khironex_runtime_log('Fatal shutdown error', [
    'incident' => $incidentId,
    'type' => $last['type'],
    'message' => $last['message'] ?? '',
    'file' => $last['file'] ?? '',
    'line' => $last['line'] ?? 0,
    'route' => $_GET['route'] ?? null,
    'uri' => $_SERVER['REQUEST_URI'] ?? null,
  ]);
  khironex_render_500($incidentId);
});

require_once __DIR__ . '/../app/bootstrap.php';

$config = khironex_config();

// proxy for uploaded files (kept outside web root)
if (isset($_GET['route']) && $_GET['route'] === 'uploaded') {
  $path = $_GET['path'] ?? '';
  $path = rawurldecode($path);
  // strip dangerous control characters only
  $path = str_replace(["\0", "\r", "\n"], '', $path);
  // reject any traversal attempts
  if ($path === '' || strpos($path, '..') !== false || strpos($path, 'uploads/') !== 0) {
    http_response_code(400);
    echo 'Bad path';
    exit;
  }
  $root = dirname(__DIR__);
  $file = realpath($root . '/' . $path);
  if (!$file || !is_file($file) || strpos($file, realpath($root . '/uploads') . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    echo 'Not found';
    exit;
  }
  $mime = mime_content_type($file) ?: 'application/octet-stream';
  header('Content-Type: ' . $mime);
  header('Content-Length: ' . filesize($file));
  readfile($file);
  exit;
}

if (empty($config) || empty($config['installed'])) {
  redirect('../installer/');
}

$dbConf = $config['db'] ?? [];

$db = @new mysqli(
    $dbConf['host'] ?? 'localhost',
    $dbConf['user'] ?? 'root',
    $dbConf['pass'] ?? '',
    $dbConf['name'] ?? '',
    (int)($dbConf['port'] ?? 3306)
);

if ($db->connect_error) {
    if ((int)$db->connect_errno === 1049 || stripos((string)$db->connect_error, 'Unknown database') !== false) {
        redirect('../installer/?step=db&error=missing_db');
    }

    http_response_code(500);
    echo "DB error: " . htmlspecialchars($db->connect_error);
    exit;
}

$db->set_charset('utf8mb4');

/* ===========================
   Variables para los triggers
   =========================== */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['user'])) {

    $usuarioId = (int)$_SESSION['user']['id'];
    $usuarioNombre = $db->real_escape_string($_SESSION['user']['full_name']);

    $db->query("SET @usuario_id = {$usuarioId}");
    $db->query("SET @usuario_nombre = '{$usuarioNombre}'");
}

$route = $_GET['route'] ?? 'dashboard';
$routeAliases = [
  'employees' => 'practicantes',
  'trainers' => 'relatores',
  'trainings' => 'capacitaciones',
  'sites' => 'sedes',
  'cursos' => 'capacitaciones',
  'reports' => 'informes',
  'users' => 'usuarios',
  'profile' => 'perfil',
  'catalogs' => 'practicantes',
  'applicants' => 'postulantes',
  'contacts' => 'contactos',
  'basedatos' => 'database',
  'bd' => 'database',
  'db' => 'database',
];
if (isset($routeAliases[$route])) {
  $route = $routeAliases[$route];
}
$user = $_SESSION['user'] ?? null;
$roles = [];
if ($user) {
  try {
    $roles = fetchRoles($db, $user['id'] ?? 0);
  } catch (Throwable $e) {
    $roles = [];
  }
}
$isAdmin = $user && requireRole('admin', $roles);
$isRrhh = $user && requireRole('rrhh', $roles);
$isAdministracion = $user && requireRole('administracion', $roles);
$canRead = $user && requireRole('read', $roles);
$canRrhhAdd = $isAdmin || $isRrhh;
$canRrhhModify = $isAdmin;
$canAdminArea = $isAdmin || $isAdministracion;
$canAccessRrhh = $canRrhhAdd || $canRead;
$canAccessAdminArea = $canAdminArea || $canRead;
$canAdmin = $isAdmin;
// Tareas: todos los usuarios ven la pagina y sus tareas pendientes;
// solo administradores (admin / administracion operativa) pueden asignar.
$canAssignTareas = $isAdmin || $isAdministracion;
$canViewTareas = (bool)$user;

if ($route === 'logout') {
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', (bool)($params['secure'] ?? false), (bool)($params['httponly'] ?? false));
  }
  session_unset();
  session_destroy();
  redirect(route_url('login'));
}

if ($route === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim((string)($_POST['email'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  $row = null;
  try {
    $stmt = $db->prepare("SELECT id, full_name, email, password_hash
      FROM usuarios
      WHERE email = ? AND status = 'active'
      LIMIT 1");
    if ($stmt) {
      $stmt->bind_param('s', $email);
      if ($stmt->execute()) {
        if (method_exists($stmt, 'get_result')) {
          $result = $stmt->get_result();
          if ($result) {
            $row = $result->fetch_assoc();
          }
        } else {
          $stmt->bind_result($id, $fullName, $rowEmail, $passwordHash);
          if ($stmt->fetch()) {
            $row = [
              'id' => (int)$id,
              'full_name' => (string)$fullName,
              'email' => (string)$rowEmail,
              'password_hash' => (string)$passwordHash,
            ];
          }
        }
      }
      $stmt->close();
    }
  } catch (Throwable $e) {
    $row = null;
  }

  if ($row && password_verify($password, (string)($row['password_hash'] ?? ''))) {
    $_SESSION['user'] = [
      'id' => (int)($row['id'] ?? 0),
      'full_name' => (string)($row['full_name'] ?? ''),
      'email' => (string)($row['email'] ?? ''),
    ];
    if (!empty($_SESSION['user']['id'])) {
      redirect('');
    }
  }
  $loginError = "Credenciales invalidas.";
}

if ($route === 'forgot' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $forgotEmail = trim((string)($_POST['email'] ?? ''));
  if ($forgotEmail !== '') {
    $forgotMessage = "Si existe una cuenta con ese email, recibiras un enlace de recuperacion.";
  }
}

if (!$user && !in_array($route, ['login', 'forgot'], true)) {
  $route = 'login';
}

export_practicantes_template_if_requested($route, $user);

export_herramientas_templates_if_requested($route, ($canAccessRrhh || $canAccessAdminArea));

export_informes_if_requested($route, $user, $db);

$ticketsAvailable = false;
$ticketMessagesAvailable = false;
$ticketRows = [];
$ticketMessagesByTicket = [];
$ticketFilters = [
  'q' => trim((string)($_GET['q'] ?? '')),
  'status' => trim((string)($_GET['status'] ?? '')),
  'prioridad' => trim((string)($_GET['prioridad'] ?? '')),
  'modulo' => trim((string)($_GET['modulo'] ?? '')),
];
$ticketFilters['q'] = function_exists('mb_substr') ? mb_substr($ticketFilters['q'], 0, 100) : substr($ticketFilters['q'], 0, 100);
$ticketMessages = $_SESSION['tickets_flash'] ?? [];
$ticketErrors = [];
unset($_SESSION['tickets_flash']);
if ($route === 'tickets' && $user) {
  $ticketsAvailable = db_table_exists($db, 'tickets_soporte');
  $ticketMessagesAvailable = db_table_exists($db, 'tickets_mensajes');
  if ($ticketsAvailable && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticketAction = (string)($_POST['ticket_action'] ?? '');
    $ticketResult = ($ticketAction === 'comment' && !$ticketMessagesAvailable)
      ? ['error' => 'Falta habilitar el historial de mensajes con la migración tickets_mensajes_migration.sql.']
      : tickets_handle_post($db, $user, $isAdmin);
    if (!empty($ticketResult['success'])) {
      $_SESSION['tickets_flash'] = [$ticketResult['success']];
      redirect(route_url('tickets'));
    }
    if (!empty($ticketResult['error'])) {
      $ticketErrors[] = $ticketResult['error'];
    }
  }
  if ($ticketsAvailable) {
    $ticketRows = tickets_fetch_for_user($db, $user['id'] ?? 0, $isAdmin, $ticketFilters);
    if ($ticketMessagesAvailable) {
      $ticketMessagesByTicket = tickets_fetch_messages($db, $ticketRows);
    }
  }
}

$tareasAvailable = false;
$tareaMessages = [];
$tareaErrors = [];
$tareaRows = [];
$tareaUsers = [];
$tareaCanAssign = (bool)$canAssignTareas;
$tareaView = 'pendientes';
$tareaFilters = [
  'q' => trim((string)($_GET['q'] ?? '')),
  'status' => trim((string)($_GET['status'] ?? '')),
  'prioridad' => trim((string)($_GET['prioridad'] ?? '')),
  'responsable' => (int)($_GET['responsable'] ?? 0),
];
$tareaFilters['q'] = function_exists('mb_substr') ? mb_substr($tareaFilters['q'], 0, 100) : substr($tareaFilters['q'], 0, 100);
$tareaFlash = $_SESSION['tareas_flash'] ?? [];
unset($_SESSION['tareas_flash']);
if ($route === 'tareas' && $user && $canViewTareas) {
  tareas_ensure_schema($db);
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tareaResult = tareas_handle_post($db, $user, $canAssignTareas, $isAdmin);
    if (!empty($tareaResult['success'])) {
      $_SESSION['tareas_flash'] = [$tareaResult['success']];
      redirect(route_url('tareas'));
    }
    if (!empty($tareaResult['error'])) {
      $tareaErrors[] = $tareaResult['error'];
    }
  }
  $tareaMessages = array_merge($tareaFlash, $tareaMessages);
  $tareaRows = tareas_fetch_for_user($db, $user['id'] ?? 0, $canAssignTareas, $tareaFilters);
  $tareaUsers = $canAssignTareas ? tareas_fetch_users($db, $user['id'] ?? 0) : [];
  if ($tareaFilters['status'] === 'completada') {
    $tareaView = 'archivadas';
  } elseif ($tareaFilters['status'] === 'pendiente') {
    $tareaView = 'pendientes';
  } else {
    $tareaView = 'todas';
  }
}

$calendarTasks = [];
$calendarMonth = trim((string)($_GET['mes'] ?? date('Y-m')));
$calendarMonthDate = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $calendarMonth)
  ? DateTimeImmutable::createFromFormat('!Y-m', $calendarMonth)
  : false;
if (!$calendarMonthDate || $calendarMonthDate->format('Y-m') !== $calendarMonth) {
  $calendarMonthDate = new DateTimeImmutable('first day of this month');
  $calendarMonth = $calendarMonthDate->format('Y-m');
}
if (in_array($route, ['calendario', 'dashboard'], true) && $user && $canViewTareas) {
  tareas_ensure_schema($db);
  $calendarTasks = tareas_fetch_for_user($db, $user['id'] ?? 0, $canAssignTareas);
}

$frameworkAssets = bootstrap_assets();
$appCssUrl = asset('assets/app.css');
$appJsUrl = asset('assets/app.js');
$appCssVersionPath = __DIR__ . '/assets/app.css';
$appJsVersionPath = __DIR__ . '/assets/app.js';
if (is_file($appCssVersionPath)) {
  $appCssUrl .= '?v=' . (string)filemtime($appCssVersionPath);
}
if (is_file($appJsVersionPath)) {
  $appJsUrl .= '?v=' . (string)filemtime($appJsVersionPath);
}

?>
<!doctype html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Khironex</title>
  <?php if (!empty($frameworkAssets['css'])): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($frameworkAssets['css']); ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="<?php echo htmlspecialchars($appCssUrl); ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="<?php echo in_array($route, ['login', 'forgot'], true) ? 'login-body' : 'dashboard-body'; ?>">
  <?php if ($route === 'login'): ?>
    <?php require __DIR__ . '/../app/Views/auth/login.php'; ?>
  <?php elseif ($route === 'forgot'): ?>
    <?php require __DIR__ . '/../app/Views/auth/forgot.php'; ?>
  <?php else: ?>
    <div class="brand-watermark brand-locked" data-brand-lock="global-watermark" aria-hidden="true"></div>
    <?php
    $dashboardConfigPath = __DIR__ . '/../config/dashboard.php';
    $dashboardConfig = load_dashboard_config($dashboardConfigPath);

    $dashboardMenu = dashboard_menu($dashboardConfig);
    $dashboardMenu = array_values(array_filter($dashboardMenu, function ($item) use ($canAdmin, $canAccessRrhh, $canAccessAdminArea, $canViewTareas) {
      $routeKey = $item['route'] ?? '';
      if ($routeKey === 'usuarios' || $routeKey === 'solicitudes' || $routeKey === 'historial' || $routeKey === 'contactos' || $routeKey === 'database') {
        return $canAdmin;
      }
      if ($routeKey === 'herramientas') {
        return $canAccessRrhh || $canAccessAdminArea;
      }
      if ($routeKey === 'tareas' || $routeKey === 'calendario') {
        return true;
      }
      if ($routeKey === 'archivos') {
        return $canAccessRrhh || $canAccessAdminArea;
      }
      if (in_array($routeKey, ['postulantes', 'practicantes'], true)) {
        return $canAccessRrhh;
      }
      if (in_array($routeKey, ['relatores', 'capacitaciones', 'sedes', 'informes'], true)) {
        return $canAccessAdminArea;
      }
      return true;
    }));

    $routeLabels = dashboard_labels();
    $currentLabel = $routeLabels[$route] ?? ucfirst((string)$route);
    ?>
    <?php if ($route === 'dashboard'): ?>
      <?php
      $dashboardData = [
        'contacts' => [],
        'contactsText' => '',
        'birthdays' => [],
        'birthdayWindowDays' => (int)($dashboardConfig['birthdays_window_days'] ?? 30),
        'expiring' => [],
        'alerts' => [],
        'requests' => [],
        'history' => [],
        'totals' => [
          'practicantes' => 0,
          'relatores' => 0,
          'capacitaciones' => 0,
        ],
      ];
      $dashboardLoadError = '';
      try {
        $dashboardData = dashboard_build_data($db, $dashboardConfig, $canAdmin);
      } catch (Throwable $e) {
        $dashboardLoadError = (string)$e->getMessage();
      }
      $dashboardTasks = ['rows' => [], 'total' => 0];
      if ($user && $canViewTareas && function_exists('tareas_pending_summary')) {
        try {
          $dashboardTasks = tareas_pending_summary($db, (int)($user['id'] ?? 0), 5);
        } catch (Throwable $e) {
          $dashboardTasks = ['rows' => [], 'total' => 0];
        }
      }
      $dashboardData['tasks'] = $dashboardTasks;
      $dashboardData['tasksVisible'] = (bool)($user && $canViewTareas);
      $dashboardData['calendarTasks'] = $calendarTasks;
      $dashboardData['calendarMonth'] = $calendarMonth;
      $dashboardData['calendarMonthDate'] = $calendarMonthDate;
      ?>
      <?php require __DIR__ . '/../app/Views/dashboard/index.php'; ?>
    <?php else: ?>
      <div class="dashboard">
        <?php require __DIR__ . '/../app/Views/layout/sidebar.php'; ?>
        <div class="dashboard-main">
          <?php require __DIR__ . '/../app/Views/layout/topbar.php'; ?>
          <div class="container">
            <?php if ($route === 'postulantes'): ?>
              <?php if (!$canAccessRrhh): ?>
                <div class="notice">No tienes permisos para acceder a RRHH.</div>
              <?php else: ?>
                <?php
                $postulantesData = postulantes_build_data($db, [
                  'canAdd' => $canRrhhAdd,
                  'canModify' => $canRrhhModify,
                  'canDelete' => $canAdmin,
                  'userId' => $user['id'] ?? null,
                ]);
                ?>
                <?php require __DIR__ . '/../app/Views/postulantes/index.php'; ?>
              <?php endif; ?>
            <?php elseif ($route === 'practicantes'): ?>
              <?php if (!$canAccessRrhh): ?>
                <div class="notice">No tienes permisos para acceder a RRHH.</div>
              <?php else: ?>
                <?php
                $practicantesData = practicantes_build_data($db, [
                  'canAdd' => $canRrhhAdd,
                  'canAdmin' => $canAdmin,
                  'userId' => $user['id'] ?? null,
                ]);
                ?>
                <?php require __DIR__ . '/../app/Views/practicantes/index.php'; ?>
              <?php endif; ?>
            <?php elseif ($route === 'relatores'): ?>
              <?php if (!$canAccessAdminArea): ?>
                <div class="notice">No tienes permisos para acceder a Administracion.</div>
              <?php else: ?>
                <?php
                $relatorIdCol = relator_id_column($db);
                $relatorNameCol = relator_name_column($db);
                $relatorCols = db_table_columns($db, 'relatores');
                $relatorDefaultCarreras = [
                  'Administracion',
                  'Comercial',
                  'Contabilidad',
                  'Derecho',
                  'Diseno',
                  'Informatica',
                  'Ingenieria Civil',
                  'Ingenieria Comercial',
                  'Psicologia',
                  'Recursos Humanos',
                ];
                $relatorDefaultUniversidades = [
                  'Duoc UC',
                  'INACAP',
                  'Pontificia Universidad Catolica de Chile',
                  'Universidad Andres Bello',
                  'Universidad de Chile',
                  'Universidad de Santiago de Chile',
                  'Universidad Diego Portales',
                  'Universidad Tecnica Federico Santa Maria',
                ];
                $relatorCarreras = practicantes_fetch_select_options($db, 'carrera', $relatorDefaultCarreras);
                $relatorUniversidades = practicantes_fetch_select_options($db, 'universidad', $relatorDefaultUniversidades);
                $appendRelatorDistinctOptions = function (array $options, string $column) use ($db, $relatorCols) {
                  if (!in_array($column, $relatorCols, true)) {
                    return $options;
                  }
                  $result = $db->query("SELECT DISTINCT " . $column . " AS value
                    FROM relatores
                    WHERE " . $column . " IS NOT NULL AND TRIM(" . $column . ") <> ''
                    ORDER BY " . $column . " ASC
                    LIMIT 300");
                  if ($result) {
                    while ($row = $result->fetch_assoc()) {
                      $value = trim((string)($row['value'] ?? ''));
                      if ($value !== '' && !in_array($value, $options, true)) {
                        $options[] = $value;
                      }
                    }
                  }
                  return $options;
                };
                $relatorCarreras = $appendRelatorDistinctOptions($relatorCarreras, 'carrera');
                $relatorUniversidades = $appendRelatorDistinctOptions($relatorUniversidades, 'universidad');
                sort($relatorCarreras);
                sort($relatorUniversidades);
                $relatorRegionComunas = [
                  'Arica y Parinacota' => ['Arica', 'Camarones', 'General Lagos', 'Putre'],
                  'Tarapaca' => ['Alto Hospicio', 'Camina', 'Colchane', 'Huara', 'Iquique', 'Pica', 'Pozo Almonte'],
                  'Antofagasta' => ['Antofagasta', 'Calama', 'Maria Elena', 'Mejillones', 'Ollague', 'San Pedro de Atacama', 'Sierra Gorda', 'Taltal', 'Tocopilla'],
                  'Atacama' => ['Alto del Carmen', 'Caldera', 'Chanaral', 'Copiapo', 'Diego de Almagro', 'Freirina', 'Huasco', 'Tierra Amarilla', 'Vallenar'],
                  'Coquimbo' => ['Andacollo', 'Canela', 'Combarbala', 'Coquimbo', 'Illapel', 'La Higuera', 'La Serena', 'Los Vilos', 'Monte Patria', 'Ovalle', 'Paihuano', 'Punitaqui', 'Rio Hurtado', 'Salamanca', 'Vicuna'],
                  'Valparaiso' => ['Algarrobo', 'Cabildo', 'Calera', 'Cartagena', 'Casablanca', 'Concon', 'El Quisco', 'El Tabo', 'Hijuelas', 'La Cruz', 'La Ligua', 'Limache', 'Los Andes', 'Nogales', 'Olmue', 'Petorca', 'Puchuncavi', 'Quilpue', 'Quillota', 'Quintero', 'San Antonio', 'San Esteban', 'San Felipe', 'Santa Maria', 'Valparaiso', 'Villa Alemana', 'Vina del Mar'],
                  'Metropolitana' => ['Cerrillos', 'Cerro Navia', 'Conchali', 'El Bosque', 'Estacion Central', 'Huechuraba', 'Independencia', 'La Cisterna', 'La Florida', 'La Granja', 'La Pintana', 'La Reina', 'Las Condes', 'Lo Barnechea', 'Lo Espejo', 'Lo Prado', 'Macul', 'Maipu', 'Nunoa', 'Pedro Aguirre Cerda', 'Penalolen', 'Providencia', 'Pudahuel', 'Puente Alto', 'Quilicura', 'Quinta Normal', 'Recoleta', 'Renca', 'San Bernardo', 'San Joaquin', 'San Miguel', 'San Ramon', 'Santiago', 'Vitacura'],
                  'OHiggins' => ['Chimbarongo', 'Codegua', 'Coinco', 'Coltauco', 'Donihue', 'Graneros', 'Las Cabras', 'Machali', 'Mostazal', 'Nancagua', 'Navidad', 'Olivar', 'Palmilla', 'Paredones', 'Peralillo', 'Pichidegua', 'Pichilemu', 'Placilla', 'Pumanque', 'Quinta de Tilcoco', 'Rancagua', 'Rengo', 'Requinoa', 'San Fernando', 'San Vicente'],
                  'Maule' => ['Cauquenes', 'Chanco', 'Colbun', 'Constitucion', 'Curepto', 'Curico', 'Empedrado', 'Hualane', 'Linares', 'Longavi', 'Maule', 'Molina', 'Parral', 'Pelarco', 'Pelluhue', 'Pencahue', 'Rauco', 'Retiro', 'Rio Claro', 'Romeral', 'Sagrada Familia', 'San Clemente', 'San Javier', 'San Rafael', 'Talca', 'Teno', 'Vichuquen', 'Villa Alegre', 'Yerbas Buenas'],
                  'Nuble' => ['Bulnes', 'Chillan', 'Chillan Viejo', 'Cobquecura', 'Coelemu', 'Coihueco', 'El Carmen', 'Ninhue', 'Pemuco', 'Pinto', 'Portezuelo', 'Quillon', 'Quirihue', 'Ranquil', 'San Carlos', 'San Fabian', 'San Ignacio', 'San Nicolas', 'Trehuaco', 'Yungay'],
                  'Biobio' => ['Alto Biobio', 'Arauco', 'Cabrero', 'Canete', 'Chiguayante', 'Concepcion', 'Contulmo', 'Coronel', 'Curanilahue', 'Florida', 'Hualpen', 'Hualqui', 'Laja', 'Lebu', 'Los Alamos', 'Los Angeles', 'Lota', 'Mulchen', 'Nacimiento', 'Negrete', 'Penco', 'Quilaco', 'Quilleco', 'San Pedro de la Paz', 'San Rosendo', 'Santa Barbara', 'Santa Juana', 'Talcahuano', 'Tirua', 'Tome', 'Tucapel', 'Yumbel'],
                  'Araucania' => ['Angol', 'Carahue', 'Cholchol', 'Collipulli', 'Cunco', 'Curacautin', 'Curarrehue', 'Ercilla', 'Freire', 'Galvarino', 'Gorbea', 'Lautaro', 'Loncoche', 'Lonquimay', 'Los Sauces', 'Lumaco', 'Melipeuco', 'Nueva Imperial', 'Padre Las Casas', 'Perquenco', 'Pitrufquen', 'Pucon', 'Puren', 'Renaico', 'Saavedra', 'Temuco', 'Teodoro Schmidt', 'Tolten', 'Traiguen', 'Victoria', 'Vilcun', 'Villarrica'],
                  'Los Rios' => ['Corral', 'Futrono', 'Lago Ranco', 'Lanco', 'La Union', 'Los Lagos', 'Mariquina', 'Mafil', 'Paillaco', 'Panguipulli', 'Rio Bueno', 'Valdivia'],
                  'Los Lagos' => ['Ancud', 'Calbuco', 'Castro', 'Chaiten', 'Cochamo', 'Curaco de Velez', 'Dalcahue', 'Fresia', 'Frutillar', 'Futaleufu', 'Hualaihue', 'Llanquihue', 'Los Muermos', 'Maullin', 'Osorno', 'Palena', 'Puerto Montt', 'Puerto Octay', 'Puerto Varas', 'Puqueldon', 'Purranque', 'Puyehue', 'Queilen', 'Quellon', 'Quemchi', 'Quinchao', 'Rio Negro', 'San Juan de la Costa', 'San Pablo'],
                  'Aysen' => ['Aysen', 'Chile Chico', 'Cisnes', 'Cochrane', 'Coyhaique', 'Guaitecas', 'Lago Verde', 'OHiggins', 'Rio Ibanez', 'Tortel'],
                  'Magallanes' => ['Antartica', 'Cabo de Hornos', 'Laguna Blanca', 'Natales', 'Porvenir', 'Primavera', 'Punta Arenas', 'Rio Verde', 'San Gregorio', 'Timaukel', 'Torres del Paine'],
                ];
                if (in_array('region', $relatorCols, true)) {
                  $regionSql = "SELECT DISTINCT TRIM(region) AS region_name";
                  if (in_array('comuna', $relatorCols, true)) {
                    $regionSql .= ", TRIM(comuna) AS comuna_name";
                  } else {
                    $regionSql .= ", '' AS comuna_name";
                  }
                  $regionSql .= " FROM relatores WHERE region IS NOT NULL AND TRIM(region) <> '' LIMIT 700";
                  $regionResult = $db->query($regionSql);
                  if ($regionResult) {
                    while ($regionRow = $regionResult->fetch_assoc()) {
                      $regionName = trim((string)($regionRow['region_name'] ?? ''));
                      $comunaName = trim((string)($regionRow['comuna_name'] ?? ''));
                      if ($regionName === '') {
                        continue;
                      }
                      if (!isset($relatorRegionComunas[$regionName])) {
                        $relatorRegionComunas[$regionName] = [];
                      }
                      if ($comunaName !== '' && !in_array($comunaName, $relatorRegionComunas[$regionName], true)) {
                        $relatorRegionComunas[$regionName][] = $comunaName;
                      }
                    }
                  }
                }
                foreach ($relatorRegionComunas as $regionName => $regionComunas) {
                  if ($regionComunas) {
                    sort($regionComunas);
                  }
                  $relatorRegionComunas[$regionName] = $regionComunas;
                }
                $messages = [];
                $errors = [];
                $formType = $_POST['form_type'] ?? '';
                $relatorRequestedModule = trim((string)($_REQUEST['ui_module'] ?? ''));
                $relatorActiveModule = in_array($relatorRequestedModule, ['registrar', 'buscar', 'editar'], true) ? $relatorRequestedModule : 'registrar';

                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                  if ($formType === 'relator_create') {
                    $relatorActiveModule = 'registrar';
                  } elseif ($formType === 'relator_update') {
                    $relatorActiveModule = 'editar';
                  } else {
                    $relatorActiveModule = 'buscar';
                  }
                }

                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                  if (!$canAdminArea) {
                    $errors[] = "No tienes permisos para editar relatores.";
                  } elseif ($formType === 'relator_create') {
                    $nombre = trim((string)($_POST['nombre_relator'] ?? ''));
                    $email = trim((string)($_POST['email'] ?? ''));
                    $telefono = trim((string)($_POST['telefono'] ?? ''));
                    $especialidades = trim((string)($_POST['especialidades'] ?? ''));
                    $cursos = trim((string)($_POST['cursos'] ?? ''));
                    $carreraSelected = trim((string)($_POST['carrera'] ?? ''));
                    $carreraOtra = trim((string)($_POST['carrera_otra'] ?? ''));
                    $carrera = $carreraSelected === 'otro' ? $carreraOtra : $carreraSelected;
                    $academico = trim((string)($_POST['academico'] ?? ''));
                    $experiencia = isset($_POST['experiencia_cc']) ? 1 : 0;
                    $sence = isset($_POST['sence']) ? 1 : 0;
                    $universidadSelected = trim((string)($_POST['universidad'] ?? ''));
                    $universidadOtra = trim((string)($_POST['universidad_otra'] ?? ''));
                    $universidad = $universidadSelected === 'otro' ? $universidadOtra : $universidadSelected;
                    $region = trim((string)($_POST['region'] ?? ''));
                    $comunaSelected = trim((string)($_POST['comuna'] ?? ''));
                    $comunaOtra = trim((string)($_POST['comuna_otra'] ?? ''));
                    $comuna = $comunaSelected === 'otra' ? $comunaOtra : $comunaSelected;
                    $observaciones = trim((string)($_POST['observaciones'] ?? ''));
                    $calificacionRaw = trim((string)($_POST['calificacion'] ?? ''));
                    $calificacion = $calificacionRaw === '' ? null : (float)str_replace(',', '.', $calificacionRaw);
                    $sistemaReuf = trim((string)($_POST['sistema_reuf'] ?? ''));
                    $cvPath = store_uploaded_file($_FILES['cv_file'] ?? [], 'relatores');
                    if (!$cvPath) {
                      $cvPath = trim((string)($_POST['cv_path'] ?? ''));
                    }

                    if ($nombre === '') {
                      $errors[] = "El nombre del relator es obligatorio.";
                    }
                    if ($carreraSelected === 'otro' && $carrera === '') {
                      $errors[] = "Debes indicar la carrera.";
                    }
                    if ($universidadSelected === 'otro' && $universidad === '') {
                      $errors[] = "Debes indicar la universidad.";
                    }
                    if ($comunaSelected === 'otra' && $comuna === '') {
                      $errors[] = "Debes indicar la comuna.";
                    }
                    if ($comuna !== '' && $region === '') {
                      $errors[] = "Debes seleccionar una region para la comuna.";
                    }
                    if ($calificacion !== null && ($calificacion < 1 || $calificacion > 5)) {
                      $errors[] = "La calificacion debe estar entre 1.0 y 5.0.";
                    }
                    if ($nombre !== '' && !$errors) {
                      $dupStmt = $db->prepare("SELECT " . $relatorIdCol . " FROM relatores WHERE " . $relatorNameCol . " = ? LIMIT 1");
                      $dupStmt->bind_param('s', $nombre);
                      $dupStmt->execute();
                      if ($dupStmt->get_result()->fetch_assoc()) {
                        $errors[] = "Ya existe un relator con ese nombre.";
                      }
                    }
                    if (!$errors) {
                      $colValues = [
                        $relatorNameCol => $nombre,
                        'email' => $email,
                        'telefono' => $telefono,
                        'especialidades' => $especialidades,
                        'cursos' => $cursos,
                        'carrera' => $carrera,
                        'academico' => $academico,
                        'experiencia_cc' => $experiencia,
                        'sence' => $sence,
                        'universidad' => $universidad,
                        'region' => $region,
                        'comuna' => $comuna,
                        'calificacion' => $calificacion,
                        'observaciones' => $observaciones,
                        'cv_path' => $cvPath,
                        'sistema_reuf' => $sistemaReuf,
                      ];
                      $columns = [];
                      $placeholders = [];
                      $types = '';
                      $values = [];
                      foreach ($colValues as $col => $val) {
                        if (!in_array($col, $relatorCols, true)) {
                          continue;
                        }
                        if ($val === '' || $val === null) {
                          if (in_array($col, ['experiencia_cc', 'sence'], true)) {
                            $val = (int)$val;
                          } else {
                            continue;
                          }
                        }
                        $columns[] = $col;
                        $placeholders[] = '?';
                        if (in_array($col, ['experiencia_cc', 'sence'], true)) {
                          $types .= 'i';
                          $values[] = (int)$val;
                        } elseif ($col === 'calificacion') {
                          $types .= 'd';
                          $values[] = (float)$val;
                        } else {
                          $types .= 's';
                          $values[] = (string)$val;
                        }
                      }
                      if ($columns) {
                        $stmt = $db->prepare("INSERT INTO relatores (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")");
                        if ($stmt) {
                          $stmt->bind_param($types, ...$values);
                          if ($stmt->execute()) {
                            $messages[] = "Relator agregado.";
                          } else {
                            $errors[] = "No se pudo guardar el relator.";
                          }
                        } else {
                          $errors[] = "No se pudo preparar la insercion.";
                        }
                      } else {
                        $errors[] = "No se pudieron mapear columnas para guardar.";
                      }
                    }
                  } elseif ($formType === 'relator_update') {
                    $relatorId = (int)($_POST['relator_id'] ?? 0);
                    $nombre = trim((string)($_POST['nombre_relator'] ?? ''));
                    $email = trim((string)($_POST['email'] ?? ''));
                    $telefono = trim((string)($_POST['telefono'] ?? ''));
                    $especialidades = trim((string)($_POST['especialidades'] ?? ''));
                    $cursos = trim((string)($_POST['cursos'] ?? ''));
                    $carrera = trim((string)($_POST['carrera'] ?? ''));
                    $academico = trim((string)($_POST['academico'] ?? ''));
                    $experiencia = isset($_POST['experiencia_cc']) ? 1 : 0;
                    $sence = isset($_POST['sence']) ? 1 : 0;
                    $universidad = trim((string)($_POST['universidad'] ?? ''));
                    $region = trim((string)($_POST['region'] ?? ''));
                    $comuna = trim((string)($_POST['comuna'] ?? ''));
                    $observaciones = trim((string)($_POST['observaciones'] ?? ''));
                    $calificacionRaw = trim((string)($_POST['calificacion'] ?? ''));
                    $calificacion = $calificacionRaw === '' ? null : (float)str_replace(',', '.', $calificacionRaw);
                    $sistemaReuf = trim((string)($_POST['sistema_reuf'] ?? ''));
                    $cvPath = store_uploaded_file($_FILES['cv_file'] ?? [], 'relatores');
                    if (!$cvPath) {
                      $cvPath = trim((string)($_POST['cv_path'] ?? ''));
                    }

                    if ($relatorId <= 0) {
                      $errors[] = "Relator invalido para editar.";
                    }
                    if ($nombre === '') {
                      $errors[] = "El nombre del relator es obligatorio.";
                    }
                    if ($calificacion !== null && ($calificacion < 1 || $calificacion > 5)) {
                      $errors[] = "La calificacion debe estar entre 1.0 y 5.0.";
                    }
                    if ($nombre !== '' && $relatorId > 0 && !$errors) {
                      $dupStmt = $db->prepare("SELECT " . $relatorIdCol . " FROM relatores WHERE " . $relatorNameCol . " = ? AND " . $relatorIdCol . " != ? LIMIT 1");
                      $dupStmt->bind_param('si', $nombre, $relatorId);
                      $dupStmt->execute();
                      if ($dupStmt->get_result()->fetch_assoc()) {
                        $errors[] = "Ya existe otro relator con ese nombre.";
                      }
                    }

                    if (!$errors) {
                      $colValues = [
                        $relatorNameCol => $nombre,
                        'email' => $email,
                        'telefono' => $telefono,
                        'especialidades' => $especialidades,
                        'cursos' => $cursos,
                        'carrera' => $carrera,
                        'academico' => $academico,
                        'experiencia_cc' => $experiencia,
                        'sence' => $sence,
                        'universidad' => $universidad,
                        'region' => $region,
                        'comuna' => $comuna,
                        'calificacion' => $calificacion,
                        'observaciones' => $observaciones,
                        'cv_path' => $cvPath,
                        'sistema_reuf' => $sistemaReuf,
                      ];

                      $sets = [];
                      $types = '';
                      $values = [];
                      foreach ($colValues as $col => $val) {
                        if (!in_array($col, $relatorCols, true)) {
                          continue;
                        }
                        if ($val === '' || $val === null) {
                          if (in_array($col, ['experiencia_cc', 'sence'], true)) {
                            $val = (int)$val;
                          } else {
                            continue;
                          }
                        }

                        $sets[] = $col . " = ?";
                        if (in_array($col, ['experiencia_cc', 'sence'], true)) {
                          $types .= 'i';
                          $values[] = (int)$val;
                        } elseif ($col === 'calificacion') {
                          $types .= 'd';
                          $values[] = (float)$val;
                        } else {
                          $types .= 's';
                          $values[] = (string)$val;
                        }
                      }

                      if ($sets) {
                        $sql = "UPDATE relatores SET " . implode(', ', $sets) . " WHERE " . $relatorIdCol . " = ?";
                        $stmt = $db->prepare($sql);
                        if ($stmt) {
                          $types .= 'i';
                          $values[] = $relatorId;
                          $stmt->bind_param($types, ...$values);
                          if ($stmt->execute()) {
                            $messages[] = "Relator actualizado.";
                          } else {
                            $errors[] = "No se pudo actualizar el relator.";
                          }
                        } else {
                          $errors[] = "No se pudo preparar la actualizacion.";
                        }
                      } else {
                        $errors[] = "No se detectaron datos para actualizar.";
                      }
                    }
                  } elseif ($formType === 'relator_delete') {
                    $relatorId = (int)($_POST['relator_id'] ?? 0);
                    if ($relatorId > 0) {
                      $stmt = $db->prepare("DELETE FROM relatores WHERE " . $relatorIdCol . " = ?");
                      if ($stmt) {
                        $stmt->bind_param('i', $relatorId);
                        if ($stmt->execute()) {
                          $messages[] = "Relator eliminado.";
                        } else {
                          $errors[] = "No se pudo eliminar el relator.";
                        }
                      }
                    }
                  } elseif ($formType === 'relator_sence') {
                    $relatorId = (int)($_POST['relator_id'] ?? 0);
                    $sence = isset($_POST['sence']) ? 1 : 0;
                    if ($relatorId > 0 && in_array('sence', $relatorCols, true)) {
                      $stmt = $db->prepare("UPDATE relatores SET sence = ? WHERE " . $relatorIdCol . " = ?");
                      if ($stmt) {
                        $stmt->bind_param('ii', $sence, $relatorId);
                        if ($stmt->execute()) {
                          $messages[] = "SENCE actualizado.";
                        } else {
                          $errors[] = "No se pudo actualizar SENCE.";
                        }
                      }
                    }
                  } elseif ($formType === 'relator_documento') {
                    $relatorId = (int)($_POST['relator_id'] ?? 0);
                    $titulo = trim((string)($_POST['titulo'] ?? 'Documento'));
                    $tipo = trim((string)($_POST['tipo'] ?? ''));
                    $ruta = store_uploaded_file($_FILES['archivo'] ?? [], 'relatores');
                    if (!$ruta) {
                      $ruta = trim((string)($_POST['ruta'] ?? ''));
                    }
                    if ($relatorId > 0 && $ruta !== '') {
                      $stmt = $db->prepare("INSERT INTO documentos (target_type, target_id, titulo, tipo, ruta, created_at) VALUES ('relator', ?, ?, ?, ?, NOW())");
                      if ($stmt) {
                        $stmt->bind_param('isss', $relatorId, $titulo, $tipo, $ruta);
                        if ($stmt->execute()) {
                          $messages[] = "Documento asociado.";
                        } else {
                          $errors[] = "No se pudo guardar el documento.";
                        }
                      }
                    } else {
                      $errors[] = "Debes adjuntar un archivo o ruta.";
                    }
                  }
                }

                $filterGeneral = trim((string)($_GET['q'] ?? ''));
                $filterSence = trim((string)($_GET['sence'] ?? ''));
                $filterEspecialidad = trim((string)($_GET['especialidad'] ?? ''));
                $filterCurso = trim((string)($_GET['curso'] ?? ''));
                $filterCarrera = trim((string)($_GET['carrera'] ?? ''));
                $filterUniversidad = trim((string)($_GET['universidad'] ?? ''));
                $filterRegion = trim((string)($_GET['region'] ?? ''));
                $filterComuna = trim((string)($_GET['comuna'] ?? ''));
                $filterExperiencia = trim((string)($_GET['experiencia'] ?? ''));
                $filterCalificacion = trim((string)($_GET['calificacion'] ?? ''));
                $filterReuf = trim((string)($_GET['reuf'] ?? ''));
                $filterCv = trim((string)($_GET['cv'] ?? ''));
                $filterSort = trim((string)($_GET['sort'] ?? ''));
                foreach (['q', 'sence', 'especialidad', 'curso', 'carrera', 'universidad', 'region', 'comuna', 'experiencia', 'calificacion', 'reuf', 'cv', 'sort'] as $key) {
                  if (isset($_GET[$key]) && trim((string)$_GET[$key]) !== '') {
                    $relatorActiveModule = 'buscar';
                    break;
                  }
                }
                if ((int)($_GET['view'] ?? 0) > 0) {
                  $relatorActiveModule = 'buscar';
                }
                if ((int)($_GET['edit'] ?? 0) > 0) {
                  $relatorActiveModule = 'editar';
                }
                $filters = [];
                $params = [];
                $types = '';
                if ($filterGeneral !== '') {
                  $searchColumns = [$relatorNameCol];
                  foreach (['email', 'telefono', 'especialidades', 'cursos', 'carrera', 'universidad', 'region', 'comuna'] as $col) {
                    if (in_array($col, $relatorCols, true)) {
                      $searchColumns[] = $col;
                    }
                  }
                  $tokens = preg_split('/\s+/', $filterGeneral, -1, PREG_SPLIT_NO_EMPTY);
                  foreach ($tokens as $token) {
                    $tokenFilters = [];
                    foreach ($searchColumns as $col) {
                      $tokenFilters[] = $col . " LIKE ?";
                      $params[] = '%' . $token . '%';
                      $types .= 's';
                    }
                    if ($tokenFilters) {
                      $filters[] = '(' . implode(' OR ', $tokenFilters) . ')';
                    }
                  }
                }
                if ($filterEspecialidad !== '' && in_array('especialidades', $relatorCols, true)) {
                  $filters[] = "especialidades LIKE ?";
                  $params[] = '%' . $filterEspecialidad . '%';
                  $types .= 's';
                }
                if ($filterCurso !== '' && in_array('cursos', $relatorCols, true)) {
                  $filters[] = "cursos LIKE ?";
                  $params[] = '%' . $filterCurso . '%';
                  $types .= 's';
                }
                if ($filterCarrera !== '' && in_array('carrera', $relatorCols, true)) {
                  $filters[] = "carrera LIKE ?";
                  $params[] = '%' . $filterCarrera . '%';
                  $types .= 's';
                }
                if ($filterUniversidad !== '' && in_array('universidad', $relatorCols, true)) {
                  $filters[] = "universidad LIKE ?";
                  $params[] = '%' . $filterUniversidad . '%';
                  $types .= 's';
                }
                if ($filterRegion !== '' && in_array('region', $relatorCols, true)) {
                  $filters[] = "region = ?";
                  $params[] = $filterRegion;
                  $types .= 's';
                }
                if ($filterComuna !== '' && in_array('comuna', $relatorCols, true)) {
                  $filters[] = "comuna = ?";
                  $params[] = $filterComuna;
                  $types .= 's';
                }
                if ($filterSence !== '' && in_array('sence', $relatorCols, true)) {
                  $filters[] = "sence = ?";
                  $params[] = $filterSence === 'si' ? 1 : 0;
                  $types .= 'i';
                }
                if ($filterExperiencia !== '' && in_array('experiencia_cc', $relatorCols, true)) {
                  $filters[] = "experiencia_cc = ?";
                  $params[] = $filterExperiencia === 'si' ? 1 : 0;
                  $types .= 'i';
                }
                if ($filterCalificacion !== '' && in_array('calificacion', $relatorCols, true)) {
                  $filters[] = "calificacion >= ?";
                  $params[] = (float)str_replace(',', '.', $filterCalificacion);
                  $types .= 'd';
                }
                if ($filterReuf !== '' && in_array('sistema_reuf', $relatorCols, true)) {
                  $filters[] = "LOWER(TRIM(sistema_reuf)) = ?";
                  $params[] = strtolower($filterReuf) === 'si' ? 'si' : 'no';
                  $types .= 's';
                }
                if ($filterCv !== '' && in_array('cv_path', $relatorCols, true)) {
                  if ($filterCv === 'si') {
                    $filters[] = "cv_path IS NOT NULL AND TRIM(cv_path) <> ''";
                  } elseif ($filterCv === 'no') {
                    $filters[] = "(cv_path IS NULL OR TRIM(cv_path) = '')";
                  }
                }

                $sortSql = $relatorIdCol . " DESC";
                if ($filterSort === 'nombre_asc') {
                  $sortSql = $relatorNameCol . " ASC";
                } elseif ($filterSort === 'nombre_desc') {
                  $sortSql = $relatorNameCol . " DESC";
                } elseif ($filterSort === 'calificacion_desc' && in_array('calificacion', $relatorCols, true)) {
                  $sortSql = "calificacion DESC, " . $relatorNameCol . " ASC";
                } elseif ($filterSort === 'calificacion_asc' && in_array('calificacion', $relatorCols, true)) {
                  $sortSql = "calificacion ASC, " . $relatorNameCol . " ASC";
                }

                $sql = "SELECT * FROM relatores";
                if ($filters) {
                  $sql .= " WHERE " . implode(" AND ", $filters);
                }
                $sql .= " ORDER BY " . $sortSql . " LIMIT 200";
                $stmt = $db->prepare($sql);
                if ($params) {
                  $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $trainers = $stmt->get_result();
                $trainersCount = $trainers ? (int)$trainers->num_rows : 0;

                $viewId = (int)($_GET['view'] ?? 0);
                $editId = (int)($_GET['edit'] ?? 0);
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && $formType === 'relator_update') {
                  $editId = (int)($_POST['relator_id'] ?? 0);
                }
                $viewRelator = null;
                $editRelator = null;
                $relatorDocs = [];
                if ($viewId > 0) {
                  $detailStmt = $db->prepare("SELECT * FROM relatores WHERE " . $relatorIdCol . " = ? LIMIT 1");
                  $detailStmt->bind_param('i', $viewId);
                  $detailStmt->execute();
                  $viewRelator = $detailStmt->get_result()->fetch_assoc();
                  if ($viewRelator) {
                    $docStmt = $db->prepare("SELECT titulo, tipo, ruta, created_at FROM documentos WHERE target_type = 'relator' AND target_id = ? ORDER BY created_at DESC");
                    $docStmt->bind_param('i', $viewId);
                    $docStmt->execute();
                    $docRes = $docStmt->get_result();
                    while ($row = $docRes->fetch_assoc()) {
                      $relatorDocs[] = $row;
                    }
                  }
                }
                if ($editId > 0) {
                  $editStmt = $db->prepare("SELECT * FROM relatores WHERE " . $relatorIdCol . " = ? LIMIT 1");
                  if ($editStmt) {
                    $editStmt->bind_param('i', $editId);
                    $editStmt->execute();
                    $editRelator = $editStmt->get_result()->fetch_assoc();
                  }
                }
                $relatorDraft = [];
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && $formType === 'relator_create' && $errors) {
                  $relatorDraft = $_POST;
                }
                $relatorEditDraft = [];
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && $formType === 'relator_update' && $errors) {
                  $relatorEditDraft = $_POST;
                }
                $relatorDraftValue = function ($key, $default = '') use ($relatorDraft) {
                  if (!array_key_exists($key, $relatorDraft)) {
                    return $default;
                  }
                  return trim((string)$relatorDraft[$key]);
                };
                $relatorDraftChecked = function ($key) use ($relatorDraft) {
                  return array_key_exists($key, $relatorDraft);
                };
                $relatorEditValue = function ($key, $default = '') use ($relatorEditDraft, $editRelator) {
                  if (array_key_exists($key, $relatorEditDraft)) {
                    return trim((string)$relatorEditDraft[$key]);
                  }
                  if (is_array($editRelator) && array_key_exists($key, $editRelator)) {
                    return trim((string)$editRelator[$key]);
                  }
                  return $default;
                };
                $relatorEditChecked = function ($key) use ($relatorEditDraft, $editRelator) {
                  if (array_key_exists($key, $relatorEditDraft)) {
                    return true;
                  }
                  return !empty($editRelator[$key]);
                };
                $currentRelatorCarrera = $relatorDraftValue('carrera');
                $currentRelatorCarreraOtra = $relatorDraftValue('carrera_otra');
                if ($currentRelatorCarrera !== '' && $currentRelatorCarrera !== 'otro' && !in_array($currentRelatorCarrera, $relatorCarreras, true)) {
                  $currentRelatorCarreraOtra = $currentRelatorCarrera;
                  $currentRelatorCarrera = 'otro';
                }
                $currentRelatorUniversidad = $relatorDraftValue('universidad');
                $currentRelatorUniversidadOtra = $relatorDraftValue('universidad_otra');
                if ($currentRelatorUniversidad !== '' && $currentRelatorUniversidad !== 'otro' && !in_array($currentRelatorUniversidad, $relatorUniversidades, true)) {
                  $currentRelatorUniversidadOtra = $currentRelatorUniversidad;
                  $currentRelatorUniversidad = 'otro';
                }
                $currentRelatorRegion = $relatorDraftValue('region');
                $currentRelatorComuna = $relatorDraftValue('comuna');
                $currentRelatorComunaOtra = $relatorDraftValue('comuna_otra');
                $selectedRelatorRegionComunas = isset($relatorRegionComunas[$currentRelatorRegion]) ? $relatorRegionComunas[$currentRelatorRegion] : [];
                if ($currentRelatorComuna !== '' && $currentRelatorComuna !== 'otra' && !in_array($currentRelatorComuna, $selectedRelatorRegionComunas, true)) {
                  $currentRelatorComunaOtra = $currentRelatorComuna;
                  $currentRelatorComuna = 'otra';
                }
                $searchRelatorCarreras = $relatorCarreras;
                if ($filterCarrera !== '' && !in_array($filterCarrera, $searchRelatorCarreras, true)) {
                  $searchRelatorCarreras[] = $filterCarrera;
                  sort($searchRelatorCarreras);
                }
                $searchRelatorUniversidades = $relatorUniversidades;
                if ($filterUniversidad !== '' && !in_array($filterUniversidad, $searchRelatorUniversidades, true)) {
                  $searchRelatorUniversidades[] = $filterUniversidad;
                  sort($searchRelatorUniversidades);
                }
                $searchRelatorRegiones = array_keys($relatorRegionComunas);
                if ($filterRegion !== '' && !in_array($filterRegion, $searchRelatorRegiones, true)) {
                  $searchRelatorRegiones[] = $filterRegion;
                  sort($searchRelatorRegiones);
                }
                $searchRelatorComunas = [];
                if ($filterRegion !== '' && isset($relatorRegionComunas[$filterRegion])) {
                  $searchRelatorComunas = $relatorRegionComunas[$filterRegion];
                } else {
                  foreach ($relatorRegionComunas as $comunas) {
                    foreach ($comunas as $comunaName) {
                      if (!in_array($comunaName, $searchRelatorComunas, true)) {
                        $searchRelatorComunas[] = $comunaName;
                      }
                    }
                  }
                  sort($searchRelatorComunas);
                }
                if ($filterComuna !== '' && !in_array($filterComuna, $searchRelatorComunas, true)) {
                  $searchRelatorComunas[] = $filterComuna;
                  sort($searchRelatorComunas);
                }
                ?>
                <div class="card">
                  <div class="card-header">
                    <div>
                      <h3>Relatores</h3>
                      <p class="subtle">Registro de relatores con SENCE y especialidades.</p>
                    </div>
                    <span class="chip">Administracion</span>
                  </div>
                  <?php foreach ($messages as $message): ?>
                    <div class="notice"><?php echo htmlspecialchars($message); ?></div>
                  <?php endforeach; ?>
                  <?php foreach ($errors as $error): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                  <?php endforeach; ?>

                  <style>
                    .relatores-tabs {
                      display: flex;
                      gap: 10px;
                      margin: 12px 0 18px;
                    }

                    .relatores-tab {
                      min-width: 130px;
                    }

                    .relatores-tab.is-active {
                      background: linear-gradient(120deg, var(--app-accent), var(--app-accent-2));
                      color: #f5fbfb;
                    }

                    .relatores-panel[hidden] {
                      display: none !important;
                    }
                  </style>

                  <div class="relatores-tabs" data-relatores-tabs style="display: flex; flex-wrap: wrap; gap: 10px; width: 100%;">
                    <button type="button" class="btn secondary relatores-tab <?php echo $relatorActiveModule === 'registrar' ? 'is-active' : ''; ?>" data-module-target="registrar">Registrar</button>
                    <button type="button" class="btn secondary relatores-tab <?php echo $relatorActiveModule === 'buscar' ? 'is-active' : ''; ?>" data-module-target="buscar">Buscar</button>
                    <button type="button" class="btn secondary relatores-tab <?php echo $relatorActiveModule === 'editar' ? 'is-active' : ''; ?>" data-module-target="editar">Editar</button>
                  </div>

                  <section class="relatores-panel" data-relatores-panel="registrar" <?php echo $relatorActiveModule === 'registrar' ? '' : 'hidden'; ?>>
                    <?php if ($canAdminArea): ?>
                      <form id="relator-create-form" method="post" enctype="multipart/form-data" class="stack compact">
                        <input type="hidden" name="form_type" value="relator_create">
                        <input type="hidden" name="ui_module" value="registrar">
                        <div class="grid two">
                          <div class="field">
                            <label>Nombre relator</label>
                            <input name="nombre_relator" required value="<?php echo htmlspecialchars($relatorDraftValue('nombre_relator')); ?>">
                          </div>
                          <div class="field">
                            <label>Email</label>
                            <input name="email" type="email" value="<?php echo htmlspecialchars($relatorDraftValue('email')); ?>">
                          </div>
                          <div class="field">
                            <label>Telefono</label>
                            <input name="telefono" value="<?php echo htmlspecialchars($relatorDraftValue('telefono')); ?>">
                          </div>
                          <div class="field">
                            <label>Especialidades</label>
                            <input name="especialidades" placeholder="Ej: Excel, Gestion, RRHH" value="<?php echo htmlspecialchars($relatorDraftValue('especialidades')); ?>">
                          </div>
                          <div class="field">
                            <label>Cursos que dicta</label>
                            <input name="cursos" placeholder="Ej: Induccion, Liderazgo" value="<?php echo htmlspecialchars($relatorDraftValue('cursos')); ?>">
                          </div>
                          <div class="field">
                            <label>Carrera</label>
                            <select name="carrera" data-relator-carrera>
                              <option value="">Selecciona carrera</option>
                              <?php foreach ($relatorCarreras as $option): ?>
                                <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $currentRelatorCarrera === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                              <?php endforeach; ?>
                              <option value="otro" <?php echo $currentRelatorCarrera === 'otro' ? 'selected' : ''; ?>>Otro</option>
                            </select>
                          </div>
                          <div class="field" data-relator-carrera-otra <?php echo $currentRelatorCarrera === 'otro' ? '' : 'hidden'; ?>>
                            <label>Otra carrera</label>
                            <input name="carrera_otra" value="<?php echo htmlspecialchars($currentRelatorCarreraOtra); ?>" <?php echo $currentRelatorCarrera === 'otro' ? '' : 'disabled'; ?>>
                          </div>
                          <div class="field full">
                            <label>Datos academicos</label>
                            <textarea name="academico" rows="2"><?php echo htmlspecialchars($relatorDraftValue('academico')); ?></textarea>
                          </div>
                          <div class="field">
                            <label>Universidad</label>
                            <select name="universidad" data-relator-universidad>
                              <option value="">Selecciona universidad</option>
                              <?php foreach ($relatorUniversidades as $option): ?>
                                <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $currentRelatorUniversidad === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                              <?php endforeach; ?>
                              <option value="otro" <?php echo $currentRelatorUniversidad === 'otro' ? 'selected' : ''; ?>>Otro</option>
                            </select>
                          </div>
                          <div class="field" data-relator-universidad-otra <?php echo $currentRelatorUniversidad === 'otro' ? '' : 'hidden'; ?>>
                            <label>Otra universidad</label>
                            <input name="universidad_otra" value="<?php echo htmlspecialchars($currentRelatorUniversidadOtra); ?>" <?php echo $currentRelatorUniversidad === 'otro' ? '' : 'disabled'; ?>>
                          </div>
                          <div class="field">
                            <label>Region</label>
                            <select name="region" data-relator-region>
                              <option value="">Selecciona region</option>
                              <?php foreach (array_keys($relatorRegionComunas) as $regionName): ?>
                                <option value="<?php echo htmlspecialchars($regionName); ?>" <?php echo $currentRelatorRegion === $regionName ? 'selected' : ''; ?>><?php echo htmlspecialchars($regionName); ?></option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                          <div class="field">
                            <label>Comuna</label>
                            <select name="comuna" data-relator-comuna data-current="<?php echo htmlspecialchars($currentRelatorComuna); ?>"></select>
                          </div>
                          <div class="field" data-relator-comuna-otra <?php echo $currentRelatorComuna === 'otra' ? '' : 'hidden'; ?>>
                            <label>Otra comuna</label>
                            <input name="comuna_otra" value="<?php echo htmlspecialchars($currentRelatorComunaOtra); ?>" <?php echo $currentRelatorComuna === 'otra' ? '' : 'disabled'; ?>>
                          </div>
                          <div class="field full">
                            <label>Observaciones</label>
                            <textarea name="observaciones" rows="2"><?php echo htmlspecialchars($relatorDraftValue('observaciones')); ?></textarea>
                          </div>
                          <div class="field">
                            <label>CV (archivo)</label>
                            <input type="file" name="cv_file" accept=".pdf,.doc,.docx">
                          </div>
                          <div class="field">
                            <label>CV (ruta/URL)</label>
                            <input name="cv_path" value="<?php echo htmlspecialchars($relatorDraftValue('cv_path')); ?>">
                          </div>
                          <div class="field">
                            <label><input type="checkbox" name="sence" <?php echo $relatorDraftChecked('sence') ? 'checked' : ''; ?>> SENCE</label>
                          </div>
                          <div class="field">
                            <label><input type="checkbox" name="experiencia_cc" <?php echo $relatorDraftChecked('experiencia_cc') ? 'checked' : ''; ?>> Experiencia Relatores</label>
                          </div>
                          <?php if (in_array('sistema_reuf', $relatorCols, true)): ?>
                            <div class="field">
                              <label>Sistema REUF</label>
                              <select name="sistema_reuf">
                                <option value="">-- Seleccionar --</option>
                                <option value="si" <?php echo $relatorDraftValue('sistema_reuf') === 'si' ? 'selected' : ''; ?>>Si</option>
                                <option value="no" <?php echo $relatorDraftValue('sistema_reuf') === 'no' ? 'selected' : ''; ?>>No</option>
                              </select>
                            </div>
                          <?php endif; ?>
                          <?php if (in_array('calificacion', $relatorCols, true)): ?>
                            <div class="field">
                              <label>Calificacion (1.0 a 5.0)</label>
                              <input type="number" name="calificacion" min="1" max="5" step="0.1" value="<?php echo htmlspecialchars($relatorDraftValue('calificacion')); ?>">
                            </div>
                          <?php endif; ?>
                        </div>
                        <button class="btn" type="submit">Agregar relator</button>
                      </form>
                      <script>
                        (function() {
                          var form = document.getElementById('relator-create-form');
                          if (!form) {
                            return;
                          }

                          var regionComunas = <?php echo json_encode($relatorRegionComunas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                          var carreraSelect = form.querySelector('[data-relator-carrera]');
                          var carreraOtraField = form.querySelector('[data-relator-carrera-otra]');
                          var carreraOtraInput = form.querySelector('[name="carrera_otra"]');
                          var universidadSelect = form.querySelector('[data-relator-universidad]');
                          var universidadOtraField = form.querySelector('[data-relator-universidad-otra]');
                          var universidadOtraInput = form.querySelector('[name="universidad_otra"]');
                          var regionSelect = form.querySelector('[data-relator-region]');
                          var comunaSelect = form.querySelector('[data-relator-comuna]');
                          var comunaOtraField = form.querySelector('[data-relator-comuna-otra]');
                          var comunaOtraInput = form.querySelector('[name="comuna_otra"]');

                          function addOption(select, value, label) {
                            var option = document.createElement('option');
                            option.value = value;
                            option.textContent = label;
                            select.appendChild(option);
                          }

                          function hasOption(select, value) {
                            for (var i = 0; i < select.options.length; i++) {
                              if (select.options[i].value === value) {
                                return true;
                              }
                            }
                            return false;
                          }

                          function syncCarreraOtra() {
                            if (!carreraSelect || !carreraOtraField || !carreraOtraInput) {
                              return;
                            }
                            var show = carreraSelect.value === 'otro';
                            carreraOtraField.hidden = !show;
                            carreraOtraInput.disabled = !show;
                            carreraOtraInput.required = show;
                          }

                          function syncUniversidadOtra() {
                            if (!universidadSelect || !universidadOtraField || !universidadOtraInput) {
                              return;
                            }
                            var show = universidadSelect.value === 'otro';
                            universidadOtraField.hidden = !show;
                            universidadOtraInput.disabled = !show;
                            universidadOtraInput.required = show;
                          }

                          function fillComunas(selectedValue) {
                            if (!comunaSelect) {
                              return;
                            }
                            var region = regionSelect ? regionSelect.value : '';
                            var comunas = (region && regionComunas[region]) ? regionComunas[region] : [];

                            comunaSelect.innerHTML = '';
                            addOption(comunaSelect, '', 'Selecciona comuna');
                            comunas.forEach(function(name) {
                              addOption(comunaSelect, name, name);
                            });
                            addOption(comunaSelect, 'otra', 'Otra comuna');

                            if (selectedValue && hasOption(comunaSelect, selectedValue)) {
                              comunaSelect.value = selectedValue;
                            } else if (selectedValue) {
                              comunaSelect.value = 'otra';
                              if (comunaOtraInput && comunaOtraInput.value === '') {
                                comunaOtraInput.value = selectedValue;
                              }
                            }
                          }

                          function syncComunaOtra() {
                            if (!comunaSelect || !comunaOtraField || !comunaOtraInput) {
                              return;
                            }
                            var show = comunaSelect.value === 'otra';
                            comunaOtraField.hidden = !show;
                            comunaOtraInput.disabled = !show;
                            comunaOtraInput.required = show;
                          }

                          if (carreraSelect) {
                            carreraSelect.addEventListener('change', syncCarreraOtra);
                          }
                          if (universidadSelect) {
                            universidadSelect.addEventListener('change', syncUniversidadOtra);
                          }
                          if (regionSelect) {
                            regionSelect.addEventListener('change', function() {
                              fillComunas('');
                              syncComunaOtra();
                            });
                          }
                          if (comunaSelect) {
                            comunaSelect.addEventListener('change', syncComunaOtra);
                          }

                          fillComunas(comunaSelect ? (comunaSelect.getAttribute('data-current') || '') : '');
                          syncCarreraOtra();
                          syncUniversidadOtra();
                          syncComunaOtra();
                        })();
                      </script>
                    <?php else: ?>
                      <div class="notice">Solo lectura: no tienes permisos para editar.</div>
                    <?php endif; ?>
                  </section>

                  <section class="relatores-panel" data-relatores-panel="editar" <?php echo $relatorActiveModule === 'editar' ? '' : 'hidden'; ?>>
                    <?php if (!$canAdminArea): ?>
                      <div class="notice">Solo lectura: no tienes permisos para editar.</div>
                    <?php elseif (!$editRelator): ?>
                      <div class="notice">Selecciona un relator desde la pestana Buscar para editar sus datos.</div>
                    <?php else: ?>
                      <form method="post" enctype="multipart/form-data" class="stack compact">
                        <input type="hidden" name="form_type" value="relator_update">
                        <input type="hidden" name="ui_module" value="editar">
                        <input type="hidden" name="relator_id" value="<?php echo (int)($editRelator[$relatorIdCol] ?? 0); ?>">
                        <div class="grid two">
                          <div class="field">
                            <label>Nombre relator</label>
                            <input name="nombre_relator" required value="<?php echo htmlspecialchars($relatorEditValue('nombre_relator', (string)($editRelator[$relatorNameCol] ?? ''))); ?>">
                          </div>
                          <div class="field">
                            <label>Email</label>
                            <input name="email" type="email" value="<?php echo htmlspecialchars($relatorEditValue('email')); ?>">
                          </div>
                          <div class="field">
                            <label>Telefono</label>
                            <input name="telefono" value="<?php echo htmlspecialchars($relatorEditValue('telefono')); ?>">
                          </div>
                          <div class="field">
                            <label>Especialidades</label>
                            <input name="especialidades" value="<?php echo htmlspecialchars($relatorEditValue('especialidades')); ?>">
                          </div>
                          <div class="field">
                            <label>Cursos que dicta</label>
                            <input name="cursos" value="<?php echo htmlspecialchars($relatorEditValue('cursos')); ?>">
                          </div>
                          <div class="field">
                            <label>Carrera</label>
                            <input name="carrera" value="<?php echo htmlspecialchars($relatorEditValue('carrera')); ?>">
                          </div>
                          <div class="field full">
                            <label>Datos academicos</label>
                            <textarea name="academico" rows="2"><?php echo htmlspecialchars($relatorEditValue('academico')); ?></textarea>
                          </div>
                          <div class="field">
                            <label>Universidad</label>
                            <input name="universidad" value="<?php echo htmlspecialchars($relatorEditValue('universidad')); ?>">
                          </div>
                          <div class="field">
                            <label>Region</label>
                            <input name="region" value="<?php echo htmlspecialchars($relatorEditValue('region')); ?>">
                          </div>
                          <div class="field">
                            <label>Comuna</label>
                            <input name="comuna" value="<?php echo htmlspecialchars($relatorEditValue('comuna')); ?>">
                          </div>
                          <?php if (in_array('calificacion', $relatorCols, true)): ?>
                            <div class="field">
                              <label>Calificacion (1.0 a 5.0)</label>
                              <input type="number" name="calificacion" min="1" max="5" step="0.1" value="<?php echo htmlspecialchars($relatorEditValue('calificacion')); ?>">
                            </div>
                          <?php endif; ?>
                          <div class="field full">
                            <label>Observaciones</label>
                            <textarea name="observaciones" rows="2"><?php echo htmlspecialchars($relatorEditValue('observaciones')); ?></textarea>
                          </div>
                          <div class="field">
                            <label>CV (archivo)</label>
                            <input type="file" name="cv_file" accept=".pdf,.doc,.docx">
                          </div>
                          <div class="field">
                            <label>CV (ruta/URL)</label>
                            <input name="cv_path" value="<?php echo htmlspecialchars($relatorEditValue('cv_path')); ?>">
                          </div>
                          <div class="field">
                            <label><input type="checkbox" name="sence" <?php echo $relatorEditChecked('sence') ? 'checked' : ''; ?>> SENCE</label>
                          </div>
                          <div class="field">
                            <label><input type="checkbox" name="experiencia_cc" <?php echo $relatorEditChecked('experiencia_cc') ? 'checked' : ''; ?>> Experiencia Relatores</label>
                          </div>
                          <?php if (in_array('sistema_reuf', $relatorCols, true)): ?>
                            <div class="field">
                              <label>Sistema REUF</label>
                              <select name="sistema_reuf">
                                <option value="">-- Seleccionar --</option>
                                <option value="si" <?php echo $relatorEditValue('sistema_reuf') === 'Si' ? 'selected' : ''; ?>>Si</option>
                                <option value="no" <?php echo $relatorEditValue('sistema_reuf') === 'No' ? 'selected' : ''; ?>>No</option>
                              </select>
                            </div>
                          <?php endif; ?>
                        </div>
                        <button class="btn" type="submit">Guardar cambios</button>
                      </form>
                    <?php endif; ?>
                  </section>

                  <section class="relatores-panel" data-relatores-panel="buscar" <?php echo $relatorActiveModule === 'buscar' ? '' : 'hidden'; ?>>
                    <form id="relator-search-form" method="get" class="filters">
                      <h3>Buscador de Relatores</h3>
                      <input type="hidden" name="route" value="relatores">
                      <input type="hidden" name="ui_module" value="buscar">
                      <div class="grid two">
                        <div class="field">
                          <label>Busqueda general</label>
                          <input name="q" placeholder="Nombre, correo, telefono, especialidad, curso..." value="<?php echo htmlspecialchars($filterGeneral); ?>">
                        </div>
                        <div class="field">
                          <label>Especialidad</label>
                          <input name="especialidad" value="<?php echo htmlspecialchars($filterEspecialidad); ?>">
                        </div>
                        <div class="field">
                          <label>Curso</label>
                          <input name="curso" value="<?php echo htmlspecialchars($filterCurso); ?>">
                        </div>
                        <div class="field">
                          <label>Carrera</label>
                          <select name="carrera">
                            <option value="">Todas</option>
                            <?php foreach ($searchRelatorCarreras as $option): ?>
                              <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $filterCarrera === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="field">
                          <label>Universidad</label>
                          <select name="universidad">
                            <option value="">Todas</option>
                            <?php foreach ($searchRelatorUniversidades as $option): ?>
                              <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $filterUniversidad === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="field">
                          <label>Region</label>
                          <select name="region" data-relator-search-region>
                            <option value="">Todas</option>
                            <?php foreach ($searchRelatorRegiones as $option): ?>
                              <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $filterRegion === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="field">
                          <label>Comuna</label>
                          <select name="comuna" data-relator-search-comuna data-current="<?php echo htmlspecialchars($filterComuna); ?>">
                            <option value="">Todas</option>
                            <?php foreach ($searchRelatorComunas as $option): ?>
                              <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $filterComuna === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="field">
                          <label>SENCE</label>
                          <select name="sence">
                            <option value="">Todos</option>
                            <option value="si" <?php echo $filterSence === 'si' ? 'selected' : ''; ?>>Si</option>
                            <option value="no" <?php echo $filterSence === 'no' ? 'selected' : ''; ?>>No</option>
                          </select>
                        </div>
                        <div class="field">
                          <label>Experiencia Relatores</label>
                          <select name="experiencia">
                            <option value="">Todos</option>
                            <option value="si" <?php echo $filterExperiencia === 'si' ? 'selected' : ''; ?>>Si</option>
                            <option value="no" <?php echo $filterExperiencia === 'no' ? 'selected' : ''; ?>>No</option>
                          </select>
                        </div>
                        <?php if (in_array('sistema_reuf', $relatorCols, true)): ?>
                          <div class="field">
                            <label>Sistema REUF</label>
                            <select name="reuf">
                              <option value="">Todos</option>
                              <option value="si" <?php echo strtolower($filterReuf) === 'si' ? 'selected' : ''; ?>>Si</option>
                              <option value="no" <?php echo strtolower($filterReuf) === 'no' ? 'selected' : ''; ?>>No</option>
                            </select>
                          </div>
                        <?php endif; ?>
                        <?php if (in_array('cv_path', $relatorCols, true)): ?>
                          <div class="field">
                            <label>CV adjunto</label>
                            <select name="cv">
                              <option value="">Todos</option>
                              <option value="si" <?php echo $filterCv === 'si' ? 'selected' : ''; ?>>Si</option>
                              <option value="no" <?php echo $filterCv === 'no' ? 'selected' : ''; ?>>No</option>
                            </select>
                          </div>
                        <?php endif; ?>
                        <?php if (in_array('calificacion', $relatorCols, true)): ?>
                          <div class="field">
                            <label>Calificacion minima</label>
                            <input type="number" name="calificacion" min="1" max="5" step="0.1" value="<?php echo htmlspecialchars($filterCalificacion); ?>">
                          </div>
                        <?php endif; ?>
                        <div class="field">
                          <label>Ordenar por</label>
                          <select name="sort">
                            <option value="">Mas recientes</option>
                            <option value="nombre_asc" <?php echo $filterSort === 'nombre_asc' ? 'selected' : ''; ?>>Nombre (A-Z)</option>
                            <option value="nombre_desc" <?php echo $filterSort === 'nombre_desc' ? 'selected' : ''; ?>>Nombre (Z-A)</option>
                            <?php if (in_array('calificacion', $relatorCols, true)): ?>
                              <option value="calificacion_desc" <?php echo $filterSort === 'calificacion_desc' ? 'selected' : ''; ?>>Calificacion (mayor primero)</option>
                              <option value="calificacion_asc" <?php echo $filterSort === 'calificacion_asc' ? 'selected' : ''; ?>>Calificacion (menor primero)</option>
                            <?php endif; ?>
                          </select>
                        </div>
                      </div>
                      <div class="filter-actions">
                        <button class="btn" type="submit">Aplicar filtros</button>
                        <a class="btn secondary" href="<?php echo htmlspecialchars(route_url('relatores') . '&ui_module=buscar'); ?>">Limpiar</a>
                      </div>
                    </form>
                    <p class="subtle">Resultados encontrados: <?php echo (int)$trainersCount; ?> (maximo 200)</p>
                    <div style="display: block; width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; clear: both; margin-bottom: 20px;">
                      <table style="width: 100%; min-width: 600px; border-collapse: collapse;">
                        <thead>
                          <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Contacto</th>
                            <th>Especialidades</th>
                            <th>SENCE</th>
                            <th>Cursos</th>
                            <th>Experiencia</th>
                            <?php if (in_array('calificacion', $relatorCols, true)): ?>
                              <th>Calificacion</th>
                            <?php endif; ?>
                            <?php if (in_array('sistema_reuf', $relatorCols, true)): ?>
                              <th>Sistema REUF</th>
                            <?php endif; ?>
                            <th>Acciones</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php if ($trainers): while ($row = $trainers->fetch_assoc()): ?>
                              <tr>
                                <td><?php echo htmlspecialchars((string)($row[$relatorIdCol] ?? '')); ?></td>
                                <td>
                                  <a class="link" href="<?php echo htmlspecialchars(route_url('relatores') . '&ui_module=buscar&view=' . (int)($row[$relatorIdCol] ?? 0)); ?>">
                                    <?php echo htmlspecialchars((string)($row[$relatorNameCol] ?? '')); ?>
                                  </a>
                                </td>
                                <td>
                                  <?php echo htmlspecialchars((string)($row['email'] ?? '')); ?>
                                  <?php if (!empty($row['telefono'])): ?>
                                    <span class="subtle"><?php echo htmlspecialchars((string)$row['telefono']); ?></span>
                                  <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars((string)($row['especialidades'] ?? '')); ?></td>
                                <td><?php echo !empty($row['sence']) ? 'Si' : 'No'; ?></td>
                                <td><?php echo htmlspecialchars((string)($row['cursos'] ?? '')); ?></td>
                                <td><?php echo !empty($row['experiencia_cc']) ? 'Si' : 'No'; ?></td>
                                <?php if (in_array('calificacion', $relatorCols, true)): ?>
                                  <td><?php echo isset($row['calificacion']) && $row['calificacion'] !== null ? htmlspecialchars((string)$row['calificacion']) : '-'; ?></td>
                                <?php endif; ?>
                                <?php if (in_array('sistema_reuf', $relatorCols, true)): ?>
                                  <td><?php echo htmlspecialchars((string)($row['sistema_reuf'] ?? '-')); ?></td>
                                <?php endif; ?>
                                <td>
                                  <?php if ($canAdminArea): ?>
                                    <a class="btn warning" href="<?php echo htmlspecialchars(route_url('relatores') . '&ui_module=editar&edit=' . (int)($row[$relatorIdCol] ?? 0)); ?>">Editar</a>
                                    <form method="post" onsubmit="return confirm('Eliminar relator <?php echo htmlspecialchars((string)($row[$relatorNameCol] ?? '')); ?>');">
                                      <input type="hidden" name="form_type" value="relator_delete">
                                      <input type="hidden" name="relator_id" value="<?php echo (int)($row[$relatorIdCol] ?? 0); ?>">
                                      <input type="hidden" name="ui_module" value="buscar">
                                      <button class="btn danger" type="submit">Eliminar</button>
                                    </form>
                                  <?php else: ?>
                                    <span class="subtle">Solo lectura</span>
                                  <?php endif; ?>
                                </td>
                              </tr>
                          <?php endwhile;
                          endif; ?>
                        </tbody>
                      </table>
                    </section>

                    <script>
                      (function() {
                        var tabContainer = document.querySelector('[data-relatores-tabs]');
                        if (!tabContainer) {
                          return;
                        }

                        var moduleButtons = tabContainer.querySelectorAll('[data-module-target]');
                        var modulePanels = document.querySelectorAll('[data-relatores-panel]');
                        var initialModule = '<?php echo htmlspecialchars($relatorActiveModule, ENT_QUOTES); ?>';

                        function setActiveModule(module, persist) {
                          moduleButtons.forEach(function(button) {
                            var active = button.getAttribute('data-module-target') === module;
                            button.classList.toggle('is-active', active);
                          });
                          modulePanels.forEach(function(panel) {
                            panel.hidden = panel.getAttribute('data-relatores-panel') !== module;
                          });
                          if (persist) {
                            try {
                              localStorage.setItem('khx_relatores_active_module', module);
                            } catch (err) {}
                          }
                        }

                        moduleButtons.forEach(function(button) {
                          button.addEventListener('click', function() {
                            var module = button.getAttribute('data-module-target') || 'registrar';
                            setActiveModule(module, true);
                          });
                        });

                        setActiveModule(initialModule, false);

                        function bindPersistentForm(form, storageKey, keepServerValue) {
                          if (!form) {
                            return;
                          }

                          function serialize() {
                            var data = {};
                            var fields = form.querySelectorAll('input, select, textarea');
                            fields.forEach(function(field) {
                              if (!field.name) {
                                return;
                              }
                              if (field.type === 'password' || field.type === 'submit' || field.type === 'button') {
                                return;
                              }
                              if (field.type === 'checkbox') {
                                data[field.name] = field.checked ? '1' : '0';
                              } else {
                                data[field.name] = field.value;
                              }
                            });
                            return data;
                          }

                          function restore() {
                            var raw;
                            try {
                              raw = localStorage.getItem(storageKey);
                            } catch (err) {
                              raw = null;
                            }
                            if (!raw) {
                              return;
                            }

                            var data;
                            try {
                              data = JSON.parse(raw);
                            } catch (err) {
                              return;
                            }

                            var fields = form.querySelectorAll('input, select, textarea');
                            fields.forEach(function(field) {
                              if (!field.name || !Object.prototype.hasOwnProperty.call(data, field.name)) {
                                return;
                              }
                              if (keepServerValue && field.value !== '' && field.type !== 'checkbox') {
                                return;
                              }
                              if (field.type === 'checkbox') {
                                field.checked = data[field.name] === '1';
                              } else {
                                field.value = data[field.name];
                              }
                            });
                          }

                          function save() {
                            try {
                              localStorage.setItem(storageKey, JSON.stringify(serialize()));
                            } catch (err) {}
                          }

                          restore();
                          form.addEventListener('input', save);
                          form.addEventListener('change', save);
                          form.addEventListener('submit', save);
                        }

                        bindPersistentForm(document.getElementById('relator-create-form'), 'khx_relatores_registrar_form', true);
                        bindPersistentForm(document.getElementById('relator-search-form'), 'khx_relatores_buscar_form', true);

                        (function syncSearchComunas() {
                          var searchForm = document.getElementById('relator-search-form');
                          if (!searchForm) {
                            return;
                          }
                          var regionComunas = <?php echo json_encode($relatorRegionComunas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                          var regionSelect = searchForm.querySelector('[data-relator-search-region]');
                          var comunaSelect = searchForm.querySelector('[data-relator-search-comuna]');
                          if (!regionSelect || !comunaSelect) {
                            return;
                          }

                          function addOption(select, value, label) {
                            var option = document.createElement('option');
                            option.value = value;
                            option.textContent = label;
                            select.appendChild(option);
                          }

                          function hasOption(select, value) {
                            for (var i = 0; i < select.options.length; i++) {
                              if (select.options[i].value === value) {
                                return true;
                              }
                            }
                            return false;
                          }

                          function getAllComunas() {
                            var all = [];
                            Object.keys(regionComunas).forEach(function(regionName) {
                              (regionComunas[regionName] || []).forEach(function(comunaName) {
                                if (all.indexOf(comunaName) === -1) {
                                  all.push(comunaName);
                                }
                              });
                            });
                            all.sort();
                            return all;
                          }

                          function fillSearchComunas(selectedValue) {
                            var region = regionSelect.value;
                            var comunas = region ? (regionComunas[region] || []) : getAllComunas();
                            comunaSelect.innerHTML = '';
                            addOption(comunaSelect, '', 'Todas');
                            comunas.forEach(function(name) {
                              addOption(comunaSelect, name, name);
                            });

                            if (selectedValue && hasOption(comunaSelect, selectedValue)) {
                              comunaSelect.value = selectedValue;
                            } else if (selectedValue) {
                              addOption(comunaSelect, selectedValue, selectedValue);
                              comunaSelect.value = selectedValue;
                            }
                          }

                          regionSelect.addEventListener('change', function() {
                            fillSearchComunas('');
                          });

                          fillSearchComunas(comunaSelect.getAttribute('data-current') || '');
                        })();
                      })();
                    </script>
                  </div>
                </div>

                <?php if ($viewRelator && $relatorActiveModule === 'buscar'): ?>
                  <div class="grid two detail-grid">
                    <div class="card">
                      <h4 class="section-title">Detalle del relator</h4>
                      <div class="info-grid">
                        <div><span class="info-label">Nombre</span><span><?php echo htmlspecialchars((string)($viewRelator[$relatorNameCol] ?? '')); ?></span></div>
                        <div><span class="info-label">Email</span><span><?php echo htmlspecialchars((string)($viewRelator['email'] ?? '')); ?></span></div>
                        <div><span class="info-label">Telefono</span><span><?php echo htmlspecialchars((string)($viewRelator['telefono'] ?? '')); ?></span></div>
                        <div><span class="info-label">SENCE</span><span><?php echo !empty($viewRelator['sence']) ? 'Si' : 'No'; ?></span></div>
                        <div><span class="info-label">Especialidades</span><span><?php echo htmlspecialchars((string)($viewRelator['especialidades'] ?? '')); ?></span></div>
                        <div><span class="info-label">Cursos</span><span><?php echo htmlspecialchars((string)($viewRelator['cursos'] ?? '')); ?></span></div>
                        <div><span class="info-label">Carrera</span><span><?php echo htmlspecialchars((string)($viewRelator['carrera'] ?? '')); ?></span></div>
                        <div><span class="info-label">Academico</span><span><?php echo htmlspecialchars((string)($viewRelator['academico'] ?? '')); ?></span></div>
                        <div><span class="info-label">Experiencia Relatores</span><span><?php echo !empty($viewRelator['experiencia_cc']) ? 'Si' : 'No'; ?></span></div>
                        <?php if (in_array('calificacion', $relatorCols, true)): ?>
                          <div><span class="info-label">Calificacion</span><span><?php echo isset($viewRelator['calificacion']) && $viewRelator['calificacion'] !== null ? htmlspecialchars((string)$viewRelator['calificacion']) : '-'; ?></span></div>
                        <?php endif; ?>
                        <?php if (in_array('sistema_reuf', $relatorCols, true)): ?>
                          <div><span class="info-label">Sistema REUF</span><span><?php
                                                                                  $reufVal = strtolower(trim((string)($viewRelator['sistema_reuf'] ?? '')));
                                                                                  echo $reufVal === 'si' ? 'Si' : ($reufVal === 'no' ? 'No' : '-');
                                                                                  ?></span></div>
                        <?php endif; ?>
                      </div>
                      <?php if ($canAdminArea && in_array('sence', $relatorCols, true)): ?>
                        <form method="post" class="stack compact">
                          <input type="hidden" name="form_type" value="relator_sence">
                          <input type="hidden" name="relator_id" value="<?php echo (int)($viewRelator[$relatorIdCol] ?? 0); ?>">
                          <input type="hidden" name="ui_module" value="buscar">
                          <div class="field">
                            <label>Actualizar SENCE</label>
                            <select name="sence">
                              <option value="1" <?php echo !empty($viewRelator['sence']) ? 'selected' : ''; ?>>Si</option>
                              <option value="0" <?php echo empty($viewRelator['sence']) ? 'selected' : ''; ?>>No</option>
                            </select>
                          </div>
                          <button class="btn" type="submit">Guardar</button>
                        </form>
                      <?php endif; ?>
                    </div>
                    <div class="card">
                      <h4 class="section-title">Documentos</h4>
                      <?php if ($canAdminArea): ?>
                        <form method="post" enctype="multipart/form-data" class="stack compact">
                          <input type="hidden" name="form_type" value="relator_documento">
                          <input type="hidden" name="relator_id" value="<?php echo (int)($viewRelator[$relatorIdCol] ?? 0); ?>">
                          <input type="hidden" name="ui_module" value="buscar">
                          <div class="grid two">
                            <div class="field">
                              <label>Titulo</label>
                              <input name="titulo" required>
                            </div>
                            <div class="field">
                              <label>Tipo</label>
                              <input name="tipo">
                            </div>
                            <div class="field full">
                              <label>Archivo</label>
                              <input type="file" name="archivo" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg">
                            </div>
                            <div class="field full">
                              <label>Ruta/URL</label>
                              <input name="ruta">
                            </div>
                          </div>
                          <button class="btn" type="submit">Agregar documento</button>
                        </form>
                      <?php endif; ?>
                      <?php if ($relatorDocs): ?>
                        <ul class="list-plain">
                          <?php foreach ($relatorDocs as $row): ?>
                            <li>
                              <strong><?php echo htmlspecialchars((string)$row['titulo']); ?></strong>
                              <span class="subtle"><?php echo htmlspecialchars((string)($row['tipo'] ?? '')); ?></span>
                              <?php if (!empty($row['ruta'])): ?>
                                <a class="link" href="<?php echo htmlspecialchars(asset($row['ruta'])); ?>" target="_blank" rel="noopener">Abrir</a>
                              <?php endif; ?>
                            </li>
                          <?php endforeach; ?>
                        </ul>
                      <?php else: ?>
                        <p class="subtle">Sin documentos asociados.</p>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endif; ?>
              <?php endif; ?>
            <?php elseif ($route === 'capacitaciones'): ?>
              <?php if (!$canAccessAdminArea): ?>
                <div class="notice">No tienes permisos para acceder a Administracion.</div>
              <?php else: ?>
                <?php
                $relatorIdCol = relator_id_column($db);
                $relatorNameCol = relator_name_column($db);
                $relatorCols = db_table_columns($db, 'relatores');
                $capCols = db_table_columns($db, 'capacitaciones');
                $messages = [];
                $errors = [];
                $formType = $_POST['form_type'] ?? '';

                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                  if (!$canAdminArea) {
                    $errors[] = "No tienes permisos para editar cursos.";
                  } elseif ($formType === 'curso_create') {
                    $title = trim((string)($_POST['title'] ?? ''));
                    $cliente = trim((string)($_POST['cliente'] ?? ''));
                    $executionDate = trim((string)($_POST['execution_date'] ?? ''));
                    $trainerId = (int)($_POST['trainer_id'] ?? 0);
                    $monitor = trim((string)($_POST['monitor'] ?? ''));
                    $lugar = trim((string)($_POST['lugar'] ?? ''));
                    $horas = (int)($_POST['horas'] ?? 0);
                    $description = trim((string)($_POST['description'] ?? ''));
                    $reportPath = store_uploaded_file($_FILES['report_file'] ?? [], 'cursos');
                    if (!$reportPath) {
                      $reportPath = trim((string)($_POST['report_path'] ?? ''));
                    }

                    if ($title === '') {
                      $errors[] = "El nombre del curso es obligatorio.";
                    } else {
                      $colValues = [
                        'title' => $title,
                        'cliente' => $cliente,
                        'execution_date' => $executionDate,
                        'trainer_id' => $trainerId ?: null,
                        'monitor' => $monitor,
                        'lugar' => $lugar,
                        'horas' => $horas ?: null,
                        'description' => $description,
                        'estado' => 'finalizado',
                        'report_path' => $reportPath,
                      ];
                      if ($executionDate !== '') {
                        if (in_array('start_date', $capCols, true)) {
                          $colValues['start_date'] = $executionDate;
                        }
                        if (in_array('end_date', $capCols, true)) {
                          $colValues['end_date'] = $executionDate;
                        }
                      }
                      $columns = [];
                      $placeholders = [];
                      $types = '';
                      $values = [];
                      foreach ($colValues as $col => $val) {
                        if (!in_array($col, $capCols, true)) {
                          continue;
                        }
                        if ($val === '' || $val === null) {
                          continue;
                        }
                        $columns[] = $col;
                        $placeholders[] = '?';
                        if (in_array($col, ['trainer_id', 'horas'], true)) {
                          $types .= 'i';
                          $values[] = (int)$val;
                        } else {
                          $types .= 's';
                          $values[] = (string)$val;
                        }
                      }
                      if ($columns) {
                        $stmt = $db->prepare("INSERT INTO capacitaciones (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")");
                        if ($stmt) {
                          $stmt->bind_param($types, ...$values);
                          if ($stmt->execute()) {
                            $messages[] = "Curso finalizado registrado.";
                            $courseId = $stmt->insert_id;
                            if ($reportPath) {
                              $docStmt = $db->prepare("INSERT INTO documentos (target_type, target_id, titulo, tipo, ruta, created_at) VALUES ('curso', ?, 'Informe', 'curso', ?, NOW())");
                              if ($docStmt) {
                                $docStmt->bind_param('is', $courseId, $reportPath);
                                $docStmt->execute();
                              }
                            }
                          } else {
                            $errors[] = "No se pudo guardar el curso.";
                          }
                        }
                      } else {
                        $errors[] = "No se pudieron mapear columnas para guardar el curso.";
                      }
                    }
                  }
                }

                $trainers = fetchOptions($db, 'relatores', $relatorNameCol, $relatorIdCol);
                $filterName = trim((string)($_GET['q'] ?? ''));
                $filterCliente = trim((string)($_GET['cliente'] ?? ''));
                $filterFecha = trim((string)($_GET['fecha'] ?? ''));
                $filterRelator = (int)($_GET['relator_id'] ?? 0);
                $filterSence = trim((string)($_GET['sence'] ?? ''));

                $filters = [];
                $params = [];
                $types = '';
                if ($filterName !== '') {
                  $filters[] = "t.title LIKE ?";
                  $params[] = '%' . $filterName . '%';
                  $types .= 's';
                }
                if ($filterCliente !== '' && in_array('cliente', $capCols, true)) {
                  $filters[] = "t.cliente LIKE ?";
                  $params[] = '%' . $filterCliente . '%';
                  $types .= 's';
                }
                if ($filterFecha !== '') {
                  $filters[] = "COALESCE(t.execution_date, t.end_date, t.start_date) = ?";
                  $params[] = $filterFecha;
                  $types .= 's';
                }
                if ($filterRelator > 0) {
                  $filters[] = "t.trainer_id = ?";
                  $params[] = $filterRelator;
                  $types .= 'i';
                }
                if ($filterSence !== '' && in_array('sence', $relatorCols, true)) {
                  $filters[] = "tr.sence = ?";
                  $params[] = $filterSence === 'si' ? 1 : 0;
                  $types .= 'i';
                }
                if (in_array('estado', $capCols, true)) {
                  $filters[] = "t.estado IN ('finalizado', 'completada')";
                }

                $senceSelect = in_array('sence', $relatorCols, true) ? "tr.sence AS trainer_sence" : "NULL AS trainer_sence";
                $sql = "SELECT t.*, tr." . $relatorNameCol . " AS trainer_name, " . $senceSelect
                  . " FROM capacitaciones t LEFT JOIN relatores tr ON tr." . $relatorIdCol . " = t.trainer_id";
                if ($filters) {
                  $sql .= " WHERE " . implode(" AND ", $filters);
                }
                $sql .= " ORDER BY COALESCE(t.execution_date, t.end_date, t.start_date) DESC, t.id DESC LIMIT 200";
                $stmt = $db->prepare($sql);
                if ($params) {
                  $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $rows = $stmt->get_result();
                ?>
                <div class="card">
                  <div class="card-header">
                    <div>
                      <h3>Cursos finalizados</h3>
                      <p class="subtle">Registro de cursos ejecutados con informes asociados.</p>
                    </div>
                    <span class="chip">Administracion</span>
                  </div>
                  <?php foreach ($messages as $message): ?>
                    <div class="notice"><?php echo htmlspecialchars($message); ?></div>
                  <?php endforeach; ?>
                  <?php foreach ($errors as $error): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                  <?php endforeach; ?>

                  <?php if ($canAdminArea): ?>
                    <form method="post" enctype="multipart/form-data" class="stack compact">
                      <input type="hidden" name="form_type" value="curso_create">
                      <div class="grid two">
                        <div class="field">
                          <label>Nombre del curso</label>
                          <input name="title" required>
                        </div>
                        <div class="field">
                          <label>Cliente/empresa</label>
                          <input name="cliente">
                        </div>
                        <div class="field">
                          <label>Fecha de ejecucion</label>
                          <input name="execution_date" type="date">
                        </div>
                        <div class="field">
                          <label>Relator</label>
                          <select name="trainer_id">
                            <option value="0">Sin asignar</option>
                            <?php foreach ($trainers as $id => $name): ?>
                              <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="field">
                          <label>Quien monitoriza</label>
                          <input name="monitor">
                        </div>
                        <div class="field">
                          <label>Lugar</label>
                          <input name="lugar">
                        </div>
                        <div class="field">
                          <label>Horas</label>
                          <input name="horas" type="number" min="0">
                        </div>
                        <div class="field full">
                          <label>Observaciones</label>
                          <textarea name="description"></textarea>
                        </div>
                        <div class="field">
                          <label>Informe (archivo)</label>
                          <input type="file" name="report_file" accept=".pdf,.doc,.docx,.xls,.xlsx">
                        </div>
                        <div class="field">
                          <label>Informe (ruta/URL)</label>
                          <input name="report_path">
                        </div>
                      </div>
                      <button class="btn" type="submit">Registrar curso finalizado</button>
                    </form>
                    <hr>
                  <?php else: ?>
                    <div class="notice">Solo lectura: no tienes permisos para editar.</div>
                  <?php endif; ?>

                  <form method="get" class="filters">
                    <h3>Buscador de cursos</h3>
                    <input type="hidden" name="route" value="capacitaciones">
                    <div class="grid two">
                      <div class="field">
                        <label>Curso</label>
                        <input name="q" value="<?php echo htmlspecialchars($filterName); ?>">
                      </div>
                      <div class="field">
                        <label>Cliente</label>
                        <input name="cliente" value="<?php echo htmlspecialchars($filterCliente); ?>">
                      </div>
                      <div class="field">
                        <label>Fecha</label>
                        <input name="fecha" type="date" value="<?php echo htmlspecialchars($filterFecha); ?>">
                      </div>
                      <div class="field">
                        <label>Relator</label>
                        <select name="relator_id">
                          <option value="0">Todos</option>
                          <?php foreach ($trainers as $id => $name): ?>
                            <option value="<?php echo $id; ?>" <?php echo $filterRelator === (int)$id ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="field">
                        <label>SENCE</label>
                        <select name="sence">
                          <option value="">Todos</option>
                          <option value="si" <?php echo $filterSence === 'si' ? 'selected' : ''; ?>>Si</option>
                          <option value="no" <?php echo $filterSence === 'no' ? 'selected' : ''; ?>>No</option>
                        </select>
                      </div>
                    </div>
                    <div class="filter-actions">
                      <button class="btn" type="submit">Aplicar filtros</button>
                      <a class="btn secondary" href="<?php echo htmlspecialchars(route_url('capacitaciones')); ?>">Limpiar</a>
                    </div>
                  </form>
                  <div style="display: block; width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; clear: both; margin-bottom: 20px;">
                    <table style="width: 100%; min-width: 600px; border-collapse: collapse;">
                      <thead>
                        <tr>
                          <th>ID</th>
                          <th>Curso</th>
                          <th>Cliente</th>
                          <th>Fecha</th>
                          <th>Relator</th>
                          <th>SENCE</th>
                          <th>Monitor</th>
                          <th>Informe</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if ($rows): while ($row = $rows->fetch_assoc()): ?>
                            <?php $fechaCurso = $row['execution_date'] ?? $row['end_date'] ?? $row['start_date'] ?? ''; ?>
                            <tr>
                              <td><?php echo (int)$row['id']; ?></td>
                              <td><?php echo htmlspecialchars((string)$row['title']); ?></td>
                              <td><?php echo htmlspecialchars((string)($row['cliente'] ?? '')); ?></td>
                              <td><?php echo htmlspecialchars((string)$fechaCurso); ?></td>
                              <td><?php echo htmlspecialchars((string)($row['trainer_name'] ?? '')); ?></td>
                              <td><?php echo !empty($row['trainer_sence']) ? 'Si' : 'No'; ?></td>
                              <td><?php echo htmlspecialchars((string)($row['monitor'] ?? '')); ?></td>
                              <td>
                                <?php if (!empty($row['report_path'])): ?>
                                  <a class="link" href="<?php echo htmlspecialchars(asset($row['report_path'])); ?>" target="_blank" rel="noopener">Abrir</a>
                                <?php else: ?>
                                  <span class="subtle">Sin informe</span>
                                <?php endif; ?>
                              </td>
                            </tr>
                        <?php endwhile;
                        endif; ?>
                      </tbody>
                    </table>
                 </div>
                </div>
              <?php endif; ?>
            <?php elseif ($route === 'sedes'): ?>
              <?php if (!$canAccessAdminArea): ?>
                <div class="notice">No tienes permisos para acceder a Administracion.</div>
              <?php else: ?>
                <?php
                $sedesData = sedes_build_data($db, [
                  'canManage' => $canAdminArea,
                  'canDelete' => $canAdmin,
                  'userId' => $user['id'] ?? null,
                ]);
                ?>
                <?php require __DIR__ . '/../app/Views/sedes/index.php'; ?>
              <?php endif; ?>
            <?php elseif ($route === 'contactos'): ?>
              <?php if (!$canAdmin): ?>
                <div class="notice">No tienes permisos para acceder a Contactos.</div>
              <?php else: ?>
                <?php
                $contactosData = contactos_build_data($dashboardConfigPath, [
                  'canManage' => $canAdmin,
                  'canDelete' => $canAdmin,
                  'userId' => $user['id'] ?? null,
                ]);
                ?>
                <?php require __DIR__ . '/../app/Views/contactos/index.php'; ?>
              <?php endif; ?>
            <?php elseif ($route === 'database'): ?>
              <?php if (!$canAdmin): ?>
                <div class="notice">No tienes permisos para acceder a la gestión de base de datos.</div>
              <?php else: ?>
                <?php
                $dbManagerData = db_manager_build_data($db, [
                  'canManage' => $canAdmin,
                  'canDelete' => $canAdmin,
                  'userId' => $user['id'] ?? null,
                ]);
                ?>
                <?php require __DIR__ . '/../app/Views/database/index.php'; ?>
              <?php endif; ?>
            <?php elseif ($route === 'perfil'): ?>
              <?php
              $profileMessages = [];
              $profileErrors = [];
              $profileForm = [
                'full_name' => trim((string)($user['full_name'] ?? '')),
                'email' => trim((string)($user['email'] ?? '')),
              ];

              if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $fullName = trim((string)($_POST['full_name'] ?? ''));
                $email = trim((string)($_POST['email'] ?? ''));
                $currentPassword = (string)($_POST['current_password'] ?? '');
                $newPassword = (string)($_POST['new_password'] ?? '');
                $wantsPasswordChange = ($newPassword !== '');
                $profileForm['full_name'] = $fullName;
                $profileForm['email'] = $email;

                if ($fullName === '') {
                  $profileErrors[] = "El nombre es obligatorio.";
                }
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                  $profileErrors[] = "Debes ingresar un email valido.";
                }
                if ($wantsPasswordChange && strlen($newPassword) < 8) {
                  $profileErrors[] = "La nueva clave debe tener al menos 8 caracteres.";
                }
                if ($wantsPasswordChange && $currentPassword === '') {
                  $profileErrors[] = "Debes ingresar tu clave actual para cambiarla.";
                }

                if (!$profileErrors) {
                  $emailStmt = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id <> ? LIMIT 1");
                  if ($emailStmt) {
                    $emailStmt->bind_param('si', $email, $user['id']);
                    $emailStmt->execute();
                    $existing = $emailStmt->get_result();
                    if ($existing && $existing->fetch_assoc()) {
                      $profileErrors[] = "Ese email ya esta en uso por otro usuario.";
                    }
                  } else {
                    $profileErrors[] = "No se pudo validar el email.";
                  }
                }

                if (!$profileErrors && $wantsPasswordChange) {
                  $hashStmt = $db->prepare("SELECT password_hash FROM usuarios WHERE id = ? LIMIT 1");
                  if ($hashStmt) {
                    $hashStmt->bind_param('i', $user['id']);
                    $hashStmt->execute();
                    $hashRow = $hashStmt->get_result()->fetch_assoc();
                    $validCurrentPassword = $hashRow && password_verify($currentPassword, (string)($hashRow['password_hash'] ?? ''));
                    if (!$validCurrentPassword) {
                      $profileErrors[] = "La clave actual no coincide.";
                    }
                  } else {
                    $profileErrors[] = "No se pudo validar la clave actual.";
                  }
                }

                if (!$profileErrors) {
                  $updateStmt = null;
                  if ($wantsPasswordChange) {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateStmt = $db->prepare("UPDATE usuarios SET full_name = ?, email = ?, password_hash = ? WHERE id = ? LIMIT 1");
                    if ($updateStmt) {
                      $updateStmt->bind_param('sssi', $fullName, $email, $newHash, $user['id']);
                    }
                  } else {
                    $updateStmt = $db->prepare("UPDATE usuarios SET full_name = ?, email = ? WHERE id = ? LIMIT 1");
                    if ($updateStmt) {
                      $updateStmt->bind_param('ssi', $fullName, $email, $user['id']);
                    }
                  }

                  if (!empty($updateStmt) && $updateStmt->execute()) {
                    $_SESSION['user']['full_name'] = $fullName;
                    $_SESSION['user']['email'] = $email;
                    $user = $_SESSION['user'];
                    $profileForm['full_name'] = $fullName;
                    $profileForm['email'] = $email;
                    $profileMessages[] = $wantsPasswordChange ? "Perfil actualizado y clave cambiada correctamente." : "Perfil actualizado correctamente.";
                  } else {
                    $profileErrors[] = "No se pudo guardar el perfil.";
                  }
                }
              }
              ?>
              <?php require __DIR__ . '/../app/Views/profile/index.php'; ?>
            <?php elseif ($route === 'usuarios'): ?>
              <?php if (!$canAdmin): ?>
                <div class="notice">No tienes permisos para acceder a usuarios.</div>
              <?php else: ?>
                <?php
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                  $fullName = trim((string)($_POST['full_name'] ?? ''));
                  $email = trim((string)($_POST['email'] ?? ''));
                  $password = (string)($_POST['password'] ?? '');
                  $roleKey = $_POST['role_key'] ?? 'read';
                  if ($fullName !== '' && $email !== '' && $password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO usuarios (full_name, email, password_hash, status, created_at) VALUES (?, ?, ?, 'active', NOW())");
                    $stmt->bind_param('sss', $fullName, $email, $hash);
                    if ($stmt->execute()) {
                      $userId = $stmt->insert_id;
                      $db->query("INSERT INTO usuarios_roles (user_id, role_id) SELECT " . (int)$userId . ", id FROM roles WHERE role_key = '" . $db->real_escape_string($roleKey) . "'");
                    }
                  }
                }
                $users = $db->query("SELECT u.id, u.full_name, u.email, u.status, GROUP_CONCAT(r.name SEPARATOR ', ') AS roles
            FROM usuarios u
            LEFT JOIN usuarios_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
            GROUP BY u.id
            ORDER BY u.id DESC LIMIT 100");
                ?>
                <div class="card">
                  <h3>Usuarios y Roles</h3>
                  <form method="post">
                    <div class="grid two">
                      <div class="field">
                        <label>Nombre</label>
                        <input name="full_name" required>
                      </div>
                      <div class="field">
                        <label>Email</label>
                        <input name="email" type="email" required>
                      </div>
                      <div class="field">
                        <label>Contrasena</label>
                        <input name="password" type="password" required>
                      </div>
                      <div class="field">
                        <label>Rol</label>
                        <select name="role_key">
                          <option value="admin">Encargado RRHH (admin)</option>
                          <option value="rrhh">RRHH operativo</option>
                          <option value="administracion">Administracion</option>
                          <option value="read">Lectura</option>
                        </select>
                      </div>
                    </div>
                    <button class="btn" type="submit">Crear usuario</button>
                  </form>
                  <hr>
                  <div style="display: block; width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; clear: both; margin-bottom: 20px;">
                    <table style="width: 100%; min-width: 600px; border-collapse: collapse;">
                      <thead>
                        <tr>
                          <th>ID</th>
                          <th>Nombre</th>
                          <th>Email</th>
                          <th>Roles</th>
                          <th>Estado</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if ($users): while ($row = $users->fetch_assoc()): ?>
                            <tr>
                              <td><?php echo (int)$row['id']; ?></td>
                              <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                              <td><?php echo htmlspecialchars($row['email']); ?></td>
                              <td><?php echo htmlspecialchars($row['roles'] ?? ''); ?></td>
                              <td><?php echo htmlspecialchars($row['status']); ?></td>
                            </tr>
                        <?php endwhile;
                        endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              <?php endif; ?>
            <?php elseif ($route === 'tickets'): ?>
              <?php
              $ticketIsAdmin = (bool)$isAdmin;
              require __DIR__ . '/../app/Views/tickets/index.php';
              ?>
            <?php elseif ($route === 'tareas'): ?>
              <?php require __DIR__ . '/../app/Views/tareas/index.php'; ?>
            <?php elseif ($route === 'calendario'): ?>
              <?php require __DIR__ . '/../app/Views/calendario/index.php'; ?>
            <?php elseif ($route === 'solicitudes'): ?>
              <?php if (!$canAdmin): ?>
                <div class="notice">No tienes permisos para acceder a solicitudes.</div>
              <?php else: ?>
                <?php
                $messages = [];
                $errors = [];
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                  $solicitudId = (int)($_POST['solicitud_id'] ?? 0);
                  $status = trim((string)($_POST['status'] ?? ''));
                  if ($solicitudId > 0 && in_array($status, ['aprobada', 'rechazada'], true)) {
                    $stmt = $db->prepare("UPDATE solicitudes_cambio SET status = ?, resolved_at = NOW() WHERE id = ?");
                    $stmt->bind_param('si', $status, $solicitudId);
                    if ($stmt->execute()) {
                      $messages[] = "Solicitud actualizada.";
                    } else {
                      $errors[] = "No se pudo actualizar la solicitud.";
                    }
                  }
                }
                $statusFilter = trim((string)($_GET['status'] ?? 'pendiente'));
                $params = [];
                $types = '';
                $sql = "SELECT s.id, s.modulo, s.accion, s.detalle, s.status, s.created_at, u.full_name, u.email
                        FROM solicitudes_cambio s
                        INNER JOIN usuarios u ON u.id = s.user_id";
                if ($statusFilter !== '' && in_array($statusFilter, ['pendiente', 'aprobada', 'rechazada'], true)) {
                  $sql .= " WHERE s.status = ?";
                  $params[] = $statusFilter;
                  $types .= 's';
                }
                $sql .= " ORDER BY s.created_at DESC";
                $stmt = $db->prepare($sql);
                if ($params) {
                  $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $requests = $stmt->get_result();
                ?>
                <div class="card">
                  <div class="card-header">
                    <div>
                      <h3>Solicitudes de cambio</h3>
                      <p class="subtle">Revisión y control de cambios solicitados por RRHH.</p>
                    </div>
                    <span class="chip">Admin</span>
                  </div>
                  <?php foreach ($messages as $message): ?>
                    <div class="notice"><?php echo htmlspecialchars($message); ?></div>
                  <?php endforeach; ?>
                  <?php foreach ($errors as $error): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                  <?php endforeach; ?>

                  <form method="get" class="filters">
                    <input type="hidden" name="route" value="solicitudes">
                    <div class="grid two">
                      <div class="field">
                        <label>Estado</label>
                        <select name="status">
                          <option value="">Todos</option>
                          <option value="pendiente" <?php echo $statusFilter === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                          <option value="aprobada" <?php echo $statusFilter === 'aprobada' ? 'selected' : ''; ?>>Aprobada</option>
                          <option value="rechazada" <?php echo $statusFilter === 'rechazada' ? 'selected' : ''; ?>>Rechazada</option>
                        </select>
                      </div>
                    </div>
                    <div class="filter-actions">
                      <button class="btn" type="submit">Filtrar</button>
                      <a class="btn secondary" href="<?php echo htmlspecialchars(route_url('solicitudes')); ?>">Limpiar</a>
                    </div>
                  </form>
                  <div style="display: block; width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; clear: both; margin-bottom: 20px;">
                    <table style="width: 100%; min-width: 600px; border-collapse: collapse;">
                      <thead>
                        <tr>
                          <th>Usuario</th>
                          <th>Modulo</th>
                          <th>Accion</th>
                          <th>Detalle</th>
                          <th>Estado</th>
                          <th>Fecha</th>
                          <th>Gestion</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if ($requests): while ($row = $requests->fetch_assoc()): ?>
                            <tr>
                              <td><?php echo htmlspecialchars($row['full_name']); ?><br><span class="subtle"><?php echo htmlspecialchars($row['email']); ?></span></td>
                              <td><?php echo htmlspecialchars($row['modulo']); ?></td>
                              <td><?php echo htmlspecialchars($row['accion']); ?></td>
                              <td><?php echo htmlspecialchars($row['detalle']); ?></td>
                              <td><?php echo htmlspecialchars($row['status']); ?></td>
                              <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                              <td>
                                <?php if ($row['status'] === 'pendiente'): ?>
                                  <form method="post" class="stack compact">
                                    <input type="hidden" name="solicitud_id" value="<?php echo (int)$row['id']; ?>">
                                    <select name="status">
                                      <option value="aprobada">Aprobar</option>
                                      <option value="rechazada">Rechazar</option>
                                    </select>
                                    <button class="btn secondary" type="submit">Actualizar</button>
                                  </form>
                                <?php else: ?>
                                  <span class="subtle">Gestionado</span>
                                <?php endif; ?>
                              </td>
                            </tr>
                        <?php endwhile;
                        endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              <?php endif; ?>
            <?php elseif ($route === 'herramientas'): ?>
              <?php if (!$canAccessRrhh && !$canAccessAdminArea): ?>
                <div class="notice">No tienes permisos para acceder a herramientas.</div>
              <?php else: ?>
                <?php
                $messages = [];
                $errors = [];

                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                  $formType = $_POST['form_type'] ?? '';
                  if ($formType === 'import_relatores') {
                    if (!$canAdminArea) {
                      $errors[] = "No tienes permisos para importar relatores.";
                    } else {
                      [$msgs, $errs] = import_relatores_csv($db, $_FILES['csv_file'] ?? []);
                      $messages = array_merge($messages, $msgs);
                      $errors = array_merge($errors, $errs);
                    }
                  }
                  if ($formType === 'import_cursos') {
                    if (!$canAdminArea) {
                      $errors[] = "No tienes permisos para importar cursos.";
                    } else {
                      [$msgs, $errs] = import_cursos_csv($db, $_FILES['csv_file'] ?? []);
                      $messages = array_merge($messages, $msgs);
                      $errors = array_merge($errors, $errs);
                    }
                  }
                }
                ?>
                <div class="stack">
                  <div class="card">
                    <div class="card-header">
                      <div>
                        <h3>Herramientas de administracion</h3>
                        <p class="subtle">Importaciones y exportaciones masivas para mantener el sistema.</p>
                      </div>
                      <span class="chip">Administracion</span>
                    </div>
                    <?php foreach ($messages as $message): ?>
                      <div class="notice"><?php echo htmlspecialchars($message); ?></div>
                    <?php endforeach; ?>
                    <?php foreach ($errors as $error): ?>
                      <div class="error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                  </div>

                    <?php if ($canAccessRrhh): ?>
                      <div class="card">
                        <h4 class="section-title">RRHH - Importaciones masivas</h4>
                        <p class="subtle">Usa el formato de practicantes para cargar datos en bloque.</p>
                        <form method="post" enctype="multipart/form-data" action="<?php echo htmlspecialchars(route_url('practicantes')); ?>" class="csv-upload">
                          <input type="hidden" name="form_type" value="practicantes_csv">
                        <label>CSV practicantes</label>
                          <div class="csv-row" style="width: 100%; min-width: 0; overflow: hidden;">
                            <div class="field" style="width: 100%; min-width: 0; max-width: 100%; overflow: hidden; box-sizing: border-box;">
                              <input type="file" name="csv_file" accept=".csv,text/csv" required style="width: 100%; max-width: 100%; box-sizing: border-box;">
                            </div>
                            <button class="btn" type="submit">Importar practicantes</button>
                            <a class="btn secondary" href="<?php echo htmlspecialchars(route_url('practicantes') . '&export=template'); ?>">Descargar plantilla</a>
                          </div>
                        </form>
                      </div>
                    <?php endif; ?>

                  <?php if ($canAccessAdminArea): ?>
                    <div class="card">
                      <h4 class="section-title">Administracion - Importaciones masivas</h4>
                      <div class="grid two">
                        <div>
                          <form method="post" enctype="multipart/form-data" class="csv-upload">
                            <input type="hidden" name="form_type" value="import_relatores">
                            <label>CSV relatores</label>
                            <div class="csv-row" style="width: 100%; min-width: 0; max-width: 100%; overflow: hidden; box-sizing: border-box;">
                              <div class="field" style="width: 100%; min-width: 0; max-width: 100%; overflow: hidden; box-sizing: border-box;">
                                <input type="file" name="csv_file" accept=".csv,text/csv" required style="width: 100%; max-width: 100%; box-sizing: border-box;">
                              </div>
                              <button class="btn" type="submit">Importar relatores</button>
                              <a class="btn secondary" href="<?php echo htmlspecialchars(route_url('herramientas') . '&export=relatores_template'); ?>">Plantilla relatores</a>
                            </div>
                          </form>
                        </div>
                        <div>
                          <form method="post" enctype="multipart/form-data" class="csv-upload">
                            <input type="hidden" name="form_type" value="import_cursos">
                          <label>CSV cursos finalizados</label>
                            <div class="csv-row" style="width: 100%; min-width: 0; max-width: 100%; overflow: hidden; box-sizing: border-box;">
                              <div class="field" style="width: 100%; min-width: 0; max-width: 100%; overflow: hidden; box-sizing: border-box;">
                                <input type="file" name="csv_file" accept=".csv,text/csv" required style="width: 100%; max-width: 100%; box-sizing: border-box;">
                              </div>
                              <button class="btn" type="submit">Importar cursos</button>
                              <a class="btn secondary" href="<?php echo htmlspecialchars(route_url('herramientas') . '&export=cursos_template'); ?>">Plantilla cursos</a>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                  <?php endif; ?>

                  <div class="card">
                    <h4 class="section-title">Exportaciones masivas</h4>
                    <div class="section-actions">
                      <a class="btn secondary" href="<?php echo htmlspecialchars(route_url('informes') . '&export=practicantes'); ?>">Exportar practicantes</a>
                      <a class="btn secondary" href="<?php echo htmlspecialchars(route_url('informes') . '&export=departamentos'); ?>">Exportar dotacion</a>
                      <a class="btn secondary" href="<?php echo htmlspecialchars(route_url('informes') . '&export=capacitaciones'); ?>">Exportar cursos</a>
                      <a class="btn secondary" href="<?php echo htmlspecialchars(route_url('informes') . '&export=relatores'); ?>">Exportar relatores</a>
                      <a class="btn secondary" href="<?php echo htmlspecialchars(route_url('informes') . '&export=relatores_reuf'); ?>">Exportar REUF</a>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            <?php elseif ($route === 'archivos'): ?>
              <?php if (!$canAccessRrhh && !$canAccessAdminArea): ?>
                <div class="notice">No tienes permisos para acceder a archivos compartidos.</div>
              <?php else: ?>
                <?php
                $messages = [];
                $errors = [];
                $canUpload = $canRrhhAdd || $canAdminArea;
                $canDelete = $canAdminArea;
                $userId = $user['id'] ?? null;

                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                  $formType = $_POST['form_type'] ?? '';
                  if ($formType === 'archivo_upload') {
                    if (!$canUpload) {
                      $errors[] = "No tienes permisos para subir archivos.";
                    } else {
                      $titulo = trim((string)($_POST['titulo'] ?? ''));
                      $descripcion = trim((string)($_POST['descripcion'] ?? ''));
                      $categoria = trim((string)($_POST['categoria'] ?? ''));
                      $originalName = $_FILES['archivo']['name'] ?? '';
                      $ruta = store_uploaded_file($_FILES['archivo'] ?? [], 'compartidos');
                      if (!$ruta) {
                        $errors[] = "No se pudo subir el archivo.";
                      } else {
                        if ($titulo === '') {
                          $titulo = $originalName !== '' ? $originalName : 'Archivo compartido';
                        }
                        $stmt = $db->prepare("INSERT INTO archivos_compartidos (titulo, descripcion, categoria, ruta, original_name, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                        if ($stmt) {
                          $stmt->bind_param('sssssi', $titulo, $descripcion, $categoria, $ruta, $originalName, $userId);
                          if ($stmt->execute()) {
                            $messages[] = "Archivo compartido agregado.";
                          } else {
                            $errors[] = "No se pudo guardar el archivo.";
                          }
                        } else {
                          $errors[] = "No se pudo preparar el guardado.";
                        }
                      }
                    }
                  }
                  if ($formType === 'archivo_delete') {
                    $archivoId = (int)($_POST['archivo_id'] ?? 0);
                    if ($archivoId > 0 && $canDelete) {
                      $stmt = $db->prepare("DELETE FROM archivos_compartidos WHERE id = ?");
                      $stmt->bind_param('i', $archivoId);
                      if ($stmt->execute()) {
                        $messages[] = "Archivo eliminado.";
                      } else {
                        $errors[] = "No se pudo eliminar el archivo.";
                      }
                    } elseif ($archivoId > 0 && $canUpload) {
                      $detail = "Solicitud de eliminacion archivo compartido #" . $archivoId;
                      if (function_exists('create_change_request')) {
                        create_change_request($db, $userId, 'archivos', 'eliminacion', $detail);
                        $messages[] = "Solicitud enviada al Encargado de RRHH.";
                      } else {
                        $errors[] = "No se pudo registrar la solicitud.";
                      }
                    } else {
                      $errors[] = "No tienes permisos para eliminar archivos.";
                    }
                  }
                }

                $filterName = trim((string)($_GET['q'] ?? ''));
                $filterCategory = trim((string)($_GET['categoria'] ?? ''));
                $filters = [];
                $params = [];
                $types = '';
                if ($filterName !== '') {
                  $filters[] = "(a.titulo LIKE ? OR a.descripcion LIKE ? OR a.original_name LIKE ?)";
                  $params[] = '%' . $filterName . '%';
                  $params[] = '%' . $filterName . '%';
                  $params[] = '%' . $filterName . '%';
                  $types .= 'sss';
                }
                if ($filterCategory !== '') {
                  $filters[] = "a.categoria LIKE ?";
                  $params[] = '%' . $filterCategory . '%';
                  $types .= 's';
                }
                $sql = "SELECT a.*, u.full_name AS uploader FROM archivos_compartidos a LEFT JOIN usuarios u ON u.id = a.uploaded_by";
                if ($filters) {
                  $sql .= " WHERE " . implode(" AND ", $filters);
                }
                $sql .= " ORDER BY a.created_at DESC LIMIT 200";
                $stmt = $db->prepare($sql);
                if ($params) {
                  $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $files = $stmt->get_result();
                ?>
                <div class="card">
                  <div class="card-header">
                    <div>
                      <h3>Archivos compartidos</h3>
                      <p class="subtle">Repositorio comun para documentos y recursos internos.</p>
                    </div>
                    <span class="chip">Compartido</span>
                  </div>
                  <?php foreach ($messages as $message): ?>
                    <div class="notice"><?php echo htmlspecialchars($message); ?></div>
                  <?php endforeach; ?>
                  <?php foreach ($errors as $error): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                  <?php endforeach; ?>

                  <?php if ($canUpload): ?>
                    <form method="post" enctype="multipart/form-data" class="stack compact">
                      <input type="hidden" name="form_type" value="archivo_upload">
                      <div class="grid two">
                        <div class="field">
                          <label>Titulo</label>
                          <input name="titulo" placeholder="Ej: Manual de procesos">
                        </div>
                        <div class="field">
                          <label>Categoria</label>
                          <input name="categoria" placeholder="Ej: RRHH, Administracion, Cursos">
                        </div>
                        <div class="field full">
                          <label>Descripcion</label>
                          <textarea name="descripcion" rows="2"></textarea>
                        </div>
                        <div class="field full">
                          <label>Archivo</label>
                          <input type="file" name="archivo" required>
                        </div>
                      </div>
                      <button class="btn" type="submit">Subir archivo</button>
                    </form>
                    <hr>
                  <?php else: ?>
                    <div class="notice">Solo lectura: no tienes permisos para subir archivos.</div>
                  <?php endif; ?>

                  <form method="get" class="filters">
                    <input type="hidden" name="route" value="archivos">
                    <div class="grid two">
                      <div class="field">
                        <label>Buscar</label>
                        <input name="q" value="<?php echo htmlspecialchars($filterName); ?>">
                      </div>
                      <div class="field">
                        <label>Categoria</label>
                        <input name="categoria" value="<?php echo htmlspecialchars($filterCategory); ?>">
                      </div>
                    </div>
                    <div class="filter-actions">
                      <button class="btn" type="submit">Aplicar filtros</button>
                      <a class="btn secondary" href="<?php echo htmlspecialchars(route_url('archivos')); ?>">Limpiar</a>
                    </div>
                  </form>
                  <div style="display: block; width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; clear: both; margin-bottom: 20px;">
                    <table style="width: 100%; min-width: 600px; border-collapse: collapse;">
                      <thead>
                        <tr>
                          <th>Titulo</th>
                          <th>Categoria</th>
                          <th>Subido por</th>
                          <th>Fecha</th>
                          <th>Archivo</th>
                          <th>Acciones</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if ($files): while ($row = $files->fetch_assoc()): ?>
                            <tr>
                              <td>
                                <strong><?php echo htmlspecialchars($row['titulo']); ?></strong>
                                <?php if (!empty($row['descripcion'])): ?>
                                  <div class="subtle"><?php echo htmlspecialchars($row['descripcion']); ?></div>
                                <?php endif; ?>
                              </td>
                              <td><?php echo htmlspecialchars($row['categoria'] ?? ''); ?></td>
                              <td><?php echo htmlspecialchars($row['uploader'] ?? ''); ?></td>
                              <td><?php echo htmlspecialchars($row['created_at'] ?? ''); ?></td>
                              <td>
                                <?php if (!empty($row['ruta'])): ?>
                                  <a class="link" href="<?php echo htmlspecialchars(asset($row['ruta'])); ?>" target="_blank" rel="noopener">Abrir</a>
                                <?php endif; ?>
                              </td>
                              <td>
                                <?php if ($canDelete): ?>
                                  <form method="post" onsubmit="return confirm('�1�71�1�771�1�71�1�7770�1�71�1�771�1�71�1�7777Eliminar archivo?');">
                                    <input type="hidden" name="form_type" value="archivo_delete">
                                    <input type="hidden" name="archivo_id" value="<?php echo (int)$row['id']; ?>">
                                    <button class="btn secondary" type="submit">Eliminar</button>
                                  </form>
                                <?php elseif ($canUpload): ?>
                                  <form method="post">
                                    <input type="hidden" name="form_type" value="archivo_delete">
                                    <input type="hidden" name="archivo_id" value="<?php echo (int)$row['id']; ?>">
                                    <button class="btn secondary" type="submit">Solicitar eliminacion</button>
                                  </form>
                                <?php else: ?>
                                  <span class="subtle">Solo lectura</span>
                                <?php endif; ?>
                              </td>
                            </tr>
                        <?php endwhile;
                        endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              <?php endif; ?>
            <?php elseif ($route === 'historial'): ?>
              <?php if (!$canAdmin): ?>
                <div class="notice">No tienes permisos para acceder al historial.</div>
              <?php else: ?>
                <?php
                $filterGeneral = trim((string)($_GET['q'] ?? ''));
                $filterTabla = trim((string)($_GET['tabla'] ?? ''));
                $filterAccion = trim((string)($_GET['accion'] ?? ''));
                $filterFechaDesde = trim((string)($_GET['fecha_desde'] ?? ''));
                $filterFechaHasta = trim((string)($_GET['fecha_hasta'] ?? ''));
                $historialRows = null;
                $historialCount = 0;
                $historialError = '';
                $tablasDisponibles = [];

                if (!db_table_exists($db, 'historial')) {
                  $historialError = "La tabla historial no existe en esta base de datos. La vista se muestra, pero todavía no hay registros de auditoría disponibles.";
                } else {
                  $historialCols = db_table_columns($db, 'historial');
                  $requiredHistorialCols = ['id_historial', 'usuario_id', 'fecha'];
                  if (array_diff($requiredHistorialCols, $historialCols)) {
                    $historialError = "La tabla historial existe, pero su estructura no coincide con la que necesita esta sección.";
                  } else {
                    if (in_array('tabla_afectada', $historialCols, true)) {
                      $tablasRes = $db->query("SELECT DISTINCT tabla_afectada FROM historial WHERE tabla_afectada IS NOT NULL AND TRIM(tabla_afectada) <> '' ORDER BY tabla_afectada ASC LIMIT 100");
                      if ($tablasRes) {
                        while ($tablaRow = $tablasRes->fetch_assoc()) {
                          $tablasDisponibles[] = (string)$tablaRow['tabla_afectada'];
                        }
                      }
                    }

                    $filters = [];
                    $params = [];
                    $types = '';
                    if ($filterGeneral !== '') {
                      $searchCols = array_values(array_intersect(['tabla_afectada', 'accion', 'descripcion'], $historialCols));
                      if ($searchCols) {
                        $tokens = preg_split('/\s+/', $filterGeneral, -1, PREG_SPLIT_NO_EMPTY);
                        foreach ($tokens as $token) {
                          $tokenFilters = [];
                          foreach ($searchCols as $col) {
                            $tokenFilters[] = "h." . $col . " LIKE ?";
                            $params[] = '%' . $token . '%';
                            $types .= 's';
                          }
                          if ($tokenFilters) {
                            $filters[] = '(' . implode(' OR ', $tokenFilters) . ')';
                          }
                        }
                      }
                    }
                    if ($filterTabla !== '' && in_array('tabla_afectada', $historialCols, true)) {
                      $filters[] = "h.tabla_afectada = ?";
                      $params[] = $filterTabla;
                      $types .= 's';
                    }
                    if ($filterAccion !== '' && in_array('accion', $historialCols, true)) {
                      $filters[] = "UPPER(h.accion) = ?";
                      $params[] = strtoupper($filterAccion);
                      $types .= 's';
                    }
                    if ($filterFechaDesde !== '') {
                      $filters[] = "DATE(h.fecha) >= ?";
                      $params[] = $filterFechaDesde;
                      $types .= 's';
                    }
                    if ($filterFechaHasta !== '') {
                      $filters[] = "DATE(h.fecha) <= ?";
                      $params[] = $filterFechaHasta;
                      $types .= 's';
                    }

                    $sql = "SELECT h.*, u.full_name AS usuario_nombre
                            FROM historial h
                            LEFT JOIN usuarios u ON u.id = h.usuario_id";
                    if ($filters) {
                      $sql .= " WHERE " . implode(" AND ", $filters);
                    }
                    $sql .= " ORDER BY h.fecha DESC, h.id_historial DESC LIMIT 200";
                    $stmt = $db->prepare($sql);
                    if (!$stmt) {
                      $historialError = "No se pudo preparar la consulta del historial. Revisa la estructura de la base de datos.";
                    } else {
                      try {
                        if ($params) {
                          $stmt->bind_param($types, ...$params);
                        }
                        $stmt->execute();
                        $historialRows = $stmt->get_result();
                        $historialCount = $historialRows ? (int)$historialRows->num_rows : 0;
                      } catch (Throwable $e) {
                        $historialError = "No se pudo cargar el historial. Revisa la estructura de la base de datos.";
                      }
                      $stmt->close();
                    }
                  }
                }
                ?>
                <?php require __DIR__ . '/../app/Views/historial/index.php'; ?>
              <?php endif; ?>
            <?php elseif ($route === 'informes'): ?>
              <?php if (!$canAccessAdminArea): ?>
                <div class="notice">No tienes permisos para acceder a Administracion.</div>
              <?php else: ?>
                <?php
                $relatorIdCol = relator_id_column($db);
                $relatorNameCol = relator_name_column($db);
                $countEmployees = $db->query("SELECT COUNT(*) AS total FROM practicantes");
                $totalEmployees = $countEmployees ? (int)$countEmployees->fetch_assoc()['total'] : 0;
                $countTrainings = $db->query("SELECT COUNT(*) AS total FROM capacitaciones");
                $totalTrainings = $countTrainings ? (int)$countTrainings->fetch_assoc()['total'] : 0;
                $reportStats = $db->query("SELECT COUNT(DISTINCT t.id) AS total
                FROM capacitaciones t
                LEFT JOIN documentos d ON d.target_type = 'curso' AND d.target_id = t.id
                WHERE (t.report_path IS NOT NULL AND t.report_path <> '') OR d.id IS NOT NULL");
                $reportsTotal = $reportStats ? (int)$reportStats->fetch_assoc()['total'] : 0;
                $reportsPending = max(0, $totalTrainings - $reportsTotal);
                $byDepartment = $db->query("SELECT d.name, COUNT(e.id) AS total
            FROM departamentos d
            LEFT JOIN practicantes e ON e.department_id = d.id
            GROUP BY d.id ORDER BY total DESC");
                $byTrainer = $db->query("SELECT t." . $relatorNameCol . " AS full_name, COUNT(tr.id) AS total
            FROM relatores t
            LEFT JOIN capacitaciones tr ON tr.trainer_id = t." . $relatorIdCol . "
            GROUP BY t." . $relatorIdCol . " ORDER BY total DESC LIMIT 10");
                $recentCourses = $db->query("SELECT t.title, COALESCE(t.execution_date, t.end_date, t.start_date) AS fecha, tr." . $relatorNameCol . " AS relator
            FROM capacitaciones t
            LEFT JOIN relatores tr ON tr." . $relatorIdCol . " = t.trainer_id
            ORDER BY fecha DESC LIMIT 8");
                ?>
                <div class="stack">
                  <div class="grid two">
                    <div class="card">
                      <h3>Indicadores RRHH</h3>
                      <p>Total empleados: <?php echo $totalEmployees; ?></p>
                      <div class="section-actions">
                        <a class="btn secondary" href="<?php echo htmlspecialchars(route_url('informes') . '&export=practicantes'); ?>">Exportar practicantes</a>
                        <a class="btn secondary" href="<?php echo htmlspecialchars(route_url('informes') . '&export=departamentos'); ?>">Exportar dotacion</a>
                      </div>
                    </div>
                    <div class="card">
                      <h3>Indicadores Cursos finalizados</h3>
                      <p>Total cursos: <?php echo $totalTrainings; ?></p>
                      <p>Informes ingresados: <?php echo $reportsTotal; ?></p>
                      <p>Informes pendientes: <?php echo $reportsPending; ?></p>
                      <div class="section-actions">
                        <a class="btn secondary" href="<?php echo htmlspecialchars(route_url('informes') . '&export=capacitaciones'); ?>">Exportar capacitaciones</a>
                        <a class="btn secondary" href="<?php echo htmlspecialchars(route_url('informes') . '&export=relatores'); ?>">Exportar relatores</a>
                        <a class="btn secondary" href="<?php echo htmlspecialchars(route_url('informes') . '&export=relatores_reuf'); ?>">Exportar REUF</a>
                      </div>
                    </div>
                  </div>
                  <div class="grid two">
                    <div class="card">
                      <h4 class="section-title">Dotacion por departamento</h4>
                      <table>
                        <thead>
                          <tr>
                            <th>Departamento</th>
                            <th>Total</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php if ($byDepartment): while ($row = $byDepartment->fetch_assoc()): ?>
                              <tr>
                                <td><?php echo htmlspecialchars($row['name'] ?? 'Sin asignar'); ?></td>
                                <td><?php echo (int)$row['total']; ?></td>
                              </tr>
                          <?php endwhile;
                          endif; ?>
                        </tbody>
                      </table>
                    </div>
                    <div class="card">
                      <h4 class="section-title">Relatores activos</h4>
                      <table>
                        <thead>
                          <tr>
                            <th>Relator</th>
                            <th>Total</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php if ($byTrainer): while ($row = $byTrainer->fetch_assoc()): ?>
                              <tr>
                                <td><?php echo htmlspecialchars($row['full_name'] ?? 'Sin relator'); ?></td>
                                <td><?php echo (int)$row['total']; ?></td>
                              </tr>
                          <?php endwhile;
                          endif; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                  <div class="grid two">
                    <div class="card">
                      <h4 class="section-title">Cursos recientes finalizados</h4>
                      <table>
                        <thead>
                          <tr>
                            <th>Curso</th>
                            <th>Fecha</th>
                            <th>Relator</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php if ($recentCourses): while ($row = $recentCourses->fetch_assoc()): ?>
                              <tr>
                                <td><?php echo htmlspecialchars($row['title'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['fecha'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['relator'] ?? ''); ?></td>
                              </tr>
                          <?php endwhile;
                          endif; ?>
                        </tbody>
                      </table>
                    </div>
                    <div class="card">
                      <h4 class="section-title">Informes pendientes/ingresados</h4>
                      <p>Ingresados: <?php echo $reportsTotal; ?></p>
                      <p>Pendientes: <?php echo $reportsPending; ?></p>
                      <p class="subtle">Basado en cursos con informe asociado o documento adjunto.</p>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            <?php else: ?>
              <div class="notice">Ruta no encontrada.</div>
            <?php endif; ?>
          </div>

        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
  <?php if (in_array($route, ['login', 'forgot'], true)): ?>
    <!-- background replaced with static SVG; no JS required -->
  <?php endif; ?>
  <?php if (!empty($frameworkAssets['js'])): ?>
    <script src="<?php echo htmlspecialchars($frameworkAssets['js']); ?>"></script>
  <?php endif; ?>
  <script src="<?php echo htmlspecialchars($appJsUrl); ?>"></script>
</body>

</html>
