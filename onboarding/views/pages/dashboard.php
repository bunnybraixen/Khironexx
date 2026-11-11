<?php $summary = $onboardingData['summary']; ?>
<section class="onboarding-welcome">
  <div>
    <p class="onboarding-overline">VISTA GENERAL</p>
    <h2>Una bienvenida bien organizada empieza aquí.</h2>
    <p>Coordina procesos, acompaña cada avance y reúne los recursos de incorporación en un solo lugar.</p>
  </div>
  <a class="onboarding-button onboarding-button-light" href="?page=procesos"><i class="fa-solid fa-plus" aria-hidden="true"></i> Iniciar proceso</a>
</section>

<section class="onboarding-metrics" aria-label="Resumen de onboarding">
  <article class="onboarding-metric"><span class="metric-icon metric-cyan"><i class="fa-solid fa-people-arrows-left-right" aria-hidden="true"></i></span><div><span>Procesos activos</span><strong><?php echo (int)$summary['inProgress']; ?></strong></div><small>En curso</small></article>
  <article class="onboarding-metric"><span class="metric-icon metric-lime"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span><div><span>Completados</span><strong><?php echo (int)$summary['completed']; ?></strong></div><small>Este periodo</small></article>
  <article class="onboarding-metric"><span class="metric-icon metric-coral"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span><div><span>Tareas pendientes</span><strong><?php echo (int)$summary['pendingTasks']; ?></strong></div><small>Por resolver</small></article>
  <article class="onboarding-metric"><span class="metric-icon metric-blue"><i class="fa-solid fa-user-plus" aria-hidden="true"></i></span><div><span>Procesos registrados</span><strong><?php echo (int)$summary['processes']; ?></strong></div><small>Total</small></article>
</section>

<section class="onboarding-dashboard-grid">
  <article class="onboarding-panel onboarding-progress-panel">
    <div class="onboarding-panel-heading"><div><p class="onboarding-overline">AVANCE</p><h2>Progreso de onboarding</h2></div><a href="?page=procesos">Ver procesos <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></div>
    <div class="onboarding-empty-chart"><div class="empty-chart-ring"><span><strong>0%</strong><small>promedio</small></span></div><div><strong>Aún no hay procesos en curso</strong><p>Al crear el primer proceso, aquí verás el avance general por fase.</p></div></div>
  </article>
  <article class="onboarding-panel onboarding-shortcuts">
    <div class="onboarding-panel-heading"><div><p class="onboarding-overline">ACCESO RÁPIDO</p><h2>Organiza la experiencia</h2></div></div>
    <a class="onboarding-shortcut" href="?page=tareas"><span><i class="fa-solid fa-list-check" aria-hidden="true"></i></span><div><strong>Checklist de tareas</strong><small>Responsables, fechas y evidencias</small></div><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
    <a class="onboarding-shortcut" href="?page=biblioteca"><span><i class="fa-solid fa-folder-open" aria-hidden="true"></i></span><div><strong>Material de inducción</strong><small>Documentos y recursos internos</small></div><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
    <a class="onboarding-shortcut" href="?page=plan-30-60-90"><span><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span><div><strong>Plan 30-60-90</strong><small>Objetivos y seguimiento</small></div><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
  </article>
  <article class="onboarding-panel onboarding-activity-panel">
    <div class="onboarding-panel-heading"><div><p class="onboarding-overline">SEGUIMIENTO</p><h2>Actividad reciente</h2></div></div>
    <div class="onboarding-empty-state"><span class="empty-state-icon"><i class="fa-regular fa-clock" aria-hidden="true"></i></span><strong>Sin actividad por ahora</strong><p>Los nuevos procesos y sus avances aparecerán en este espacio.</p></div>
  </article>
</section>