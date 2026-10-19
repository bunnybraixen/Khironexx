<aside class="sidebar" id="sidebar" aria-label="Menu principal">
  <div class="sidebar-head">
    <a class="sidebar-brand" href="<?php echo htmlspecialchars(route_url('dashboard')); ?>" aria-label="Ir al dashboard">
      <span class="sidebar-brand-mark" aria-hidden="true">
        <i class="fa-solid fa-wave-square"></i>
      </span>
      <span class="sidebar-brand-text">Khironex</span>
    </a>
    <button class="sidebar-toggle" type="button" id="sidebar-toggle" aria-label="Colapsar menu lateral" aria-controls="sidebar">
      <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>
  </div>
  <?php
  $routeIcons = [
    'usuarios' => 'fa-solid fa-sliders',
    'database' => 'fa-solid fa-database',
    'dashboard' => 'fa-solid fa-chart-line',
    'contactos' => 'fa-solid fa-address-book',
    'postulantes' => 'fa-solid fa-user-plus',
    'practicantes' => 'fa-solid fa-users',
    'relatores' => 'fa-solid fa-chalkboard-user',
    'capacitaciones' => 'fa-solid fa-graduation-cap',
    'sedes' => 'fa-solid fa-map-location-dot',
    'informes' => 'fa-solid fa-file-lines',
    'solicitudes' => 'fa-solid fa-inbox',
    'tickets' => 'fa-solid fa-headset',
    'tareas' => 'fa-solid fa-list-check',
    'herramientas' => 'fa-solid fa-screwdriver-wrench',
    'archivos' => 'fa-solid fa-folder-open',
    'historial' => 'fa-solid fa-clock-rotate-left',
  ];
  ?>
  <nav class="sidebar-nav" aria-label="Secciones del dashboard">
    <?php foreach ($dashboardMenu as $item): ?>
      <?php
      $label = $item['label'] ?? '';
      $target = $item['route'] ?? '';
      $url = $target ? route_url($target) : '#';
      $active = $target === $route;
      $icon = $item['icon'] ?? ($routeIcons[$target] ?? 'fa-solid fa-circle');
      ?>
      <a class="sidebar-link <?php echo $active ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($url); ?>" title="<?php echo htmlspecialchars($label); ?>" aria-label="<?php echo htmlspecialchars($label); ?>" data-label="<?php echo htmlspecialchars($label); ?>">
        <span class="sidebar-icon" aria-hidden="true">
          <i class="<?php echo htmlspecialchars($icon); ?>"></i>
        </span>
        <span class="sidebar-text"><?php echo htmlspecialchars($label); ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-logo">
    <img class="brand-locked" data-brand-lock="sidebar-logo" draggable="false" src="<?php echo htmlspecialchars(asset('../img/Logo-wide.png')); ?>" alt="Khironex">
  </div>
</aside>
<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>
