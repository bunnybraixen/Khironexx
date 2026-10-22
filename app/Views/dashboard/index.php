<?php
$dashboardContacts = is_array($dashboardData['contacts'] ?? null) ? $dashboardData['contacts'] : [];
$dashboardContactsText = trim((string)($dashboardData['contactsText'] ?? ''));
$dashboardBirthdays = $dashboardData['birthdays'] ?? [];
$birthdayWindowDays = (int)($dashboardData['birthdayWindowDays'] ?? 30);
$dashboardExpiring = $dashboardData['expiring'] ?? [];
$dashboardAlerts = $dashboardData['alerts'] ?? [];
$dashboardRequests = $dashboardData['requests'] ?? [];
$dashboardHistory = $dashboardData['history'] ?? [];
$totals = $dashboardData['totals'] ?? [];
$totalEmployees = (int)($totals['practicantes'] ?? 0);
$totalTrainers = (int)($totals['relatores'] ?? 0);
$totalTrainings = (int)($totals['capacitaciones'] ?? 0);
$birthdayCount = is_array($dashboardBirthdays) ? count($dashboardBirthdays) : 0;
$expiringCount = is_array($dashboardExpiring) ? count($dashboardExpiring) : 0;
$canViewTareas = $canViewTareas ?? !empty($dashboardData['tasksVisible']);
?>
<div class="dashboard">
  <?php require __DIR__ . '/../layout/sidebar.php'; ?>
  <div class="dashboard-main">
    <?php require __DIR__ . '/../layout/topbar.php'; ?>
    <?php if (!empty($dashboardLoadError)): ?>
      <div class="container">
        <div class="error">No se pudo cargar todo el dashboard: <?php echo htmlspecialchars((string)$dashboardLoadError); ?></div>
      </div>
    <?php endif; ?>
    
    <style>
      .inactive-row {
        opacity: 0.6;
      }

      .stat {
        position: relative;
        background: var(--app-surface-2, #f8fafc);
        border: 1px solid var(--app-border, #e2e8f0);
        border-radius: var(--radius-md, 10px);
        padding: 12px 14px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
      }

      .stat:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
      }

      .stat-link {
        position: absolute;
        top: 8px;
        right: 8px;
        color: var(--app-accent, #0f766e);
        font-size: 14px;
        opacity: 0.6;
        transition: opacity 0.2s ease, transform 0.2s ease;
      }

      .stat:hover .stat-link {
        opacity: 1;
        transform: translateX(2px);
      }

      .widget-link {
        display: inline-block;
        margin-top: 10px;
        font-weight: 600;
      }

      .widget-calendar {
        grid-column: span 12 !important;
        grid-row: auto !important;
        width: 100% !important;
        box-sizing: border-box !important;
        content-visibility: visible !important;
      }

      .widget-base {
        grid-column: span 12 !important;
        grid-row: auto !important;
      }

      .widget-base .stats-grid {
        display: grid !important;
        gap: 12px !important;
        grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
      }

      .widget-contact {
        grid-column: span 6 !important;
      }

      .widget-birthdays {
        grid-column: span 6 !important;
      }

      .widget-alert {
        grid-column: span 6 !important;
      }

      .widget-expiring,
      .widget-requests,
      .widget-tasks {
        grid-column: span 6 !important;
      }

      /* Cuando no hay bloque de alertas, tareas ocupa la fila completa
         para que contactos y cumpleaños no dejen huecos. */
      .widget-tasks.widget-tasks-wide {
        grid-column: span 12 !important;
      }

      .widget-history {
        grid-column: span 12 !important;
      }

      @media (max-width: 1100px) {
        .widget-base .stats-grid {
          grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        }

        .widget-calendar,
        .widget-contact,
        .widget-birthdays,
        .widget-alert,
        .widget-expiring,
        .widget-tasks {
          grid-column: span 12 !important;
        }
      }

      /* --- CONTROL DE RESPONSIVIDAD PARA EL GRID Y TARJETAS --- */
      @media (max-width: 768px) {
        .dashboard-grid {
          display: flex !important;
          flex-direction: column !important;
          width: 100% !important;
          max-width: 100% !important;
          box-sizing: border-box !important;
          overflow: hidden !important;
        }

        .widget-card {
          width: 100% !important;
          max-width: 100% !important;
          min-width: 0 !important;
          box-sizing: border-box !important;
        }

        .widget-base .stats-grid {
          grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        }
      }

      @media (max-width: 480px) {
        .widget-base .stats-grid {
          grid-template-columns: 1fr !important;
        }
      }
    </style>

    <div class="dashboard-grid">
      <?php if (!empty($canViewTareas)): ?>
        <?php
        $calTargetRoute = 'dashboard';
        $calCollapsible = true;
        require __DIR__ . '/../calendario/calendar_widget.php';
        ?>
      <?php endif; ?>
      <div class="widget-card widget-base">
        <div class="widget-head">
          <h3>Base de datos</h3>
          <span class="chip">automatico</span>
        </div>
        <div class="stats-grid">
          <div class="stat">
            <span>Practicantes totales</span>
            <strong><?php echo $totalEmployees; ?></strong>
            <a class="stat-link" href="<?php echo htmlspecialchars(route_url('practicantes')); ?>" title="Ver practicantes"><i class="fas fa-arrow-right"></i></a>
          </div>
          <div class="stat">
            <span>Activos</span>
            <strong><?php echo (int)($totals['practicantes_active'] ?? 0); ?></strong>
          </div>
          <div class="stat">
            <span>Inactivos</span>
            <strong><?php echo (int)($totals['practicantes_inactive'] ?? 0); ?></strong>
          </div>
          <div class="stat">
            <span>Relatores</span>
            <strong><?php echo $totalTrainers; ?></strong>
            <a class="stat-link" href="<?php echo htmlspecialchars(route_url('relatores')); ?>" title="Ver relatores"><i class="fas fa-arrow-right"></i></a>
          </div>
          <div class="stat">
            <span>Capacitaciones</span>
            <strong><?php echo $totalTrainings; ?></strong>
            <a class="stat-link" href="<?php echo htmlspecialchars(route_url('capacitaciones')); ?>" title="Ver capacitaciones"><i class="fas fa-arrow-right"></i></a>
          </div>
        </div>
        <p class="subtle" style="margin: 8px 0 0 0;">Resumen rapido de la base central.</p>
      </div>

      <?php $dashboardShowAlerts = ($canAdmin && $dashboardAlerts); ?>
      <?php if ($dashboardShowAlerts): ?>
        <div class="widget-card widget-alert">
          <h3>Alertas de solicitudes</h3>
          <p class="subtle">Usuarios RRHH con mas de 3 solicitudes pendientes.</p>
          <div style="display: block; width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; clear: both;">
            <table class="table-compact" style="width: 100%; min-width: 450px; border-collapse: collapse;">
              <thead>
                <tr>
                  <th>Usuario</th>
                  <th>Email</th>
                  <th>Solicitudes</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($dashboardAlerts as $alert): ?>
                  <tr>
                    <td><?php echo htmlspecialchars((string)$alert['full_name']); ?></td>
                    <td><?php echo htmlspecialchars((string)$alert['email']); ?></td>
                    <td><?php echo htmlspecialchars((string)$alert['total']); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <div class="widget-card widget-expiring">
        <div class="widget-head">
          <h3>Practicantes por terminar</h3>
          <span class="chip"><?php echo $expiringCount; ?> proximos</span>
        </div>
        <?php if ($dashboardExpiring): ?>
          <div style="display: block; width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; clear: both;">
            <table class="table-compact" style="width: 100%; min-width: 450px; border-collapse: collapse;">
              <thead>
                <tr>
                  <th>Area</th>
                  <th>Nombre</th>
                  <th>Dias</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($dashboardExpiring as $row): ?>
                  <tr>
                    <td><?php echo htmlspecialchars((string)($row['area'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string)($row['name'] ?? '')); ?></td>
                    <td><span class="metric-badge"><?php echo htmlspecialchars((string)($row['days'] ?? '')); ?> dias</span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="subtle">No hay practicantes activos proximos a termino.</p>
        <?php endif; ?>
        <a class="link widget-link" href="<?php echo htmlspecialchars(route_url('practicantes')); ?>">Ver todos los practicantes <i class="fas fa-arrow-right"></i></a>
      </div>

      <div class="widget-card widget-requests">
        <div class="widget-head">
          <h3>Solicitudes</h3>
          <span class="chip">recientes</span>
        </div>
        <?php if ($dashboardRequests): ?>
          <div style="display: block; width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; clear: both;">
            <table class="table-compact" style="width: 100%; min-width: 450px; border-collapse: collapse;">
              <thead>
                <tr>
                  <th>Usuario</th>
                  <th>Modulo</th>
                  <th>Accion</th>
                  <th>Detalle</th>
                  <th>Estado</th>
                  <th>Fecha</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($dashboardRequests as $req): ?>
                  <tr>
                    <td><?php echo htmlspecialchars((string)($req['full_name'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string)($req['modulo'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string)($req['accion'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string)($req['detalle'] ?? '')); ?></td>
                    <td>
                      <?php $reqStatus = strtolower(trim((string)($req['status'] ?? ''))); ?>
                      <?php if ($reqStatus === 'pendiente'): ?>
                        <span class="metric-badge">pendiente</span>
                      <?php elseif ($reqStatus === 'aprobada'): ?>
                        <span class="metric-badge">aprobada</span>
                      <?php elseif ($reqStatus === 'rechazada'): ?>
                        <span class="metric-badge">rechazada</span>
                      <?php else: ?>
                        <?php echo htmlspecialchars((string)($req['status'] ?? '')); ?>
                      <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars((string)($req['created_at'] ?? '')); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="subtle">No hay solicitudes recientes.</p>
        <?php endif; ?>
        <a class="link widget-link" href="<?php echo htmlspecialchars(route_url('solicitudes')); ?>">Ver todas las solicitudes <i class="fas fa-arrow-right"></i></a>
      </div>

      <?php
      $dashboardTasksData = is_array($dashboardData['tasks'] ?? null) ? $dashboardData['tasks'] : ['rows' => [], 'total' => 0];
      $dashboardTaskRows = is_array($dashboardTasksData['rows'] ?? null) ? $dashboardTasksData['rows'] : [];
      $dashboardTasksTotal = (int)($dashboardTasksData['total'] ?? 0);
      $dashboardTasksVisible = !empty($dashboardData['tasksVisible']);
      ?>
      <?php if ($dashboardTasksVisible): ?>
        <div class="widget-card widget-tasks<?php echo $dashboardShowAlerts ? '' : ' widget-tasks-wide'; ?>">
          <div class="widget-head">
            <h3>Tareas pendientes</h3>
            <span class="chip"><?php echo $dashboardTasksTotal; ?> pendientes</span>
          </div>
          <?php if ($dashboardTaskRows): ?>
            <div style="display: block; width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; clear: both;">
              <table class="table-compact" style="width: 100%; min-width: 450px; border-collapse: collapse;">
                <thead>
                  <tr>
                    <th>Tarea</th>
                    <th>Responsable</th>
                    <th>Prioridad</th>
                    <th>Fecha limite</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($dashboardTaskRows as $taskRow): ?>
                    <tr>
                      <td><a class="link" href="<?php echo htmlspecialchars(route_url('tareas')); ?>"><?php echo htmlspecialchars((string)($taskRow['titulo'] ?? '')); ?></a></td>
                      <td><?php echo htmlspecialchars((string)($taskRow['asignado_a_nombre'] ?? '')); ?></td>
                      <td><span class="metric-badge"><?php echo htmlspecialchars((string)($taskRow['prioridad'] ?? '')); ?></span></td>
                      <td><?php echo !empty($taskRow['fecha_limite']) ? htmlspecialchars((string)$taskRow['fecha_limite']) : '<span class="subtle">Sin fecha</span>'; ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="subtle">No hay tareas pendientes.</p>
          <?php endif; ?>
          <a class="link widget-link" href="<?php echo htmlspecialchars(route_url('tareas')); ?>">Ir a la pagina de tareas <i class="fas fa-arrow-right"></i></a>
        </div>
      <?php endif; ?>

      <div class="widget-card widget-contact">
        <h3>Contactos</h3>
        <?php if ($dashboardContacts): ?>
          <div style="display: block; width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; clear: both; box-sizing: border-box;">
            <table class="table-compact" style="width: 100%; min-width: 600px; border-collapse: collapse;">
              <thead>
                <tr>
                  <th>Area</th>
                  <th>Cargo</th>
                  <th>Nombre</th>
                  <th>Apellido</th>
                  <th>Contacto</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($dashboardContacts as $contact): ?>
                  <?php $rowClass = (isset($contact['status']) && in_array(strtolower(trim($contact['status'])), ['inactive', 'inactivo'], true)) ? ' class="inactive-row"' : ''; ?>
                  <tr<?php echo $rowClass; ?>>
                    <td><?php echo htmlspecialchars((string)($contact['area'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string)($contact['cargo'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string)($contact['nombre'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string)($contact['apellido'] ?? '')); ?></td>
                    <td>
                      <?php $contactValue = trim((string)($contact['contacto'] ?? '')); ?>
                      <?php if ($contactValue !== '' && strpos($contactValue, '@') !== false): ?>
                        <a class="link" href="mailto:<?php echo htmlspecialchars($contactValue); ?>"><?php echo htmlspecialchars($contactValue); ?></a>
                      <?php else: ?>
                        <?php echo htmlspecialchars($contactValue); ?>
                      <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars((string)($contact['status'] ?? '')); ?></td>
                    </tr>
                  <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php elseif ($dashboardContactsText !== ''): ?>
          <p><?php echo htmlspecialchars($dashboardContactsText); ?></p>
        <?php else: ?>
          <p class="subtle">Contactos no definidos.</p>
        <?php endif; ?>
      </div>

      <div class="widget-card widget-birthdays">
        <div class="widget-head">
          <h3>Cumplea&ntilde;os cercanos</h3>
          <span class="chip"><?php echo $birthdayCount; ?> en <?php echo $birthdayWindowDays; ?> dias</span>
        </div>
        <?php if ($dashboardBirthdays): ?>
          <ul class="list-plain">
            <?php foreach ($dashboardBirthdays as $birthday): ?>
              <?php if (is_array($birthday) && isset($birthday['name'])): ?>
                <li>
                  <span><?php echo htmlspecialchars((string)$birthday['name']); ?> (<?php echo htmlspecialchars((string)($birthday['date'] ?? '')); ?>)</span>
                  <span class="metric-badge"><?php echo (int)($birthday['days'] ?? 0); ?> dias</span>
                </li>
              <?php else: ?>
                <li><?php echo htmlspecialchars((string)($birthday['label'] ?? $birthday)); ?></li>
              <?php endif; ?>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="subtle">No hay cumplea&ntilde;os en los proximos <?php echo $birthdayWindowDays; ?> dias.</p>
        <?php endif; ?>
      </div>

      <?php if ($canAdmin): ?>
        <div class="widget-card widget-history">
          <div class="widget-head">
            <h3>Historial rapido</h3>
            <span class="chip">ultimos 3 cambios</span>
          </div>
          <?php if ($dashboardHistory): ?>
            <div style="display: block; width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; clear: both;">
              <table class="table-compact" style="width: 100%; min-width: 450px; border-collapse: collapse;">
                <thead>
                  <tr>
                    <th>Usuario</th>
                    <th>Tabla</th>
                    <th>Accion</th>
                    <th>Descripcion</th>
                    <th>Fecha</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($dashboardHistory as $hist): ?>
                    <tr>
                      <td><?php echo htmlspecialchars((string)($hist['usuario_nombre'] ?? $hist['usuario_id'] ?? '-')); ?></td>
                      <td><?php echo htmlspecialchars((string)($hist['tabla_afectada'] ?? '')); ?></td>
                      <td>
                        <?php
                        $histAccion = strtoupper((string)($hist['accion'] ?? ''));
                        $histBadgeClass = 'metric-badge';
                        if ($histAccion === 'INSERT') {
                          $histBadgeClass = 'chip success';
                        } elseif ($histAccion === 'UPDATE') {
                          $histBadgeClass = 'chip warning';
                        } elseif ($histAccion === 'DELETE') {
                          $histBadgeClass = 'chip danger';
                        }
                        ?>
                        <span class="<?php echo $histBadgeClass; ?>"><?php echo htmlspecialchars($histAccion); ?></span>
                      </td>
                      <td><?php echo htmlspecialchars((string)($hist['descripcion'] ?? '')); ?></td>
                      <td><?php echo htmlspecialchars((string)($hist['fecha'] ?? '')); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <a class="link" href="<?php echo htmlspecialchars(route_url('historial')); ?>">Ver historial completo</a>
          <?php else: ?>
            <p class="subtle">No hay cambios registrados.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>