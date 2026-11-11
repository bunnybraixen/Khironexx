<?php $process = $onboardingData['process'] ?? null; ?>
<?php if (empty($onboardingData['storageReady'])): ?>
  <div class="onboarding-form-notice" role="alert">No está disponible el almacenamiento de procesos. Aplica la migración de Onboarding y verifica el módulo de tareas.</div>
<?php elseif (!$process): ?>
  <section class="onboarding-panel"><div class="onboarding-empty-state"><span class="empty-state-icon"><i class="fa-regular fa-address-card" aria-hidden="true"></i></span><h2>No se encontró ese proceso</h2><p>El registro puede no existir o ya no estar disponible.</p><a class="onboarding-button" href="?page=procesos">Volver al listado</a></div></section>
<?php else: ?>
  <?php
  $progress = min(100, max(0, (int)$process['progress_percent']));
  $statusLabels = ['in_progress' => 'En curso', 'ready_to_close' => 'Listo para cierre', 'completed' => 'Finalizado', 'cancelled' => 'Cancelado'];
  $summary = $onboardingData['taskSummary'];
  ?>
  <?php if (!empty($onboardingData['flash'])): ?><div class="onboarding-flash is-<?php echo $onboardingEscape($onboardingData['flash']['type'] ?? 'success'); ?>" role="status"><?php echo $onboardingEscape($onboardingData['flash']['message'] ?? ''); ?></div><?php endif; ?>
  <section class="onboarding-detail-hero">
    <div class="detail-identity"><span class="detail-person-icon"><i class="fa-solid fa-user-graduate" aria-hidden="true"></i></span><div><p class="onboarding-overline">PROCESO #<?php echo (int)$process['id']; ?> · <?php echo $onboardingEscape($process['career'] ?: 'Practicante'); ?></p><h2><?php echo $onboardingEscape($process['practicante_name']); ?></h2><p><?php echo $onboardingEscape($process['rut'] ?: 'RUT no informado'); ?><?php if (!empty($process['practicante_email'])): ?> · <?php echo $onboardingEscape($process['practicante_email']); ?><?php endif; ?></p></div></div>
    <div class="detail-actions"><span class="onboarding-state state-<?php echo $onboardingEscape($process['status']); ?>"><?php echo $onboardingEscape($statusLabels[$process['status']] ?? $process['status']); ?></span><?php if (!empty($onboardingData['canManage']) && in_array($process['status'], ['in_progress', 'ready_to_close'], true)): ?><a class="onboarding-button onboarding-button-muted" href="?page=procesos&amp;edit=<?php echo (int)$process['id']; ?>#process-editor"><i class="fa-solid fa-pen" aria-hidden="true"></i> Editar</a><a class="onboarding-button onboarding-button-muted" href="../web/?route=tareas"><i class="fa-solid fa-list-check" aria-hidden="true"></i> Tareas</a><?php endif; ?></div>
  </section>
  <section class="onboarding-detail-summary">
    <article class="onboarding-panel detail-progress"><div class="detail-progress-top"><span>Avance requerido</span><strong><?php echo $progress; ?>%</strong></div><div class="onboarding-progress-track"><i style="width: <?php echo $progress; ?>%"></i></div><small>Calculado automáticamente con tareas obligatorias completadas</small></article>
    <?php foreach ([['pending', 'Pendientes', 'metric-cyan'], ['completed', 'Completadas', 'metric-lime'], ['blocked', 'Bloqueadas', 'metric-coral'], ['overdue', 'Atrasadas', 'metric-blue']] as $metric): ?><article class="onboarding-panel detail-task-metric"><span class="metric-icon <?php echo $metric[2]; ?>"><i class="fa-solid <?php echo ['pending' => 'fa-hourglass-half', 'completed' => 'fa-circle-check', 'blocked' => 'fa-ban', 'overdue' => 'fa-clock'][ $metric[0] ]; ?>" aria-hidden="true"></i></span><div><strong><?php echo (int)$summary[$metric[0]]; ?></strong><small><?php echo $metric[1]; ?></small></div></article><?php endforeach; ?>
  </section>
  <section class="onboarding-detail-columns">
    <article class="onboarding-panel"><div class="onboarding-panel-heading"><div><p class="onboarding-overline">INGRESO</p><h2>Fechas y modalidad</h2></div></div><dl class="onboarding-data-list"><div><dt>Fecha de ingreso</dt><dd><?php echo $onboardingEscape($process['start_date']); ?></dd></div><div><dt>Término estimado</dt><dd><?php echo $onboardingEscape($process['estimated_end_date']); ?></dd></div><div><dt>Término real</dt><dd><?php echo $onboardingEscape($process['actual_end_date'] ?: 'Pendiente'); ?></dd></div><div><dt>Modalidad</dt><dd><?php echo $onboardingEscape(ucfirst($process['modality'])); ?></dd></div><div><dt>Carrera / cargo</dt><dd><?php echo $onboardingEscape($process['career'] ?: '—'); ?></dd></div></dl></article>
    <article class="onboarding-panel"><div class="onboarding-panel-heading"><div><p class="onboarding-overline">ACOMPAÑAMIENTO</p><h2>Responsables</h2></div></div><dl class="onboarding-data-list"><div><dt>Responsable de onboarding</dt><dd><?php echo $onboardingEscape($process['responsible_name']); ?></dd></div><div><dt>Supervisor / área</dt><dd><?php echo $onboardingEscape($process['supervisor_name']); ?></dd></div><div><dt>Buddy</dt><dd><?php echo $onboardingEscape($process['buddy_name'] ?: 'Sin asignar'); ?></dd></div><div><dt>Buddy suplente</dt><dd><?php echo $onboardingEscape($process['backup_buddy_name'] ?: 'Sin asignar'); ?></dd></div></dl></article>
  </section>

  <section class="onboarding-panel onboarding-phase-list">
    <div class="onboarding-panel-heading"><div><p class="onboarding-overline">RUTA DE INCORPORACIÓN</p><h2>Fases y tareas</h2></div><span class="onboarding-count"><?php echo count($onboardingData['phases']); ?> fases · <?php echo (int)$summary['total']; ?> tareas</span></div>
    <?php foreach ($onboardingData['phases'] as $phase): ?>
      <?php $phaseDone = 0; foreach ($phase['tasks'] as $task) { if ($task['status'] === 'completada') { $phaseDone++; } } $phaseProgress = count($phase['tasks']) ? (int)round(100 * $phaseDone / count($phase['tasks'])) : 0; ?>
      <article class="onboarding-phase-item">
        <div class="phase-marker"><span><?php echo (int)$phase['phase_order']; ?></span></div>
        <div class="phase-content">
          <div class="phase-heading"><div><p class="onboarding-overline"><?php echo $onboardingEscape($phase['start_date']); ?> — <?php echo $onboardingEscape($phase['end_date']); ?></p><h3><?php echo $onboardingEscape($phase['title']); ?></h3></div><span class="onboarding-state state-<?php echo $onboardingEscape($phase['status']); ?>"><?php echo ['pending' => 'Pendiente', 'in_progress' => 'En curso', 'completed' => 'Completada'][$phase['status']] ?? $phase['status']; ?></span></div>
          <?php if (!empty($phase['description'])): ?><p class="phase-description"><?php echo $onboardingEscape($phase['description']); ?></p><?php endif; ?>
          <div class="phase-progress-row"><div class="onboarding-progress-track"><i style="width: <?php echo $phaseProgress; ?>%"></i></div><small><?php echo $phaseDone; ?>/<?php echo count($phase['tasks']); ?> tareas</small></div>
          <?php if (!empty($phase['tasks'])): ?><div class="phase-task-list">
            <?php foreach ($phase['tasks'] as $task): ?>
              <?php
              $taskStatus = $task['status'] === 'completada' ? 'completed' : ($task['status'] === 'bloqueada' ? 'blocked' : (!empty($task['overdue']) ? 'overdue' : 'pending'));
              $taskStatusLabel = ['completed' => 'Completada', 'blocked' => 'Bloqueada', 'overdue' => 'Atrasada', 'pending' => 'Pendiente'][$taskStatus];
              $taskSearchUrl = '../web/?route=tareas&q=' . urlencode((string)$task['titulo']);
              ?>
              <div class="phase-task-row"><span class="task-check task-<?php echo $taskStatus; ?>"><i class="fa-solid <?php echo $taskStatus === 'completed' ? 'fa-check' : ($taskStatus === 'blocked' ? 'fa-ban' : 'fa-minus'); ?>" aria-hidden="true"></i></span><div class="phase-task-title"><a href="<?php echo $onboardingEscape($taskSearchUrl); ?>" title="Abrir en el módulo de tareas"><?php echo $onboardingEscape($task['titulo']); ?></a><?php if ((int)$task['is_required'] === 1): ?><small>Obligatoria</small><?php endif; ?></div><span class="phase-task-owner"><?php echo $onboardingEscape($task['assignee_name']); ?></span><span class="phase-task-date"><?php echo $onboardingEscape($task['fecha_limite'] ?: 'Sin fecha'); ?></span><span class="task-status-label task-label-<?php echo $taskStatus; ?>"><?php echo $taskStatusLabel; ?></span></div>
            <?php endforeach; ?>
          </div><?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </section>

  <?php if ($process['status'] === 'cancelled'): ?><section class="onboarding-panel onboarding-cancellation"><p class="onboarding-overline">PROCESO CANCELADO</p><p><?php echo nl2br($onboardingEscape($process['cancellation_reason'])); ?></p><small><?php echo $onboardingEscape($process['cancelled_at']); ?></small></section><?php endif; ?>
  <?php if (!empty($onboardingData['canManage']) && in_array($process['status'], ['in_progress', 'ready_to_close'], true)): ?>
    <section class="onboarding-detail-controls">
      <form method="post" action="?page=detalle&amp;id=<?php echo (int)$process['id']; ?>" data-confirm="¿Finalizar el proceso? Solo se permite si todas las tareas obligatorias están completadas."><input type="hidden" name="csrf_token" value="<?php echo $onboardingEscape($onboardingData['csrfToken']); ?>"><input type="hidden" name="onboarding_action" value="complete"><input type="hidden" name="process_id" value="<?php echo (int)$process['id']; ?>"><button class="onboarding-button" type="submit"><i class="fa-solid fa-flag-checkered" aria-hidden="true"></i> Finalizar proceso</button></form>
      <form method="post" action="?page=detalle&amp;id=<?php echo (int)$process['id']; ?>" class="onboarding-cancel-form" data-confirm="¿Cancelar este proceso? Las tareas e historial se conservarán."><input type="hidden" name="csrf_token" value="<?php echo $onboardingEscape($onboardingData['csrfToken']); ?>"><input type="hidden" name="onboarding_action" value="cancel"><input type="hidden" name="process_id" value="<?php echo (int)$process['id']; ?>"><label class="onboarding-field"><span>Motivo de cancelación</span><input type="text" name="cancellation_reason" minlength="5" maxlength="2000" placeholder="Explica por qué se cancela" required></label><button class="onboarding-button onboarding-button-danger" type="submit"><i class="fa-solid fa-ban" aria-hidden="true"></i> Cancelar proceso</button></form>
    </section>
  <?php endif; ?>

  <section class="onboarding-panel onboarding-history-panel"><div class="onboarding-panel-heading"><div><p class="onboarding-overline">AUDITORÍA</p><h2>Historial del proceso</h2></div></div><?php if (empty($onboardingData['history'])): ?><p class="panel-description">Aún no hay acciones registradas.</p><?php else: ?><div class="onboarding-history-list"><?php foreach ($onboardingData['history'] as $history): ?><article><span class="history-dot"></span><div><strong><?php echo $onboardingEscape($history['summary']); ?></strong><small><?php echo $onboardingEscape($history['user_name'] ?: 'Usuario eliminado'); ?> · <?php echo $onboardingEscape($history['created_at']); ?></small></div></article><?php endforeach; ?></div><?php endif; ?></section>
<?php endif; ?>