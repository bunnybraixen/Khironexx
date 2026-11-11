<?php
$taskStatuses = [
  'pending' => 'Pendiente',
  'in_progress' => 'En progreso',
  'blocked' => 'Bloqueada',
  'completed' => 'Completada',
];
$taskPriorities = [
  'low' => 'Baja',
  'medium' => 'Media',
  'high' => 'Alta',
  'urgent' => 'Urgente',
];
$taskFilters = $onboardingData['taskFilters'] ?? [];
$taskStorage = $onboardingData['taskStorage'] ?? ['ready' => false, 'missing' => []];
$taskFlash = $onboardingData['flash'] ?? null;
$taskFormatDate = function ($date) {
  return !empty($date) ? date('d/m/Y', strtotime($date)) : 'Sin fecha';
};
$taskEvidenceUrl = function ($path) {
  $path = str_replace('\\', '/', trim((string)$path));
  if (strpos($path, 'uploads/') !== 0 || strpos($path, '..') !== false || strpos($path, ':') !== false) return '';
  return '../' . implode('/', array_map('rawurlencode', explode('/', $path)));
};
?>

<?php if (!$taskStorage['ready']): ?>
  <div class="onboarding-task-alert" role="status"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><div><strong>No está completa la estructura de tareas.</strong><p>Faltan estas tablas: <?php echo $onboardingEscape(implode(', ', $taskStorage['missing'])); ?>. Importa el script completo de onboarding en la base de datos local y vuelve a cargar esta página.</p></div></div>
