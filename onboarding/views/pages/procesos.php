<?php
$editProcess = $onboardingData['editProcess'] ?? null;
$editMode = is_array($editProcess) && in_array(($editProcess['status'] ?? ''), ['in_progress', 'ready_to_close'], true);
$selectedPractitionerId = (int)($editProcess['practicante_id'] ?? 0);
$activePractitionerIds = [];
foreach ($onboardingData['processes'] as $existingProcess) {
  if (in_array(($existingProcess['status'] ?? ''), ['in_progress', 'ready_to_close'], true)) {
    $activePractitionerIds[(int)$existingProcess['practicante_id']] = true;
  }
}
$formValue = function ($key, $default = '') use ($editProcess) {
  return (string)($editProcess[$key] ?? $default);
};
?>

<?php if (empty($onboardingData['storageReady'])): ?>
  <div class="onboarding-form-notice" role="alert">No está disponible el almacenamiento. Verifica que `sql/onboarding_migration.sql` se haya aplicado y que el módulo de tareas esté habilitado.</div>
<?php else: ?>
  <?php if (!empty($onboardingData['flash'])): ?><div class="onboarding-flash is-<?php echo $onboardingEscape($onboardingData['flash']['type'] ?? 'success'); ?>" role="status"><?php echo $onboardingEscape($onboardingData['flash']['message'] ?? ''); ?></div><?php endif; ?>

  <?php if (!empty($onboardingData['canManage'])): ?>
    <section class="onboarding-panel onboarding-process-create" id="process-editor">
      <div class="onboarding-panel-heading"><div><p class="onboarding-overline"><?php echo $editMode ? 'ACTUALIZAR INGRESO' : 'NUEVO INGRESO'; ?></p><h2><?php echo $editMode ? 'Editar proceso' : 'Preparar un proceso'; ?></h2><p class="panel-description">La persona y el equipo se seleccionan desde los registros existentes; se almacenan solo sus identificadores.</p></div><?php if ($editMode): ?><a class="onboarding-button onboarding-button-muted" href="?page=procesos">Cancelar edición</a><?php endif; ?></div>
      <form class="onboarding-form" method="post" action="?page=procesos#process-editor">
        <input type="hidden" name="csrf_token" value="<?php echo $onboardingEscape($onboardingData['csrfToken']); ?>">
        <input type="hidden" name="onboarding_action" value="<?php echo $editMode ? 'update' : 'create'; ?>">
        <?php if ($editMode): ?><input type="hidden" name="process_id" value="<?php echo (int)$editProcess['id']; ?>"><?php endif; ?>
        <?php if (!$editMode): ?>
          <label class="onboarding-field"><span>Practicante existente *</span><select name="practicante_id" required><option value="">Selecciona un practicante</option><?php foreach ($onboardingData['practicantes'] as $practicante): ?><option value="<?php echo (int)$practicante['id']; ?>" <?php echo !empty($activePractitionerIds[(int)$practicante['id']]) ? 'disabled' : ''; ?>><?php echo $onboardingEscape($practicante['full_name']); ?><?php echo !empty($practicante['rut']) ? ' · ' . $onboardingEscape($practicante['rut']) : ''; ?><?php echo !empty($activePractitionerIds[(int)$practicante['id']]) ? ' · Proceso activo' : ''; ?></option><?php endforeach; ?></select></label>
        <?php else: ?>
          <div class="onboarding-field"><span>Practicante</span><div class="onboarding-readonly-value"><?php echo $onboardingEscape($editProcess['practicante_name']); ?><?php echo !empty($editProcess['rut']) ? ' · ' . $onboardingEscape($editProcess['rut']) : ''; ?></div></div>
        <?php endif; ?>
        <label class="onboarding-field"><span>Fecha de ingreso *</span><input type="date" name="start_date" value="<?php echo $onboardingEscape($formValue('start_date', date('Y-m-d'))); ?>" required></label>
        <label class="onboarding-field"><span>Fecha estimada de término *</span><input type="date" name="estimated_end_date" value="<?php echo $onboardingEscape($formValue('estimated_end_date', date('Y-m-d', strtotime('+90 days')))); ?>" required></label>
        <label class="onboarding-field"><span>Modalidad *</span><select name="modality" required><?php foreach (['presencial' => 'Presencial', 'hibrida' => 'Híbrida', 'remota' => 'Remota'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo $formValue('modality', 'presencial') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
        <?php foreach ([['responsible_user_id', 'Responsable de onboarding', true], ['supervisor_user_id', 'Supervisor o encargado de área', true], ['buddy_user_id', 'Buddy asignado', false], ['backup_buddy_user_id', 'Buddy suplente', false]] as $field): ?>
          <label class="onboarding-field"><span><?php echo $onboardingEscape($field[1]); ?><?php echo $field[2] ? ' *' : ''; ?></span><select name="<?php echo $onboardingEscape($field[0]); ?>" <?php echo $field[2] ? 'required' : ''; ?>><option value="">Sin asignar</option><?php foreach ($onboardingData['usuarios'] as $usuario): ?><option value="<?php echo (int)$usuario['id']; ?>" <?php echo $formValue($field[0]) === (string)$usuario['id'] ? 'selected' : ''; ?>><?php echo $onboardingEscape($usuario['full_name']); ?></option><?php endforeach; ?></select></label>
        <?php endforeach; ?>
        <div class="onboarding-form-actions"><span class="form-hint"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Se generarán 7 fases y las tareas configuradas.</span><button class="onboarding-button" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> <?php echo $editMode ? 'Guardar cambios' : 'Crear proceso'; ?></button></div>
      </form>
    </section>
  <?php endif; ?>

  <section class="onboarding-panel onboarding-process-list">
    <div class="onboarding-panel-heading"><div><p class="onboarding-overline">SEGUIMIENTO</p><h2>Procesos de onboarding</h2></div><span class="onboarding-count"><?php echo count($onboardingData['processes']); ?> procesos</span></div>
    <form class="onboarding-filter-form" method="get" action="">
      <input type="hidden" name="page" value="procesos">
      <label class="onboarding-search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input type="search" name="q" value="<?php echo $onboardingEscape($onboardingData['filters']['q']); ?>" placeholder="Buscar nombre o RUT"></label>
      <select name="status" aria-label="Filtrar por estado"><option value="">Todos los estados</option><?php foreach (['in_progress' => 'En curso', 'ready_to_close' => 'Listo para cierre', 'completed' => 'Finalizado', 'cancelled' => 'Cancelado'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo $onboardingData['filters']['status'] === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select>
      <select name="responsible" aria-label="Filtrar por responsable"><option value="0">Todos los responsables</option><?php foreach ($onboardingData['usuarios'] as $usuario): ?><option value="<?php echo (int)$usuario['id']; ?>" <?php echo $onboardingData['filters']['responsible'] === (int)$usuario['id'] ? 'selected' : ''; ?>><?php echo $onboardingEscape($usuario['full_name']); ?></option><?php endforeach; ?></select>
      <label class="filter-date"><span>Ingreso desde</span><input type="date" name="start_from" value="<?php echo $onboardingEscape($onboardingData['filters']['start_from']); ?>"></label>
      <label class="filter-date"><span>Ingreso hasta</span><input type="date" name="start_to" value="<?php echo $onboardingEscape($onboardingData['filters']['start_to']); ?>"></label>
      <label class="filter-date"><span>Término desde</span><input type="date" name="end_from" value="<?php echo $onboardingEscape($onboardingData['filters']['end_from']); ?>"></label>
      <label class="filter-date"><span>Término hasta</span><input type="date" name="end_to" value="<?php echo $onboardingEscape($onboardingData['filters']['end_to']); ?>"></label>
      <div class="filter-actions"><button class="onboarding-button" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Filtrar</button><a class="onboarding-button onboarding-button-muted" href="?page=procesos">Limpiar</a></div>
    </form>
    <div class="onboarding-table-wrap"><table class="onboarding-table onboarding-process-table"><thead><tr><th>Practicante</th><th>Carrera / cargo</th><th>Ingreso</th><th>Término estimado</th><th>Responsable</th><th>Supervisor</th><th>Buddy</th><th>Estado</th><th>Avance</th><th>Acciones</th></tr></thead><tbody>
      <?php if (empty($onboardingData['processes'])): ?><tr><td colspan="10"><div class="onboarding-empty-state compact"><span class="empty-state-icon"><i class="fa-solid fa-diagram-project" aria-hidden="true"></i></span><strong>No hay procesos para estos criterios</strong><p>Prueba otros filtros o inicia un proceso de onboarding.</p></div></td></tr><?php endif; ?>
      <?php foreach ($onboardingData['processes'] as $process): ?>
        <?php $statusLabel = ['in_progress' => 'En curso', 'ready_to_close' => 'Listo para cierre', 'completed' => 'Finalizado', 'cancelled' => 'Cancelado'][$process['status']] ?? $process['status']; ?>
        <tr>
          <td><strong><?php echo $onboardingEscape($process['practicante_name']); ?></strong><?php if (!empty($process['rut'])): ?><small><?php echo $onboardingEscape($process['rut']); ?></small><?php endif; ?></td>
          <td><?php echo $onboardingEscape($process['career'] ?: '—'); ?></td><td><?php echo $onboardingEscape($process['start_date']); ?></td><td><?php echo $onboardingEscape($process['estimated_end_date']); ?></td>
          <td><?php echo $onboardingEscape($process['responsible_name']); ?></td><td><?php echo $onboardingEscape($process['supervisor_name']); ?></td><td><?php echo $onboardingEscape($process['buddy_name'] ?: '—'); ?></td>
          <td><span class="onboarding-state state-<?php echo $onboardingEscape($process['status']); ?>"><?php echo $onboardingEscape($statusLabel); ?></span></td>
          <td><div class="onboarding-table-progress"><span><?php echo (int)$process['progress_percent']; ?>%</span><div><i style="width: <?php echo min(100, max(0, (int)$process['progress_percent'])); ?>%"></i></div></div></td>
          <td class="onboarding-row-actions"><a href="?page=detalle&amp;id=<?php echo (int)$process['id']; ?>" aria-label="Ver proceso" title="Ver proceso"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a><?php if (!empty($onboardingData['canManage']) && in_array($process['status'], ['in_progress', 'ready_to_close'], true)): ?><a href="?page=procesos&amp;edit=<?php echo (int)$process['id']; ?>#process-editor" aria-label="Editar proceso" title="Editar proceso"><i class="fa-solid fa-pen" aria-hidden="true"></i></a><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </section>
<?php endif; ?>