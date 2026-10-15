<div class="topbar">
  <div class="topbar-left header-bar">
    <button class="mobile-menu-btn" type="button" id="sidebar-mobile-toggle" aria-label="Abrir menu lateral" aria-controls="sidebar" aria-expanded="false">
      <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>
    <a class="topbar-back" href="javascript:history.back()" title="Volver atras" aria-label="Volver atras">
      <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
    <div>
      <div class="topbar-title"><?php echo htmlspecialchars($currentLabel); ?></div>
      <div class="topbar-path">
        <span>Inicio</span>
        <span class="topbar-sep">/</span>
        <span><?php echo htmlspecialchars($currentLabel); ?></span>
      </div>
    </div>
  </div>
  <div class="topbar-right">
    <div class="user-dropdown" data-user-dropdown>
      <button class="user-trigger" type="button" data-user-trigger aria-label="Plantillas" aria-haspopup="true" aria-expanded="false">
        <i class="fas fa-file-alt" aria-hidden="true"></i>
        <span class="user-name">Plantillas</span>
        <i class="fas fa-chevron-down user-trigger-chevron" aria-hidden="true"></i>
      </button>
      <div class="user-menu" data-user-menu>
        <a class="user-menu-item" href="<?php echo htmlspecialchars(url_path('documentos/PlantillaInforme.docx')); ?>">
          <i class="fas fa-file-word" aria-hidden="true"></i> Plantilla Informe
        </a>
      </div>
    </div>
    <div class="user-dropdown" data-user-dropdown>
      <button class="user-trigger" type="button" data-user-trigger aria-label="Abrir menu de usuario" aria-haspopup="true" aria-expanded="false" aria-controls="user-menu">
        <span class="user-avatar">
          <?php
          $initials = substr($user['full_name'] ?? 'U', 0, 1);
          echo htmlspecialchars(strtoupper($initials));
          ?>
        </span>
        <span class="user-name"><?php echo htmlspecialchars($user['full_name'] ?? 'Usuario'); ?></span>
        <i class="fas fa-chevron-down user-trigger-chevron" aria-hidden="true"></i>
      </button>
      <div class="user-menu" id="user-menu" data-user-menu>
        <a class="user-menu-item" href="<?php echo htmlspecialchars(route_url('perfil')); ?>">
          <i class="fas fa-user-circle" aria-hidden="true"></i> Editar perfil
        </a>
        <form class="user-menu-form" method="post" action="<?php echo htmlspecialchars(route_url('logout')); ?>">
          <button class="user-menu-item danger" type="submit">
            <i class="fas fa-sign-out-alt" aria-hidden="true"></i> Cerrar sesion
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
