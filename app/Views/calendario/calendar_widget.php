<?php
$e = function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$calTargetRoute = $calTargetRoute ?? 'calendario';
$calCollapsible = !empty($calCollapsible);

$calendarTasks = $calendarTasks ?? [];
if (empty($calendarMonthDate) || !($calendarMonthDate instanceof DateTimeImmutable)) {
    $calendarMonth = trim((string)($_GET['mes'] ?? date('Y-m')));
    $calendarMonthDate = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $calendarMonth)
        ? DateTimeImmutable::createFromFormat('!Y-m', $calendarMonth)
        : false;
    if (!$calendarMonthDate || $calendarMonthDate->format('Y-m') !== $calendarMonth) {
        $calendarMonthDate = new DateTimeImmutable('first day of this month');
        $calendarMonth = $calendarMonthDate->format('Y-m');
    }
} else {
    $calendarMonth = $calendarMonthDate->format('Y-m');
}

$monthStart = $calendarMonthDate->modify('first day of this month');
$monthEnd = $calendarMonthDate->modify('last day of this month');
$daysInMonth = (int)$monthEnd->format('j');
$firstWeekday = (int)$monthStart->format('N'); // Monday = 1
$today = date('Y-m-d');
$tasksByDate = [];
$withoutDate = 0;
$monthTaskCount = 0;
foreach ($calendarTasks as $calendarTask) {
    $dueDate = (string)($calendarTask['fecha_limite'] ?? '');
    if ($dueDate === '') {
        $withoutDate++;
        continue;
    }
    $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate);
    if (!$dateObject || $dateObject->format('Y-m-d') !== $dueDate || $dateObject->format('Y-m') !== $calendarMonth) {
        continue;
    }
    $tasksByDate[(int)$dateObject->format('j')][] = $calendarTask;
    $monthTaskCount++;
}
$previousMonth = $monthStart->modify('-1 month')->format('Y-m');
$nextMonth = $monthStart->modify('+1 month')->format('Y-m');
$monthNames = [
    'January' => 'enero', 'February' => 'febrero', 'March' => 'marzo', 'April' => 'abril',
    'May' => 'mayo', 'June' => 'junio', 'July' => 'julio', 'August' => 'agosto',
    'September' => 'septiembre', 'October' => 'octubre', 'November' => 'noviembre', 'December' => 'diciembre',
];
$monthTitle = ucfirst($monthNames[$monthStart->format('F')] ?? $monthStart->format('F')) . ' ' . $monthStart->format('Y');

