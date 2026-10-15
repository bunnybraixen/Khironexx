<div class="login-page" id="login-page">
  <div class="svg-bg"></div>
  <div class="login-shell">
    <div class="login-card">
      <div class="login-form">
        <div class="login-logo">
          <img class="brand-locked" data-brand-lock="login-main-logo" draggable="false" src="<?php echo htmlspecialchars(asset('../img/logo.png')); ?>" alt="Khironex">
        </div>
        <h3>Iniciar sesion</h3>
        <?php if (!empty($loginError)): ?>
          <div class="error"><?php echo htmlspecialchars($loginError); ?></div>
        <?php endif; ?>
        <form method="post" action="<?php echo htmlspecialchars(route_url('login')); ?>">
          <div class="grid">
            <div class="field">
              <label>📧 Usuario o Email</label>
              <input name="email" type="text" required>
            </div>
            <div class="field">
              <label>🔑 Contrasena</label>
              <input name="password" type="password" required>
            </div>
          </div>
          <div class="login-actions">
            <button class="btn primary" type="submit">Acceder</button>
          </div>
          <a class="login-link" href="<?php echo htmlspecialchars(route_url('forgot')); ?>">Has extraviado la contrasena?</a>
          <div class="login-footer">
            <button class="btn secondary" type="button" data-cookie-open aria-controls="cookie-modal" aria-expanded="false">Aviso de Cookies</button>
            <img class="login-footer-logo brand-locked" data-brand-lock="login-footer-logo" draggable="false" src="<?php echo htmlspecialchars(asset('../img/logo_consult.png')); ?>" alt="Consultores">
          </div>
        </form>
      </div>
    </div>
  </div>
  <div class="cookie-modal" id="cookie-modal" data-cookie-modal hidden aria-hidden="true">
    <div class="cookie-modal-backdrop" data-cookie-close></div>
    <div class="cookie-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="cookie-modal-title" tabindex="-1">
      <button class="cookie-modal-close" type="button" data-cookie-close aria-label="Cerrar aviso de cookies">&times;</button>
      <h4 id="cookie-modal-title">Aviso de Cookies</h4>
      <p>Usamos cookies tecnicas para iniciar sesion, mantener seguridad y recordar preferencias basicas del sistema.</p>
      <p>No utilizamos cookies de marketing en este portal interno.</p>
      <div class="cookie-modal-actions">
        <button class="btn secondary" type="button" data-cookie-close>Aceptar</button>
      </div>
    </div>
  </div>
</div>