<div class="login-page" id="login-page">
  <div class="svg-bg"></div>
  <div class="login-shell">
    <div class="login-card">
      <div class="login-form">
        <div class="login-logo">
          <img class="brand-locked" data-brand-lock="forgot-main-logo" draggable="false" src="<?php echo htmlspecialchars(asset('../img/logo.png')); ?>" alt="Khironex">
        </div>
        <h3>Recuperar contrasena</h3>
        <?php if (!empty($forgotMessage)): ?>
          <div class="success"><?php echo htmlspecialchars($forgotMessage); ?></div>
        <?php else: ?>
          <p class="login-help">Ingresa tu email y te enviaremos un enlace de recuperacion.</p>
        <?php endif; ?>
        <form method="post" action="<?php echo htmlspecialchars(route_url('forgot')); ?>">
          <div class="grid">
            <div class="field">
              <label>📧 Email</label>
              <input name="email" type="email" required>
            </div>
          </div>
          <div class="login-actions">
            <button class="btn primary" type="submit">Enviar enlace</button>
          </div>
          <a class="login-link" href="<?php echo htmlspecialchars(route_url()); ?>">Volver al login</a>
        </form>
      </div>
    </div>
  </div>
</div>