$prevUrl = route_url($calTargetRoute) . '&mes=' . $previousMonth;
$nextUrl = route_url($calTargetRoute) . '&mes=' . $nextMonth;
$currentUrl = route_url($calTargetRoute);
?>
<style>
  .kh-calendar-card { overflow: hidden; width: 100%; box-sizing: border-box; }
  .kh-calendar-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 16px 0; flex-wrap: wrap; }
  .kh-calendar-nav { display: flex; align-items: center; gap: 10px; }
  .kh-calendar-title { min-width: 180px; text-align: center; font-size: 1.1rem; font-weight: 700; text-transform: capitalize; }
  .kh-calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); border-top: 1px solid rgba(150, 170, 200, .22); border-left: 1px solid rgba(150, 170, 200, .22); width: 100%; }
  .kh-calendar-weekday, .kh-calendar-day { min-width: 0; border-right: 1px solid rgba(150, 170, 200, .22); border-bottom: 1px solid rgba(150, 170, 200, .22); }
  .kh-calendar-weekday { padding: 10px 6px; text-align: center; color: #9db1ce; font-weight: 700; font-size: .83rem; }
  .kh-calendar-day { min-height: 115px; padding: 8px; background: rgba(10, 18, 32, .22); }
  .kh-calendar-day.is-empty { background: rgba(10, 18, 32, .08); }
  .kh-calendar-day.is-today { box-shadow: inset 0 0 0 2px #1cb8d1; }
  .kh-calendar-date { font-weight: 700; margin-bottom: 7px; }
  .kh-calendar-event { display: block; margin: 5px 0; padding: 6px 7px; border-radius: 7px; color: #eaf3ff; background: rgba(35, 124, 170, .26); border-left: 3px solid #27bfd3; font-size: .78rem; line-height: 1.3; overflow-wrap: anywhere; text-decoration: none; transition: transform 0.15s ease, background 0.15s ease; }
  .kh-calendar-event:hover { transform: translateY(-1px); background: rgba(35, 124, 170, .4); }
  .kh-calendar-event.is-completed { opacity: .68; border-left-color: #7bbf91; text-decoration: line-through; }
  .kh-calendar-event.is-overdue { border-left-color: #e67e82; background: rgba(160, 53, 65, .22); }
  .kh-calendar-meta { display: block; margin-top: 3px; color: #b3c0d3; font-size: .7rem; text-decoration: none; }
  @media (max-width: 760px) {
    .kh-calendar-day { min-height: 85px; padding: 5px; }
    .kh-calendar-event { padding: 4px; font-size: .69rem; }
    .kh-calendar-weekday { font-size: .7rem; }
  }
  @media (max-width: 520px) {
    .kh-calendar-day { min-height: 65px; }
    .kh-calendar-meta { display: none; }
  }
</style>

<?php if ($calCollapsible): ?>
  <div class="widget-card widget-calendar kh-calendar-card" id="widget-calendar-container">
    <div class="widget-head" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 0;">
      <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <h3 style="margin: 0; display: inline-flex; align-items: center; gap: 8px;">
          <i class="fa-solid fa-calendar-days" style="color: var(--app-accent, #0f766e);" aria-hidden="true"></i>
          <span>Calendario de tareas</span>
        </h3>
        <span class="chip"><?php echo (int)$monthTaskCount; ?> tareas este mes</span>
      </div>
      <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
        <a class="btn secondary" href="<?php echo $e(route_url('calendario')); ?>" title="Abrir página completa de calendario" style="padding: 6px 12px; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 6px;">
          <i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i>
          <span>Página completa</span>
        </a>
        <button type="button" class="btn secondary" id="btn-toggle-dashboard-calendar" style="padding: 6px 12px; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;" aria-expanded="false" aria-controls="dashboard-calendar-collapsible">
          <i class="fa-solid fa-chevron-down" id="toggle-calendar-chevron" aria-hidden="true"></i>
          <span id="toggle-calendar-text">Mostrar calendario</span>
        </button>
      </div>
    </div>

    <div id="dashboard-calendar-collapsible" style="display: none; margin-top: 14px;">
      <div class="kh-calendar-toolbar">
        <div class="kh-calendar-nav">
          <a class="btn secondary" href="<?php echo $e($prevUrl); ?>" aria-label="Mes anterior">‹ Anterior</a>
          <span class="kh-calendar-title"><?php echo $e($monthTitle); ?></span>
          <a class="btn secondary" href="<?php echo $e($nextUrl); ?>" aria-label="Mes siguiente">Siguiente ›</a>
        </div>
        <a class="btn secondary" href="<?php echo $e($currentUrl); ?>">Ir a este mes</a>
      </div>

      <div class="kh-calendar-grid" role="grid" aria-label="Calendario de tareas de <?php echo $e($monthTitle); ?>">
        <?php foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $weekday): ?>
          <div class="kh-calendar-weekday" role="columnheader"><?php echo $weekday; ?></div>
        <?php endforeach; ?>
        <?php for ($blank = 1; $blank < $firstWeekday; $blank++): ?>
          <div class="kh-calendar-day is-empty" role="gridcell" aria-hidden="true"></div>
        <?php endfor; ?>
        <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
          <?php
          $dayDate = $monthStart->setDate((int)$monthStart->format('Y'), (int)$monthStart->format('n'), $day)->format('Y-m-d');
          $dayTasks = $tasksByDate[$day] ?? [];
          ?>
          <div class="kh-calendar-day<?php echo $dayDate === $today ? ' is-today' : ''; ?>" role="gridcell" aria-label="<?php echo $e($dayDate); ?>">
            <div class="kh-calendar-date"><?php echo $day; ?></div>
            <?php foreach ($dayTasks as $calendarTask): ?>
              <?php
              $isCompleted = ($calendarTask['status'] ?? '') === 'completada';
              $isOverdue = !$isCompleted && $dayDate < $today;
              $eventClass = $isCompleted ? ' is-completed' : ($isOverdue ? ' is-overdue' : '');
              $taskSearch = (string)($calendarTask['titulo'] ?? '');
              $taskUrl = route_url('tareas') . '&q=' . rawurlencode($taskSearch);
              ?>
              <a class="kh-calendar-event<?php echo $eventClass; ?>" href="<?php echo $e($taskUrl); ?>" title="Abrir la lista de tareas">
                <?php echo $e($calendarTask['titulo'] ?? 'Tarea'); ?>
                <span class="kh-calendar-meta"><?php echo $e($calendarTask['asignado_a_nombre'] ?? ''); ?> · <?php echo $e(ucfirst((string)($calendarTask['prioridad'] ?? 'media'))); ?> · <?php echo $isCompleted ? 'Completada' : ($isOverdue ? 'Atrasada' : 'Pendiente'); ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endfor; ?>
        <?php $trailingCells = (7 - (($firstWeekday - 1 + $daysInMonth) % 7)) % 7; ?>
        <?php for ($blank = 0; $blank < $trailingCells; $blank++): ?>
          <div class="kh-calendar-day is-empty" role="gridcell" aria-hidden="true"></div>
        <?php endfor; ?>
      </div>

      <p class="subtle" style="margin-top: 14px;">Las tareas sin fecha límite no aparecen en el calendario (<?php echo (int)$withoutDate; ?> sin fecha). Selecciona una tarea para buscarla en la lista.</p>
    </div>

    <script>
      (function () {
        var btn = document.getElementById('btn-toggle-dashboard-calendar');
        var content = document.getElementById('dashboard-calendar-collapsible');
        var chevron = document.getElementById('toggle-calendar-chevron');
        var text = document.getElementById('toggle-calendar-text');
        if (!btn || !content) return;

        var urlParams = new URLSearchParams(window.location.search);
        var hasMonthParam = urlParams.has('mes');

        function updateState(collapsed) {
          if (collapsed) {
            content.style.display = 'none';
            if (text) text.textContent = 'Mostrar calendario';
            if (chevron) {
              chevron.className = 'fa-solid fa-chevron-down';
            }
            btn.setAttribute('aria-expanded', 'false');
          } else {
            content.style.display = 'block';
            if (text) text.textContent = 'Retraer calendario';
            if (chevron) {
              chevron.className = 'fa-solid fa-chevron-up';
            }
            btn.setAttribute('aria-expanded', 'true');
          }
        }

        // Por defecto empieza retraído; si navega con ?mes=... se muestra el mes solicitado
        var isCollapsed = !hasMonthParam;
        updateState(isCollapsed);

        btn.addEventListener('click', function () {
          var isNowCollapsed = content.style.display !== 'none';
          updateState(isNowCollapsed);
        });
      })();
    </script>
  </div>
<?php else: ?>
  <div class="card kh-calendar-card">
    <div class="card-header">
      <div>
        <h3>Calendario de tareas</h3>
        <p class="subtle">Las tareas con fecha límite aparecen en el día correspondiente.</p>
      </div>
      <span class="chip"><?php echo (int)$monthTaskCount; ?> tareas este mes</span>
    </div>

    <div class="kh-calendar-toolbar">
      <div class="kh-calendar-nav">
        <a class="btn secondary" href="<?php echo $e($prevUrl); ?>" aria-label="Mes anterior">‹ Anterior</a>
        <span class="kh-calendar-title"><?php echo $e($monthTitle); ?></span>
        <a class="btn secondary" href="<?php echo $e($nextUrl); ?>" aria-label="Mes siguiente">Siguiente ›</a>
      </div>
      <a class="btn secondary" href="<?php echo $e($currentUrl); ?>">Ir a este mes</a>
    </div>

    <div class="kh-calendar-grid" role="grid" aria-label="Calendario de tareas de <?php echo $e($monthTitle); ?>">
      <?php foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $weekday): ?>
        <div class="kh-calendar-weekday" role="columnheader"><?php echo $weekday; ?></div>
      <?php endforeach; ?>
      <?php for ($blank = 1; $blank < $firstWeekday; $blank++): ?>
        <div class="kh-calendar-day is-empty" role="gridcell" aria-hidden="true"></div>
      <?php endfor; ?>
      <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
        <?php
        $dayDate = $monthStart->setDate((int)$monthStart->format('Y'), (int)$monthStart->format('n'), $day)->format('Y-m-d');
        $dayTasks = $tasksByDate[$day] ?? [];
        ?>
        <div class="kh-calendar-day<?php echo $dayDate === $today ? ' is-today' : ''; ?>" role="gridcell" aria-label="<?php echo $e($dayDate); ?>">
          <div class="kh-calendar-date"><?php echo $day; ?></div>
          <?php foreach ($dayTasks as $calendarTask): ?>
            <?php
            $isCompleted = ($calendarTask['status'] ?? '') === 'completada';
            $isOverdue = !$isCompleted && $dayDate < $today;
            $eventClass = $isCompleted ? ' is-completed' : ($isOverdue ? ' is-overdue' : '');
            $taskSearch = (string)($calendarTask['titulo'] ?? '');
            $taskUrl = route_url('tareas') . '&q=' . rawurlencode($taskSearch);
            ?>
            <a class="kh-calendar-event<?php echo $eventClass; ?>" href="<?php echo $e($taskUrl); ?>" title="Abrir la lista de tareas">
              <?php echo $e($calendarTask['titulo'] ?? 'Tarea'); ?>
              <span class="kh-calendar-meta"><?php echo $e($calendarTask['asignado_a_nombre'] ?? ''); ?> · <?php echo $e(ucfirst((string)($calendarTask['prioridad'] ?? 'media'))); ?> · <?php echo $isCompleted ? 'Completada' : ($isOverdue ? 'Atrasada' : 'Pendiente'); ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endfor; ?>
      <?php $trailingCells = (7 - (($firstWeekday - 1 + $daysInMonth) % 7)) % 7; ?>
      <?php for ($blank = 0; $blank < $trailingCells; $blank++): ?>
        <div class="kh-calendar-day is-empty" role="gridcell" aria-hidden="true"></div>
      <?php endfor; ?>
    </div>

    <p class="subtle" style="margin-top: 14px;">Las tareas sin fecha límite no aparecen en el calendario (<?php echo (int)$withoutDate; ?> sin fecha). Selecciona una tarea para buscarla en la lista.</p>
  </div>
<?php endif; ?>