<?php else: ?>
  <?php if (!empty($taskFlash['message'])): ?><div class="onboarding-task-alert is-<?php echo $onboardingEscape($taskFlash['type'] ?? 'success'); ?>" role="status"><i class="fa-solid <?php echo ($taskFlash['type'] ?? '') === 'error' ? 'fa-triangle-exclamation' : 'fa-circle-check'; ?>" aria-hidden="true"></i><div><?php echo $onboardingEscape($taskFlash['message']); ?></div></div><?php endif; ?>

  <section class="onboarding-task-summary" aria-label="Resumen de tareas">
    <article class="onboarding-task-stat is-overdue"><span>Atrasadas</span><strong><?php echo (int)$onboardingData['taskCounts']['overdue']; ?></strong></article>
    <article class="onboarding-task-stat is-pending"><span>Pendientes</span><strong><?php echo (int)$onboardingData['taskCounts']['pending']; ?></strong></article>
    <article class="onboarding-task-stat is-progress"><span>En progreso</span><strong><?php echo (int)$onboardingData['taskCounts']['in_progress']; ?></strong></article>
    <article class="onboarding-task-stat is-blocked"><span>Bloqueadas</span><strong><?php echo (int)$onboardingData['taskCounts']['blocked']; ?></strong></article>
    <article class="onboarding-task-stat is-complete"><span>Completadas</span><strong><?php echo (int)$onboardingData['taskCounts']['completed']; ?></strong></article>
  </section>

  <?php if (!empty($onboardingData['selectedTask'])): $selectedTask = $onboardingData['selectedTask']; ?>
    <section class="onboarding-panel onboarding-task-detail" id="detalle-tarea">
      <div class="onboarding-panel-heading"><div><p class="onboarding-overline">DETALLE DE TAREA #<?php echo (int)$selectedTask['id']; ?></p><h2><?php echo $onboardingEscape($selectedTask['title']); ?></h2><p class="panel-description"><?php echo $onboardingEscape($selectedTask['practitioner_name']); ?> · Proceso #<?php echo (int)$selectedTask['process_id']; ?> · Fase <?php echo (int)$selectedTask['phase_number']; ?>: <?php echo $onboardingEscape($selectedTask['phase_name']); ?></p></div><a class="onboarding-task-back" href="?page=tareas">Volver al listado <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></div>
      <?php if (!empty($selectedTask['template_title'])): ?><p class="onboarding-task-template"><i class="fa-regular fa-copy" aria-hidden="true"></i> Basada en plantilla: <?php echo $onboardingEscape($selectedTask['template_title']); ?></p><?php endif; ?>
      <form class="onboarding-form onboarding-task-edit-form" method="post" action="?page=tareas&amp;task_id=<?php echo (int)$selectedTask['id']; ?>#detalle-tarea">
        <input type="hidden" name="csrf_token" value="<?php echo $onboardingEscape($onboardingData['taskCsrfToken']); ?>">
        <input type="hidden" name="task_action" value="update">
        <input type="hidden" name="task_id" value="<?php echo (int)$selectedTask['id']; ?>">
        <label class="onboarding-field"><span>Título</span><input type="text" name="title" maxlength="200" required value="<?php echo $onboardingEscape($selectedTask['title']); ?>" <?php echo empty($onboardingData['canManage']) ? 'readonly' : ''; ?>></label>
        <label class="onboarding-field"><span>Responsable</span><select name="assigned_user_id" required <?php echo empty($onboardingData['canManage']) ? 'disabled' : ''; ?>><?php foreach ($onboardingData['taskUsers'] as $taskUser): ?><option value="<?php echo (int)$taskUser['id']; ?>" <?php echo (int)$selectedTask['assigned_user_id'] === (int)$taskUser['id'] ? 'selected' : ''; ?>><?php echo $onboardingEscape($taskUser['name']); ?></option><?php endforeach; ?></select></label>
        <label class="onboarding-field onboarding-task-full"><span>Descripción</span><textarea name="description" rows="4" maxlength="10000" <?php echo empty($onboardingData['canManage']) ? 'readonly' : ''; ?>><?php echo $onboardingEscape($selectedTask['description'] ?? ''); ?></textarea></label>
        <label class="onboarding-field"><span>Fecha límite</span><input type="date" name="due_date" value="<?php echo $onboardingEscape($selectedTask['due_date'] ?? ''); ?>" <?php echo empty($onboardingData['canManage']) ? 'disabled' : ''; ?>></label>
        <label class="onboarding-field"><span>Prioridad</span><select name="priority" <?php echo empty($onboardingData['canManage']) ? 'disabled' : ''; ?>><?php foreach ($taskPriorities as $key => $label): ?><option value="<?php echo $key; ?>" <?php echo $selectedTask['priority'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
        <label class="onboarding-field"><span>Estado</span><select name="status" <?php echo empty($onboardingData['canManage']) ? 'disabled' : ''; ?>><?php foreach ($taskStatuses as $key => $label): ?><option value="<?php echo $key; ?>" <?php echo $selectedTask['status'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
        <?php if (!empty($onboardingData['canManage'])): ?><div class="onboarding-form-actions"><span class="form-hint"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Los cambios quedan registrados en el historial del proceso.</span><button class="onboarding-button" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Guardar cambios</button></div><?php endif; ?>
      </form>
      <div class="onboarding-task-detail-meta"><span><strong>Estado actual</strong><em class="task-badge status-<?php echo $onboardingEscape($selectedTask['status']); ?>"><?php echo $onboardingEscape($taskStatuses[$selectedTask['status']] ?? $selectedTask['status']); ?></em></span><span><strong>Prioridad</strong><em class="task-badge priority-<?php echo $onboardingEscape($selectedTask['priority']); ?>"><?php echo $onboardingEscape($taskPriorities[$selectedTask['priority']] ?? $selectedTask['priority']); ?></em></span><span><strong>Requerida</strong><em><?php echo !empty($selectedTask['is_required']) ? 'Sí' : 'No'; ?></em></span><span><strong>Creada</strong><em><?php echo $onboardingEscape($taskFormatDate($selectedTask['created_at'] ?? '')); ?></em></span><?php if (!empty($selectedTask['completed_at'])): ?><span><strong>Completada</strong><em><?php echo $onboardingEscape(date('d/m/Y H:i', strtotime($selectedTask['completed_at']))); ?></em></span><?php endif; ?></div>
      <?php if (!empty($selectedTask['comments'])): ?><div class="onboarding-task-comments"><strong>Comentarios</strong><p><?php echo nl2br($onboardingEscape($selectedTask['comments'])); ?></p></div><?php endif; ?>
      <div class="onboarding-task-extra-grid">
        <section class="onboarding-task-subpanel"><h3><i class="fa-solid fa-paperclip" aria-hidden="true"></i> Evidencias</h3><?php if (empty($onboardingData['taskEvidence'])): ?><p class="task-muted">Todavía no hay evidencias asociadas a esta tarea.</p><?php else: ?><ul class="onboarding-task-evidence-list"><?php foreach ($onboardingData['taskEvidence'] as $evidence): ?><?php $evidenceUrl = $taskEvidenceUrl($evidence['file_path'] ?? ''); ?><li><i class="fa-regular fa-file-lines" aria-hidden="true"></i><div><?php if ($evidenceUrl !== ''): ?><a href="<?php echo $onboardingEscape($evidenceUrl); ?>" target="_blank" rel="noopener noreferrer"><strong><?php echo $onboardingEscape($evidence['original_filename'] ?: basename((string)$evidence['file_path'])); ?></strong></a><?php else: ?><strong><?php echo $onboardingEscape($evidence['original_filename'] ?: basename((string)$evidence['file_path'])); ?></strong><?php endif; ?><?php if (!empty($evidence['description'])): ?><small><?php echo $onboardingEscape($evidence['description']); ?></small><?php endif; ?><small>Subida el <?php echo $onboardingEscape($taskFormatDate($evidence['uploaded_at'] ?? '')); ?></small></div></li><?php endforeach; ?></ul><?php endif; ?></section>
        <section class="onboarding-task-subpanel"><h3><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Historial</h3><?php if (empty($onboardingData['taskHistory'])): ?><p class="task-muted">No hay cambios registrados para esta tarea.</p><?php else: ?><ol class="onboarding-task-history-list"><?php foreach ($onboardingData['taskHistory'] as $event): ?><li><strong><?php echo $onboardingEscape($event['summary'] ?: $event['action']); ?></strong><small><?php echo $onboardingEscape($event['user_name'] ?: 'Usuario'); ?> · <?php echo $onboardingEscape(date('d/m/Y H:i', strtotime($event['created_at']))); ?></small></li><?php endforeach; ?></ol><?php endif; ?></section>
      </div>
    </section>
  <?php elseif (isset($_GET['task_id'])): ?>
    <div class="onboarding-task-alert is-error" role="status"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><div>No se encontró esa tarea o ya no está vinculada a un proceso de onboarding.</div></div>
  <?php endif; ?>

  <?php if (!empty($onboardingData['canManage'])): ?>
    <details class="onboarding-panel onboarding-task-create">
      <summary><span><i class="fa-solid fa-plus" aria-hidden="true"></i> Nueva tarea</span><small>Asóciala a una fase de onboarding existente</small></summary>
      <form class="onboarding-form" method="post" action="?page=tareas">
        <input type="hidden" name="csrf_token" value="<?php echo $onboardingEscape($onboardingData['taskCsrfToken']); ?>">
        <input type="hidden" name="task_action" value="create">
        <label class="onboarding-field onboarding-task-full"><span>Fase y practicante</span><select name="phase_id" required <?php echo empty($onboardingData['taskOptions']['phases']) ? 'disabled' : ''; ?>><option value="">Selecciona el proceso y su fase</option><?php foreach ($onboardingData['taskOptions']['phases'] as $phaseOption): ?><option value="<?php echo (int)$phaseOption['id']; ?>"><?php echo $onboardingEscape($phaseOption['name']); ?></option><?php endforeach; ?></select><?php if (empty($onboardingData['taskOptions']['phases'])): ?><small>Primero debe existir un proceso con fases de onboarding.</small><?php endif; ?></label>
        <label class="onboarding-field"><span>Título</span><input type="text" name="title" maxlength="200" required placeholder="Ej.: Coordinar bienvenida"></label>
        <label class="onboarding-field"><span>Responsable</span><select name="assigned_user_id" required><option value="">Selecciona un usuario</option><?php foreach ($onboardingData['taskUsers'] as $taskUser): ?><option value="<?php echo (int)$taskUser['id']; ?>"><?php echo $onboardingEscape($taskUser['name']); ?></option><?php endforeach; ?></select></label>
        <label class="onboarding-field onboarding-task-full"><span>Descripción</span><textarea name="description" rows="3" maxlength="10000" placeholder="Describe qué debe realizarse"></textarea></label>
        <label class="onboarding-field"><span>Fecha límite</span><input type="date" name="due_date"></label>
        <label class="onboarding-field"><span>Prioridad</span><select name="priority"><?php foreach ($taskPriorities as $key => $label): ?><option value="<?php echo $key; ?>" <?php echo $key === 'medium' ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
        <label class="onboarding-task-checkbox"><input type="checkbox" name="is_required" value="1" checked> Tarea requerida para completar la fase</label>
        <div class="onboarding-form-actions"><span class="form-hint"><i class="fa-solid fa-users" aria-hidden="true"></i> Los responsables se seleccionan de usuarios existentes.</span><button class="onboarding-button" type="submit" <?php echo empty($onboardingData['taskOptions']['phases']) ? 'disabled' : ''; ?>><i class="fa-solid fa-plus" aria-hidden="true"></i> Crear tarea</button></div>
      </form>
    </details>
  <?php endif; ?>

  <section class="onboarding-panel onboarding-task-list-panel">
    <div class="onboarding-panel-heading"><div><p class="onboarding-overline">SEGUIMIENTO GENERAL</p><h2>Gestión de tareas</h2><p class="panel-description">Tareas de todos los procesos y sus fases de onboarding.</p></div><span class="onboarding-count"><?php echo count($onboardingData['tasks']); ?> tareas</span></div>
    <form class="onboarding-task-filters" method="get" action="">
      <input type="hidden" name="page" value="tareas">
      <label class="onboarding-search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input type="search" name="q" value="<?php echo $onboardingEscape($taskFilters['q']); ?>" placeholder="Buscar título, descripción o practicante"></label>
      <label><span>Practicante</span><select name="practitioner_id"><option value="0">Todos</option><?php foreach ($onboardingData['practicantes'] as $person): ?><option value="<?php echo (int)$person['id']; ?>" <?php echo (int)$taskFilters['practitioner_id'] === (int)$person['id'] ? 'selected' : ''; ?>><?php echo $onboardingEscape($person['name']); ?></option><?php endforeach; ?></select></label>
      <label><span>Proceso</span><select name="process_id"><option value="0">Todos</option><?php foreach ($onboardingData['taskOptions']['processes'] as $process): ?><option value="<?php echo (int)$process['id']; ?>" <?php echo (int)$taskFilters['process_id'] === (int)$process['id'] ? 'selected' : ''; ?>><?php echo $onboardingEscape($process['name']); ?></option><?php endforeach; ?></select></label>
      <label><span>Fase</span><select name="phase_id"><option value="0">Todas</option><?php foreach ($onboardingData['taskOptions']['phases'] as $phaseOption): ?><option value="<?php echo (int)$phaseOption['id']; ?>" <?php echo (int)$taskFilters['phase_id'] === (int)$phaseOption['id'] ? 'selected' : ''; ?>><?php echo $onboardingEscape($phaseOption['name']); ?></option><?php endforeach; ?></select></label>
      <label><span>Responsable</span><select name="responsible_id"><option value="0">Todos</option><?php foreach ($onboardingData['taskUsers'] as $taskUser): ?><option value="<?php echo (int)$taskUser['id']; ?>" <?php echo (int)$taskFilters['responsible_id'] === (int)$taskUser['id'] ? 'selected' : ''; ?>><?php echo $onboardingEscape($taskUser['name']); ?></option><?php endforeach; ?></select></label>
      <label><span>Estado</span><select name="status"><option value="">Todos</option><?php foreach ($taskStatuses as $key => $label): ?><option value="<?php echo $key; ?>" <?php echo $taskFilters['status'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
      <label><span>Prioridad</span><select name="priority"><option value="">Todas</option><?php foreach ($taskPriorities as $key => $label): ?><option value="<?php echo $key; ?>" <?php echo $taskFilters['priority'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
      <label><span>Desde</span><input type="date" name="date_from" value="<?php echo $onboardingEscape($taskFilters['date_from']); ?>"></label>
      <label><span>Hasta</span><input type="date" name="date_to" value="<?php echo $onboardingEscape($taskFilters['date_to']); ?>"></label>
      <div class="onboarding-task-filter-actions"><button class="onboarding-button" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Filtrar</button><a class="onboarding-button onboarding-button-muted" href="?page=tareas">Limpiar</a></div>
    </form>
    <div class="onboarding-table-wrap"><table class="onboarding-table onboarding-task-table"><thead><tr><th>Tarea</th><th>Practicante / proceso</th><th>Fase</th><th>Responsable</th><th>Vencimiento</th><th>Prioridad</th><th>Estado</th><th></th></tr></thead><tbody>
      <?php if (empty($onboardingData['tasks'])): ?><tr><td colspan="8"><div class="onboarding-empty-state compact"><span class="empty-state-icon"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span><strong>No hay tareas que coincidan</strong><p>Cuando existan procesos con fases, podrás crear y filtrar tareas desde esta sección.</p></div></td></tr><?php else: foreach ($onboardingData['tasks'] as $task): ?>
        <tr class="<?php echo !empty($task['is_overdue']) ? 'task-row-overdue' : ''; ?>">
          <td><a class="onboarding-task-title" href="?page=tareas&amp;task_id=<?php echo (int)$task['id']; ?>#detalle-tarea"><?php echo $onboardingEscape($task['title']); ?></a><?php if (!empty($task['is_required'])): ?><small class="task-required-label">Requerida</small><?php endif; ?><?php if (!empty($task['description'])): ?><small class="task-description-preview"><?php echo $onboardingEscape(mb_strimwidth($task['description'], 0, 90, '…')); ?></small><?php endif; ?></td>
          <td><?php echo $onboardingEscape($task['practitioner_name']); ?><small>Proceso #<?php echo (int)$task['process_id']; ?></small></td>
          <td>Fase <?php echo (int)$task['phase_number']; ?><small><?php echo $onboardingEscape($task['phase_name']); ?></small></td>
          <td><?php echo $onboardingEscape($task['responsible_name'] ?: 'Sin asignar'); ?></td>
          <td><?php echo $onboardingEscape($taskFormatDate($task['due_date'])); ?><?php if (!empty($task['is_overdue'])): ?><small class="task-overdue-label"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Atrasada</small><?php endif; ?></td>
          <td><span class="task-badge priority-<?php echo $onboardingEscape($task['priority']); ?>"><?php echo $onboardingEscape($taskPriorities[$task['priority']] ?? $task['priority']); ?></span></td>
          <td><span class="task-badge status-<?php echo $onboardingEscape($task['status']); ?>"><?php echo $onboardingEscape($taskStatuses[$task['status']] ?? $task['status']); ?></span></td>
          <td><a class="onboarding-task-open" href="?page=tareas&amp;task_id=<?php echo (int)$task['id']; ?>#detalle-tarea" aria-label="Ver detalle de <?php echo $onboardingEscape($task['title']); ?>"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody></table></div>
  </section>
<?php endif; ?>
