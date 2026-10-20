<?php
$ticketMessages = $ticketMessages ?? [];
$ticketErrors = $ticketErrors ?? [];
$ticketsAvailable = $ticketsAvailable ?? false;
$ticketRows = $ticketRows ?? [];
$ticketIsAdmin = $ticketIsAdmin ?? false;
$ticketFilters = $ticketFilters ?? ['q' => '', 'status' => '', 'prioridad' => '', 'modulo' => ''];
$ticketMessagesAvailable = $ticketMessagesAvailable ?? false;
$ticketMessagesByTicket = $ticketMessagesByTicket ?? [];
$ticketStatusLabels = [
    'abierto' => 'Abierto',
    'en_revision' => 'En revisión',
    'resuelto' => 'Resuelto',
    'cerrado' => 'Cerrado',
];
$ticketModuleLabels = [
    'postulantes' => 'Postulantes',
    'practicantes' => 'Practicantes',
    'relatores' => 'Relatores',
    'capacitaciones' => 'Capacitaciones',
    'informes' => 'Informes',
    'acceso' => 'Acceso al sistema',
    'otro' => 'Otro',
];
?>
<div class="card">
  <div class="card-header">
    <div>
      <h3>Tickets de soporte</h3>
      <p class="subtle">Registra un inconveniente y revisa su estado de atención.</p>
    </div>
    <span class="chip"><?php echo $ticketIsAdmin ? 'Administración' : 'Mis tickets'; ?></span>
  </div>

  <?php foreach ($ticketMessages as $message): ?>
    <div class="notice" role="status"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endforeach; ?>
  <?php foreach ($ticketErrors as $error): ?>
    <div class="error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endforeach; ?>

  <?php if (!$ticketsAvailable): ?>
    <div class="notice">Falta preparar la tabla de tickets en la base de datos. Un administrador debe aplicar el script <code>sql/tickets_soporte_migration.sql</code> en el entorno local.</div>
  <?php else: ?>
    <?php if (!$ticketMessagesAvailable): ?>
      <div class="notice">El historial de conversación se activa importando <code>sql/tickets_mensajes_migration.sql</code> en la base local.</div>
    <?php endif; ?>
    <section class="ticket-form-section">
      <h4>Registrar un problema</h4>
      <p class="subtle">Describe qué ocurrió y en qué parte del sistema. No incluyas contraseñas ni datos personales.</p>
      <form method="post" action="<?php echo htmlspecialchars(route_url('tickets'), ENT_QUOTES, 'UTF-8'); ?>" class="ticket-form">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(tickets_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="ticket_action" value="create">
        <div class="grid two">
          <div class="field">
            <label for="ticket-asunto">Asunto <span aria-hidden="true">*</span></label>
            <input id="ticket-asunto" name="asunto" type="text" maxlength="160" required placeholder="Ej.: No puedo guardar un registro">
          </div>
          <div class="field">
            <label for="ticket-modulo">Módulo afectado <span aria-hidden="true">*</span></label>
            <select id="ticket-modulo" name="modulo" required>
              <option value="">Selecciona un módulo</option>
              <?php foreach ($ticketModuleLabels as $value => $label): ?>
                <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="ticket-prioridad">Prioridad</label>
            <select id="ticket-prioridad" name="prioridad">
              <option value="baja">Baja</option>
              <option value="media" selected>Media</option>
              <option value="alta">Alta</option>
            </select>
          </div>
          <div class="field full">
            <label for="ticket-descripcion">Descripción <span aria-hidden="true">*</span></label>
            <textarea id="ticket-descripcion" name="descripcion" rows="4" maxlength="10000" required placeholder="Indica qué estabas haciendo, qué esperabas que ocurriera y qué pasó."></textarea>
          </div>
        </div>
        <button class="btn" type="submit">Enviar ticket</button>
      </form>
    </section>

    <hr>
    <section>
      <div class="card-header">
        <div>
          <h4><?php echo $ticketIsAdmin ? 'Tickets recibidos' : 'Mis tickets'; ?></h4>
          <p class="subtle"><?php echo $ticketIsAdmin ? 'Revisa los reportes y actualiza su seguimiento.' : 'Solo tú y el equipo de soporte pueden ver tus reportes.'; ?></p>
        </div>
      </div>
      <form method="get" action="<?php echo htmlspecialchars(route_url('tickets'), ENT_QUOTES, 'UTF-8'); ?>" class="filters">
        <input type="hidden" name="route" value="tickets">
        <div class="grid two">
          <div class="field">
            <label for="ticket-filter-q">Buscar</label>
            <input id="ticket-filter-q" name="q" type="search" maxlength="100" value="<?php echo htmlspecialchars($ticketFilters['q'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo $ticketIsAdmin ? 'N.º, asunto, detalle o reportante' : 'N.º, asunto o descripción'; ?>">
          </div>
          <div class="field">
            <label for="ticket-filter-status">Estado</label>
            <select id="ticket-filter-status" name="status">
              <option value="">Todos los estados</option>
              <?php foreach ($ticketStatusLabels as $value => $label): ?>
                <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $ticketFilters['status'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="ticket-filter-priority">Prioridad</label>
            <select id="ticket-filter-priority" name="prioridad">
              <option value="">Todas las prioridades</option>
              <option value="baja" <?php echo $ticketFilters['prioridad'] === 'baja' ? 'selected' : ''; ?>>Baja</option>
              <option value="media" <?php echo $ticketFilters['prioridad'] === 'media' ? 'selected' : ''; ?>>Media</option>
              <option value="alta" <?php echo $ticketFilters['prioridad'] === 'alta' ? 'selected' : ''; ?>>Alta</option>
            </select>
          </div>
          <div class="field">
            <label for="ticket-filter-module">Módulo</label>
            <select id="ticket-filter-module" name="modulo">
              <option value="">Todos los módulos</option>
              <?php foreach ($ticketModuleLabels as $value => $label): ?>
                <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $ticketFilters['modulo'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="filter-actions">
          <button class="btn" type="submit">Buscar y filtrar</button>
          <a class="btn secondary" href="<?php echo htmlspecialchars(route_url('tickets'), ENT_QUOTES, 'UTF-8'); ?>">Limpiar</a>
        </div>
      </form>
      <p class="subtle">Resultados: <?php echo count($ticketRows); ?></p>
      <?php if (!$ticketRows): ?>
        <p class="subtle"><?php echo array_filter($ticketFilters) ? 'No se encontraron tickets con esos criterios.' : 'Todavía no hay tickets registrados.'; ?></p>
      <?php else: ?>
        <div style="display:block;width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch">
          <table class="table-compact" style="width:100%;min-width:760px;border-collapse:collapse">
            <thead><tr>
              <th>Ticket</th><th>Asunto y detalle</th><th>Módulo</th><th>Prioridad</th><th>Estado</th><th>Fecha</th>
              <?php if ($ticketIsAdmin): ?><th>Reportado por / gestión</th><?php endif; ?>
              <th>Conversación</th>
            </tr></thead>
            <tbody>
              <?php foreach ($ticketRows as $ticket): ?>
                <tr>
                  <td>TK-<?php echo str_pad((string)(int)$ticket['id'], 4, '0', STR_PAD_LEFT); ?></td>
                  <td><strong><?php echo htmlspecialchars($ticket['asunto'], ENT_QUOTES, 'UTF-8'); ?></strong><br><?php echo nl2br(htmlspecialchars($ticket['descripcion'], ENT_QUOTES, 'UTF-8')); ?>
                    <?php if (!empty($ticket['respuesta'])): ?><br><span class="subtle"><strong>Respuesta anterior:</strong> <?php echo nl2br(htmlspecialchars($ticket['respuesta'], ENT_QUOTES, 'UTF-8')); ?></span><?php endif; ?>
                  </td>
                  <td><?php echo htmlspecialchars($ticketModuleLabels[$ticket['modulo']] ?? $ticket['modulo'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(ucfirst($ticket['prioridad']), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><span class="status-pill"><?php echo htmlspecialchars($ticketStatusLabels[$ticket['status']] ?? $ticket['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                  <td><?php echo htmlspecialchars($ticket['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <?php if ($ticketIsAdmin): ?>
                    <td>
                      <div><?php echo htmlspecialchars($ticket['reporter_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                      <form method="post" action="<?php echo htmlspecialchars(route_url('tickets'), ENT_QUOTES, 'UTF-8'); ?>" class="stack compact">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(tickets_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="ticket_action" value="update">
                        <input type="hidden" name="ticket_id" value="<?php echo (int)$ticket['id']; ?>">
                        <select name="status" aria-label="Estado del ticket">
                          <?php foreach ($ticketStatusLabels as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $ticket['status'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                          <?php endforeach; ?>
                        </select>
                        <button class="btn secondary" type="submit">Guardar seguimiento</button>
                      </form>
                      <?php if (in_array((string)$ticket['status'], ['resuelto', 'cerrado'], true)): ?>
                        <form method="post" action="<?php echo htmlspecialchars(route_url('tickets'), ENT_QUOTES, 'UTF-8'); ?>" class="stack compact" onsubmit="return confirm('¿Eliminar definitivamente este ticket y su historial de mensajes? Esta acción no se puede deshacer.');">
                          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(tickets_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                          <input type="hidden" name="ticket_action" value="delete">
                          <input type="hidden" name="ticket_id" value="<?php echo (int)$ticket['id']; ?>">
                          <button class="btn danger" type="submit">Eliminar ticket</button>
                        </form>
                      <?php endif; ?>
                    </td>
                  <?php endif; ?>
                  <td>
                    <?php $ticketConversation = $ticketMessagesByTicket[(int)$ticket['id']] ?? []; ?>
                    <?php if ($ticketConversation): ?>
                      <div class="ticket-conversation" aria-label="Mensajes del ticket">
                        <?php foreach ($ticketConversation as $ticketMessage): ?>
                          <p><strong><?php echo htmlspecialchars($ticketMessage['author_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span class="subtle"><?php echo htmlspecialchars($ticketMessage['created_at'], ENT_QUOTES, 'UTF-8'); ?></span><br>
                            <?php echo nl2br(htmlspecialchars($ticketMessage['mensaje'], ENT_QUOTES, 'UTF-8')); ?></p>
                        <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <span class="subtle">Sin mensajes todavía.</span>
                    <?php endif; ?>
                    <?php if ($ticketMessagesAvailable): ?>
                      <form method="post" action="<?php echo htmlspecialchars(route_url('tickets'), ENT_QUOTES, 'UTF-8'); ?>" class="stack compact">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(tickets_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="ticket_action" value="comment">
                        <input type="hidden" name="ticket_id" value="<?php echo (int)$ticket['id']; ?>">
                        <textarea name="mensaje" rows="2" maxlength="4000" required aria-label="Nuevo mensaje del ticket" placeholder="Escribe un mensaje"></textarea>
                        <button class="btn secondary" type="submit">Enviar mensaje</button>
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
  <?php endif; ?>
</div>
