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
    <div class="user-dropdown notification-dropdown" data-user-dropdown>
      <button class="user-trigger notification-trigger" type="button" data-user-trigger aria-label="Notificaciones" aria-haspopup="true" aria-expanded="false" aria-controls="notification-menu">
        <i class="fa-regular fa-bell" aria-hidden="true"></i>
        <?php if (!empty($notificationUnreadCount)): ?>
          <span class="notification-badge" aria-label="<?php echo (int)$notificationUnreadCount; ?> sin leer"><?php echo $notificationUnreadCount > 9 ? '9+' : (int)$notificationUnreadCount; ?></span>
        <?php endif; ?>
      </button>
      <div class="user-menu notification-menu" id="notification-menu" data-user-menu>
        <div class="notification-menu-header">
          <strong>Notificaciones</strong>
          <?php if (!empty($notificationUnreadCount)): ?>
            <span><?php echo (int)$notificationUnreadCount; ?> sin leer</span>
          <?php endif; ?>
        </div>
        <?php if (!empty($notificationFlash)): ?>
          <div class="notification-empty"><?php echo htmlspecialchars($notificationFlash, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if (empty($notificationsAvailable)): ?>
          <div class="notification-empty">Para habilitar el centro, aplica <code>sql/notificaciones_migration.sql</code> en la base local.</div>
        <?php elseif (!empty($notificationRows)): ?>
          <div class="notification-list">
            <?php foreach ($notificationRows as $notification): ?>
              <?php $notificationDate = !empty($notification['created_at']) ? date('d/m H:i', strtotime((string)$notification['created_at'])) : ''; ?>
              <form class="notification-form" method="post" action="<?php echo htmlspecialchars(route_url($route), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($notificationCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="notificacion_action" value="open">
                <input type="hidden" name="notificacion_id" value="<?php echo (int)$notification['id']; ?>">
                <button class="notification-item<?php echo empty($notification['read_at']) ? ' is-unread' : ''; ?>" type="submit">
                  <span class="notification-item-title"><?php echo htmlspecialchars((string)$notification['titulo'], ENT_QUOTES, 'UTF-8'); ?></span>
                  <span class="notification-item-message"><?php echo htmlspecialchars((string)$notification['mensaje'], ENT_QUOTES, 'UTF-8'); ?></span>
                  <time class="notification-item-date"><?php echo htmlspecialchars($notificationDate, ENT_QUOTES, 'UTF-8'); ?></time>
                </button>
              </form>
            <?php endforeach; ?>
          </div>
          <?php if (!empty($notificationUnreadCount)): ?>
            <form class="notification-mark-all" method="post" action="<?php echo htmlspecialchars(route_url($route), ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($notificationCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="notificacion_action" value="read_all">
              <button type="submit">Marcar todas como leídas</button>
            </form>
          <?php endif; ?>
        <?php else: ?>
          <div class="notification-empty">No tienes notificaciones por ahora.</div>
        <?php endif; ?>
      </div>
    </div>
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
