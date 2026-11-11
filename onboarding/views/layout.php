<?php
$onboardingPages = [
    'dashboard' => ['label' => 'Dashboard', 'icon' => 'fa-chart-pie'],
    'procesos' => ['label' => 'Procesos de onboarding', 'icon' => 'fa-diagram-project'],
    'detalle' => ['label' => 'Detalle del proceso', 'icon' => 'fa-address-card'],
    'tareas' => ['label' => 'Tareas', 'icon' => 'fa-list-check'],
    'biblioteca' => ['label' => 'Biblioteca', 'icon' => 'fa-folder-open'],
    'plan-30-60-90' => ['label' => 'Plan 30-60-90', 'icon' => 'fa-calendar-check'],
];
$onboardingTitle = $onboardingPages[$onboardingPage]['label'];
$onboardingCssPath = __DIR__ . '/../assets/onboarding.css';
$onboardingJsPath = __DIR__ . '/../assets/onboarding.js';
$onboardingEscape = function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $onboardingEscape($onboardingTitle); ?> | Khironex</title>
  <link rel="stylesheet" href="../web/assets/app.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/onboarding.css?v=<?php echo (int)@filemtime($onboardingCssPath); ?>">
</head>
<body class="dashboard-body onboarding-body">
  <div class="brand-watermark" aria-hidden="true"></div>
  <div class="onboarding-shell">
    <aside class="onboarding-sidebar" id="onboarding-sidebar">
      <a class="onboarding-brand" href="?page=dashboard">
        <span class="onboarding-brand-mark"><i class="fa-solid fa-wave-square" aria-hidden="true"></i></span>
        <span><strong>Khironex</strong><small>PERSONAS · ONBOARDING</small></span>
      </a>
      <div class="onboarding-nav-label">ESPACIO DE TRABAJO</div>
      <nav class="onboarding-nav" aria-label="Navegación de onboarding">
        <?php foreach ($onboardingPages as $pageKey => $pageInfo): ?>
          <a class="onboarding-nav-link<?php echo $onboardingPage === $pageKey ? ' is-active' : ''; ?>" href="?page=<?php echo rawurlencode($pageKey); ?>"<?php echo $onboardingPage === $pageKey ? ' aria-current="page"' : ''; ?>>
            <i class="fa-solid <?php echo $onboardingEscape($pageInfo['icon']); ?>" aria-hidden="true"></i>
            <span><?php echo $onboardingEscape($pageInfo['label']); ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="onboarding-sidebar-foot">
        <span class="onboarding-status-dot"></span>
        <span>Conectado a Khironex</span>
      </div>
    </aside>
    <div class="onboarding-main">
      <header class="onboarding-topbar">
        <button class="onboarding-menu-toggle" type="button" data-onboarding-menu aria-label="Abrir navegación" aria-expanded="false">
          <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>
        <div class="onboarding-breadcrumb"><a href="../web/">Khironex</a><span>/</span><strong><?php echo $onboardingEscape($onboardingTitle); ?></strong></div>
        <div class="onboarding-user">
          <span class="onboarding-avatar"><?php echo $onboardingEscape(strtoupper(substr((string)($onboardingUser['full_name'] ?? 'U'), 0, 1))); ?></span>
          <span><?php echo $onboardingEscape($onboardingUser['full_name'] ?? 'Usuario'); ?></span>
          <a href="../web/" aria-label="Volver al sistema principal" title="Volver al sistema principal"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
        </div>
      </header>
      <main class="onboarding-content">
        <div class="onboarding-page-heading">
          <div>
            <p class="onboarding-eyebrow">GESTIÓN DE PERSONAS</p>
            <h1><?php echo $onboardingEscape($onboardingTitle); ?></h1>
          </div>
          <?php if (!$onboardingData['storageReady']): ?>
            <span class="onboarding-phase"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Estructura inicial</span>
          <?php endif; ?>
        </div>
        <?php require __DIR__ . '/pages/' . $onboardingPage . '.php'; ?>
      </main>
    </div>
  </div>
  <script src="assets/onboarding.js?v=<?php echo (int)@filemtime($onboardingJsPath); ?>" defer></script>
</body>
</html>