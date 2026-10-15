<?php
$messages = $contactosData['messages'] ?? [];
$errors = $contactosData['errors'] ?? [];
$contacts = $contactosData['contacts'] ?? [];
$canManage = (bool)($contactosData['canManage'] ?? false);
$canDelete = (bool)($contactosData['canDelete'] ?? false);
$editIndex = $contactosData['editIndex'] ?? null;
$editContact = $contactosData['editContact'] ?? null;
$draft = $contactosData['draft'] ?? [];

$isEditing = ($editIndex !== null && is_array($editContact));

$formValue = function ($key, $default = '') use ($isEditing, $editContact, $draft) {
  if (!empty($draft) && array_key_exists($key, $draft)) {
    return (string)$draft[$key];
  }
  if ($isEditing && is_array($editContact) && array_key_exists($key, $editContact)) {
    return (string)$editContact[$key];
  }
  return (string)$default;
};

$currentStatus = $formValue('status', 'Activo');
if (in_array(strtolower($currentStatus), ['inactivo', 'inactive'], true)) {
  $currentStatus = 'Inactivo';
} else {
  $currentStatus = 'Activo';
}
?>

<style>
  .contactos-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: nowrap;
  }

  .btn-sm {
    padding: 5px 10px;
    font-size: 12px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    text-decoration: none;
    cursor: pointer;
  }

  .btn-icon {
    padding: 5px 8px;
    font-size: 12px;
    border-radius: 6px;
    background: var(--app-surface-2, #f8fafc);
    border: 1px solid var(--app-border, #e2e8f0);
    color: var(--app-text, #0f172a);
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .btn-icon:hover:not(:disabled) {
    background: var(--app-accent-soft, rgba(34, 211, 238, 0.16));
    border-color: var(--app-accent, #22d3ee);
    color: var(--app-dark, #0b1226);
  }

  .btn-icon:disabled {
    opacity: 0.35;
    cursor: not-allowed;
  }

  .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 9px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
  }

  .status-badge.active {
    background: rgba(34, 197, 94, 0.15);
    color: #15803d;
    border: 1px solid rgba(34, 197, 94, 0.3);
  }

  .status-badge.inactive {
    background: rgba(100, 116, 139, 0.15);
    color: #64748b;
    border: 1px solid rgba(100, 116, 139, 0.3);
  }

  .status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: currentColor;
  }

  .highlight-row {
    background-color: var(--app-accent-soft, rgba(34, 211, 238, 0.08)) !important;
  }

  .quick-help {
    font-size: 13px;
    color: var(--app-muted, #64748b);
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 4px;
  }
</style>

<div class="stack">
  <div class="card">
    <div class="card-header">
      <div>
        <h3>Directorio de Contactos</h3>
        <p class="subtle">Administración centralizada de contactos institucionales que se presentan en el Dashboard.</p>
      </div>
      <span class="chip">Admin</span>
    </div>

    <?php foreach ($messages as $message): ?>
      <div class="notice"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($message); ?></div>
    <?php endforeach; ?>

    <?php foreach ($errors as $error): ?>
      <div class="error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <?php if ($canManage): ?>
      <div class="card" style="margin-top: 12px; border: 1px solid var(--app-border); background: var(--app-surface-2);">
        <div class="card-header" style="padding-bottom: 8px;">
          <div>
            <h4 style="margin: 0; font-size: 16px; font-weight: 600;">
              <?php if ($isEditing): ?>
                <i class="fa-solid fa-pen-to-square"></i> Modificar Contacto #<?php echo $editIndex + 1; ?>
              <?php else: ?>
                <i class="fa-solid fa-user-plus"></i> Registrar Nuevo Contacto
              <?php endif; ?>
            </h4>
            <p class="subtle" style="margin-top: 4px; font-size: 13px;">
              <?php echo $isEditing ? 'Edita los datos del contacto seleccionado y guarda los cambios.' : 'Completa los campos para agregar un nuevo contacto al directorio y al dashboard.'; ?>
            </p>
          </div>
        </div>

        <form method="post" action="<?php echo htmlspecialchars(route_url('contactos')); ?>" class="stack compact">
          <input type="hidden" name="form_action" value="<?php echo $isEditing ? 'update' : 'create'; ?>">
          <?php if ($isEditing): ?>
            <input type="hidden" name="contact_index" value="<?php echo (int)$editIndex; ?>">
          <?php endif; ?>

          <div class="grid two">
            <div class="field">
              <label for="contact-area">Área / Departamento</label>
              <input id="contact-area" name="area" list="areas-list" placeholder="ej. Recursos Humanos, Jefatura, TI..." value="<?php echo htmlspecialchars($formValue('area')); ?>">
              <datalist id="areas-list">
                <option value="Recursos Humanos">
                <option value="Jefatura">
                <option value="Administracion">
                <option value="Gerencia General">
                <option value="Operaciones">
                <option value="Comercial">
                <option value="Finanzas">
                <option value="Informatica y Sistemas">
              </datalist>
            </div>

            <div class="field">
              <label for="contact-cargo">Cargo / Rol</label>
              <input id="contact-cargo" name="cargo" placeholder="ej. Director Ejecutivo, RRHH, Jefe de Área..." value="<?php echo htmlspecialchars($formValue('cargo')); ?>">
            </div>

            <div class="field">
              <label for="contact-nombre">Nombre <span style="color: #ef4444;">*</span></label>
              <input id="contact-nombre" name="nombre" required placeholder="ej. Eric" value="<?php echo htmlspecialchars($formValue('nombre')); ?>">
            </div>

            <div class="field">
              <label for="contact-apellido">Apellido</label>
              <input id="contact-apellido" name="apellido" placeholder="ej. Contreras" value="<?php echo htmlspecialchars($formValue('apellido')); ?>">
            </div>

            <div class="field">
              <label for="contact-contacto">Contacto / Correo Electrónico <span style="color: #ef4444;">*</span></label>
              <input id="contact-contacto" name="contacto" required placeholder="ej. correo@consultorescyc.cl o +569..." value="<?php echo htmlspecialchars($formValue('contacto')); ?>">
            </div>

            <div class="field">
              <label for="contact-status">Estado</label>
              <select id="contact-status" name="status">
                <option value="Activo" <?php echo $currentStatus === 'Activo' ? 'selected' : ''; ?>>Activo</option>
                <option value="Inactivo" <?php echo $currentStatus === 'Inactivo' ? 'selected' : ''; ?>>Inactivo</option>
              </select>
            </div>
          </div>

          <div style="display: flex; gap: 10px; align-items: center; margin-top: 10px;">
            <?php if ($isEditing): ?>
              <button class="btn primary" type="submit">
                <i class="fa-solid fa-floppy-disk"></i> Guardar Cambios
              </button>
              <a class="btn secondary" href="<?php echo htmlspecialchars(route_url('contactos')); ?>">
                <i class="fa-solid fa-xmark"></i> Cancelar Edición
              </a>
            <?php else: ?>
              <button class="btn primary" type="submit">
                <i class="fa-solid fa-plus"></i> Agregar Contacto
              </button>
            <?php endif; ?>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <div style="margin-top: 24px;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
        <h4 style="margin: 0; font-size: 16px;">
          <i class="fa-solid fa-address-book"></i> Lista de Contactos Registrados (<?php echo count($contacts); ?>)
        </h4>
        <div class="quick-help">
          <i class="fa-solid fa-info-circle"></i> Los contactos se reflejan de inmediato en la tabla del Dashboard.
        </div>
      </div>

      <div style="display: block; width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; clear: both; box-sizing: border-box;">
        <table style="width: 100%; min-width: 700px; border-collapse: collapse;">
          <thead>
            <tr>
              <th style="width: 45px; text-align: center;">#</th>
              <th>Área</th>
              <th>Cargo</th>
              <th>Nombre</th>
              <th>Apellido</th>
              <th>Contacto</th>
              <th style="text-align: center;">Estado</th>
              <?php if ($canManage || $canDelete): ?>
                <th style="width: 180px; text-align: center;">Acciones</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($contacts)): ?>
              <?php foreach ($contacts as $idx => $contact): ?>
                <?php
                $isCurrentEdit = ($isEditing && (int)$editIndex === (int)$idx);
                $isInactive = in_array(strtolower(trim((string)($contact['status'] ?? ''))), ['inactivo', 'inactive'], true);
                $rowStyle = $isCurrentEdit ? 'class="highlight-row"' : ($isInactive ? 'style="opacity: 0.7;"' : '');
                ?>
                <tr <?php echo $rowStyle; ?>>
                  <td style="text-align: center; color: var(--app-muted); font-size: 12px; font-weight: 600;">
                    <?php echo $idx + 1; ?>
                  </td>
                  <td>
                    <strong><?php echo htmlspecialchars((string)($contact['area'] ?? '')); ?></strong>
                  </td>
                  <td>
                    <?php echo htmlspecialchars((string)($contact['cargo'] ?? '')); ?>
                  </td>
                  <td>
                    <?php echo htmlspecialchars((string)($contact['nombre'] ?? '')); ?>
                  </td>
                  <td>
                    <?php echo htmlspecialchars((string)($contact['apellido'] ?? '')); ?>
                  </td>
                  <td>
                    <?php $cVal = trim((string)($contact['contacto'] ?? '')); ?>
                    <?php if ($cVal !== '' && strpos($cVal, '@') !== false): ?>
                      <a class="link" href="mailto:<?php echo htmlspecialchars($cVal); ?>">
                        <i class="fa-regular fa-envelope"></i> <?php echo htmlspecialchars($cVal); ?>
                      </a>
                    <?php else: ?>
                      <?php echo htmlspecialchars($cVal); ?>
                    <?php endif; ?>
                  </td>
                  <td style="text-align: center;">
                    <?php if ($isInactive): ?>
                      <span class="status-badge inactive">
                        <span class="status-dot"></span> Inactivo
                      </span>
                    <?php else: ?>
                      <span class="status-badge active">
                        <span class="status-dot"></span> Activo
                      </span>
                    <?php endif; ?>
                  </td>
                  <?php if ($canManage || $canDelete): ?>
                    <td>
                      <div class="contactos-actions" style="justify-content: center;">
                        <?php if ($canManage): ?>
                          <!-- Subir / Bajar orden -->
                          <form method="post" action="<?php echo htmlspecialchars(route_url('contactos')); ?>" style="display: inline; margin: 0;">
                            <input type="hidden" name="form_action" value="reorder">
                            <input type="hidden" name="contact_index" value="<?php echo $idx; ?>">
                            <input type="hidden" name="direction" value="up">
                            <button type="submit" class="btn-icon" title="Mover arriba" <?php echo $idx === 0 ? 'disabled' : ''; ?>>
                              <i class="fa-solid fa-arrow-up"></i>
                            </button>
                          </form>
                          <form method="post" action="<?php echo htmlspecialchars(route_url('contactos')); ?>" style="display: inline; margin: 0;">
                            <input type="hidden" name="form_action" value="reorder">
                            <input type="hidden" name="contact_index" value="<?php echo $idx; ?>">
                            <input type="hidden" name="direction" value="down">
                            <button type="submit" class="btn-icon" title="Mover abajo" <?php echo $idx === count($contacts) - 1 ? 'disabled' : ''; ?>>
                              <i class="fa-solid fa-arrow-down"></i>
                            </button>
                          </form>

                          <!-- Editar -->
                          <a class="btn secondary btn-sm" href="<?php echo htmlspecialchars(route_url('contactos') . '&action=edit&index=' . $idx); ?>" title="Editar contacto">
                            <i class="fa-solid fa-pen-to-square"></i> Editar
                          </a>
                        <?php endif; ?>

                        <?php if ($canDelete): ?>
                          <!-- Eliminar -->
                          <form method="post" action="<?php echo htmlspecialchars(route_url('contactos')); ?>" style="display: inline; margin: 0;" onsubmit="return confirm('¿Estás seguro de eliminar a <?php echo htmlspecialchars(addslashes((string)$contact['nombre'] . ' ' . (string)$contact['apellido'])); ?>?');">
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="contact_index" value="<?php echo $idx; ?>">
                            <button type="submit" class="btn danger btn-sm" title="Eliminar contacto">
                              <i class="fa-solid fa-trash"></i>
                            </button>
                          </form>
                        <?php endif; ?>
                      </div>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="<?php echo ($canManage || $canDelete) ? 8 : 7; ?>" style="text-align: center; padding: 30px; color: var(--app-muted);">
                  <i class="fa-regular fa-folder-open" style="font-size: 28px; margin-bottom: 8px; display: block; opacity: 0.5;"></i>
                  No hay contactos registrados actualmente en el sistema.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

