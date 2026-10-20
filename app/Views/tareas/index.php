<?php
$tareaMessages = $tareaMessages ?? [];
$tareaErrors = $tareaErrors ?? [];
$tareaAvailable = $tareaAvailable ?? false;
$tareaRows = $tareaRows ?? [];
$tareaUsers = $tareaUsers ?? [];
$tareaCanAssign = $tareaCanAssign ?? false;
$tareaFilters = $tareaFilters ?? ['q' => '', 'status' => '', 'prioridad' => '', 'responsable' => 0];
$tareaStatusLabels = tareas_status_labels();
$tareaPriorityLabels = tareas_priority_labels();
$e = function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
?>
<div class="card">
  <div class="card-header">
    <div>
      <h3>Tareas</h3>
      <p class="subtle">Asigna tareas a otros usuarios y marca las tuyas como listas para archivarlas.</p>
    </div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <a class="btn secondary" href="<?php echo $e(route_url('calendario')); ?>">Ver calendario</a>
      <span class="chip"><?php echo $tareaCanAssign ? 'Administración' : 'Mis tareas'; ?></span>
    </div>
  </div>

  <?php foreach ($tareaMessages as $message): ?>
    <div class="notice" role="status"><?php echo $e($message); ?></div>
  <?php endforeach; ?>
  <?php foreach ($tareaErrors as $error): ?>
    <div class="error" role="alert"><?php echo $e($error); ?></div>
  <?php endforeach; ?>

  <?php if (!$tareaAvailable): ?>
    <?php // Si la tabla no existe aún, tareas_ensure_schema la crea sola; no se muestra aviso. ?>
  <?php endif; ?>

  <?php if ($tareaCanAssign): ?>
      <section class="ticket-form-section">
        <h4>Asignar una tarea</h4>
        <p class="subtle">La tarea quedará visible en la lista de pendientes del usuario seleccionado.</p>
        <form method="post" action="<?php echo $e(route_url('tareas')); ?>" class="ticket-form">
          <input type="hidden" name="csrf_token" value="<?php echo $e(tareas_csrf_token()); ?>">
          <input type="hidden" name="tarea_action" value="create">
          <div class="grid two">
            <div class="field">
              <label for="tarea-titulo">Título <span aria-hidden="true">*</span></label>
              <input id="tarea-titulo" name="titulo" type="text" maxlength="160" required placeholder="Ej.: Preparar informe mensual">
            </div>
            <div class="field">
              <label for="tarea-asignado">Responsable <span aria-hidden="true">*</span></label>
              <select id="tarea-asignado" name="asignado_a" required>
                <option value="">Selecciona un usuario</option>
                <?php foreach ($tareaUsers as $tareaUser): ?>
                  <option value="<?php echo (int)$tareaUser['id']; ?>"><?php echo $e($tareaUser['full_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="tarea-prioridad">Prioridad</label>
              <select id="tarea-prioridad" name="prioridad">
                <?php foreach ($tareaPriorityLabels as $value => $label): ?>
                  <option value="<?php echo $e($value); ?>" <?php echo $value === 'media' ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="tarea-fecha">Fecha límite</label>
              <input id="tarea-fecha" name="fecha_limite" type="date">
            </div>
            <div class="field" style="grid-column: 1 / -1;">
              <label for="tarea-descripcion">Descripción</label>
              <textarea id="tarea-descripcion" name="descripcion" rows="3" maxlength="5000" placeholder="Detalla qué debe hacerse"></textarea>
            </div>
          </div>
          <div class="filter-actions">
            <button class="btn" type="submit">Asignar tarea</button>
          </div>
        </form>
      </section>
      <?php endif; ?>

    <section class="ticket-list-section">
      <h4>Lista de tareas</h4>
      <form method="get" action="<?php echo $e(route_url('tareas')); ?>" class="ticket-form">
        <input type="hidden" name="route" value="tareas">
        <div class="grid two">
          <div class="field">
            <label for="tarea-filter-q">Buscar</label>
            <input id="tarea-filter-q" name="q" type="search" maxlength="100" value="<?php echo $e($tareaFilters['q'] ?? ''); ?>" placeholder="Título o descripción">
          </div>
          <div class="field">
            <label for="tarea-filter-status">Estado</label>
            <select id="tarea-filter-status" name="status">
              <option value="">Todas</option>
              <?php foreach ($tareaStatusLabels as $value => $label): ?>
                <option value="<?php echo $e($value); ?>" <?php echo ($tareaFilters['status'] ?? '') === $value ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="tarea-filter-prioridad">Prioridad</label>
            <select id="tarea-filter-prioridad" name="prioridad">
              <option value="">Todas las prioridades</option>
              <?php foreach ($tareaPriorityLabels as $value => $label): ?>
                <option value="<?php echo $e($value); ?>" <?php echo ($tareaFilters['prioridad'] ?? '') === $value ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if ($tareaCanAssign): ?>
            <div class="field">
              <label for="tarea-filter-responsable">Responsable</label>
              <select id="tarea-filter-responsable" name="responsable">
                <option value="">Mis tareas y las que asigné</option>
                <?php foreach ($tareaUsers as $tareaUser): ?>
                  <option value="<?php echo (int)$tareaUser['id']; ?>" <?php echo (int)($tareaFilters['responsable'] ?? 0) === (int)$tareaUser['id'] ? 'selected' : ''; ?>><?php echo $e($tareaUser['full_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>
        </div>
        <div class="filter-actions">
          <button class="btn" type="submit">Buscar y filtrar</button>
          <a class="btn secondary" href="<?php echo $e(route_url('tareas')); ?>">Limpiar</a>
        </div>
      </form>

      <p class="subtle">Resultados: <?php echo count($tareaRows); ?></p>

      <?php if (!$tareaRows): ?>
        <p class="subtle">No hay tareas con esos criterios.</p>
      <?php else: ?>
        <div style="display:block;width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch">
          <table class="table-compact" style="width:100%;min-width:860px;border-collapse:collapse">
            <thead>
              <tr>
                <th>Tarea</th>
                <th>Responsable</th>
                <th>Asignada por</th>
                <th>Prioridad</th>
                <th>Fecha límite</th>
                <th>Estado</th>
                <th>Creada</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>

              <?php foreach ($tareaRows as $tarea): ?>
                <?php
                $tareaId = (int)$tarea['id'];
                $tareaIsOwner = (int)$tarea['creado_por'] === (int)($user['id'] ?? 0);
                $tareaIsAssignee = (int)$tarea['asignado_a'] === (int)($user['id'] ?? 0);
                $tareaPending = (($tarea['status'] ?? '') === 'pendiente');
                ?>
                <tr>
                  <td>
                    <strong><?php echo $e($tarea['titulo']); ?></strong>
                    <?php if (!empty($tarea['descripcion'])): ?>
                      <br><span class="subtle"><?php echo nl2br($e($tarea['descripcion'])); ?></span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo $e($tarea['asignado_a_nombre'] ?? '-'); ?></td>
                  <td><?php echo $e($tarea['creado_por_nombre'] ?? '-'); ?></td>
                  <td><?php echo $e($tareaPriorityLabels[$tarea['prioridad'] ?? ''] ?? ucfirst((string)($tarea['prioridad'] ?? ''))); ?></td>
                  <td><?php echo !empty($tarea['fecha_limite']) ? $e($tarea['fecha_limite']) : '<span class="subtle">Sin fecha</span>'; ?></td>
                  <td>
                    <span class="status-pill"><?php echo $tareaPending ? $e($tareaStatusLabels['pendiente']) : $e($tareaStatusLabels['completada']); ?></span>
                    <?php if (!$tareaPending && !empty($tarea['completed_at'])): ?>
                      <br><span class="subtle"><?php echo $e($tarea['completed_at']); ?></span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo $e($tarea['created_at']); ?></td>
                  <td>
                    <?php if ($tareaPending && ($tareaIsAssignee || $tareaIsOwner || $isAdmin)): ?>
                      <form method="post" action="<?php echo $e(route_url('tareas')); ?>" class="stack compact">
                        <input type="hidden" name="csrf_token" value="<?php echo $e(tareas_csrf_token()); ?>">
                        <input type="hidden" name="tarea_id" value="<?php echo $tareaId; ?>">
                        <input type="hidden" name="tarea_action" value="complete">
                        <button class="btn secondary" type="submit">Marcar como lista</button>
                      </form>
                    <?php elseif (!$tareaPending && ($tareaIsOwner || $isAdmin)): ?>
                      <form method="post" action="<?php echo $e(route_url('tareas')); ?>" class="stack compact">
                        <input type="hidden" name="csrf_token" value="<?php echo $e(tareas_csrf_token()); ?>">
                        <input type="hidden" name="tarea_id" value="<?php echo $tareaId; ?>">
                        <input type="hidden" name="tarea_action" value="reopen">
                        <button class="btn secondary" type="submit">Reabrir</button>
                      </form>
                    <?php endif; ?>
                    <?php if ($tareaIsOwner || $isAdmin): ?>
                      <form method="post" action="<?php echo $e(route_url('tareas')); ?>" class="stack compact" onsubmit="return confirm('¿Eliminar esta tarea?');">
                        <input type="hidden" name="csrf_token" value="<?php echo $e(tareas_csrf_token()); ?>">
                        <input type="hidden" name="tarea_id" value="<?php echo $tareaId; ?>">
                        <input type="hidden" name="tarea_action" value="delete">
                        <button class="btn secondary" type="submit">Eliminar</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
</div>

