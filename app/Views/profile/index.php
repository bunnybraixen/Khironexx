<?php
$profileMessages = is_array($profileMessages ?? null) ? $profileMessages : [];
$profileErrors = is_array($profileErrors ?? null) ? $profileErrors : [];
$profileForm = is_array($profileForm ?? null) ? $profileForm : [
  'full_name' => (string)($user['full_name'] ?? ''),
  'email' => (string)($user['email'] ?? ''),
];
?>
<div class="card profile-card">
  <div class="card-header">
    <div>
      <h3>Editar perfil</h3>
      <p class="subtle">Actualiza tu nombre, email y tu clave de acceso.</p>
    </div>
    <span class="chip">Cuenta</span>
  </div>

  <?php foreach ($profileMessages as $message): ?>
    <div class="notice"><?php echo htmlspecialchars((string)$message); ?></div>
  <?php endforeach; ?>

  <?php foreach ($profileErrors as $error): ?>
    <div class="error"><?php echo htmlspecialchars((string)$error); ?></div>
  <?php endforeach; ?>

  <form method="post" class="stack compact">
    <div class="grid two">
      <div class="field">
        <label for="profile_full_name">Nombre completo</label>
        <input id="profile_full_name" name="full_name" required value="<?php echo htmlspecialchars((string)($profileForm['full_name'] ?? '')); ?>" autocomplete="name">
      </div>
      <div class="field">
        <label for="profile_email">Email</label>
        <input id="profile_email" name="email" type="email" required value="<?php echo htmlspecialchars((string)($profileForm['email'] ?? '')); ?>" autocomplete="email">
      </div>
      <div class="field">
        <label for="profile_current_password">Clave actual</label>
        <input id="profile_current_password" name="current_password" type="password" autocomplete="current-password">
      </div>
      <div class="field">
        <label for="profile_new_password">Nueva clave (opcional)</label>
        <input id="profile_new_password" name="new_password" type="password" autocomplete="new-password" minlength="8">
      </div>
    </div>
    <p class="subtle">Si no quieres cambiar la clave, deja vacios los dos campos de clave.</p>
    <button class="btn" type="submit">Guardar cambios</button>
  </form>
</div>
