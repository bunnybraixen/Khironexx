<div class="card">
  <div class="card-header">
    <div>
      <h3>Historial de cambios</h3>
      <p class="subtle">Registro de todas las acciones realizadas en el sistema.</p>
    </div>
    <span class="chip">Auditoria</span>
  </div>

  <form method="get" class="filters">
    <input type="hidden" name="route" value="historial">
    <div class="grid two">
      <div class="field">
        <label>Busqueda general</label>
        <input name="q" placeholder="Tabla, accion, descripcion, usuario..." value="<?php echo htmlspecialchars($filterGeneral); ?>">
      </div>
      <div class="field">
        <label>Tabla afectada</label>
        <select name="tabla">
          <option value="">Todas</option>
          <?php foreach ($tablasDisponibles as $tabla): ?>
            <option value="<?php echo htmlspecialchars($tabla); ?>" <?php echo $filterTabla === $tabla ? 'selected' : ''; ?>><?php echo htmlspecialchars($tabla); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Accion</label>
        <select name="accion">
          <option value="">Todas</option>
          <option value="INSERT" <?php echo $filterAccion === 'INSERT' ? 'selected' : ''; ?>>INSERT</option>
          <option value="UPDATE" <?php echo $filterAccion === 'UPDATE' ? 'selected' : ''; ?>>UPDATE</option>
          <option value="DELETE" <?php echo $filterAccion === 'DELETE' ? 'selected' : ''; ?>>DELETE</option>
        </select>
      </div>
      <div class="field">
        <label>Fecha desde</label>
        <input name="fecha_desde" type="date" value="<?php echo htmlspecialchars($filterFechaDesde); ?>">
      </div>
      <div class="field">
        <label>Fecha hasta</label>
        <input name="fecha_hasta" type="date" value="<?php echo htmlspecialchars($filterFechaHasta); ?>">
      </div>
    </div>
    <div class="filter-actions">
      <button class="btn" type="submit">Aplicar filtros</button>
      <a class="btn secondary" href="<?php echo htmlspecialchars(route_url('historial')); ?>">Limpiar</a>
    </div>
  </form>

  <p class="subtle">Resultados encontrados: <?php echo (int)$historialCount; ?> (maximo 200)</p>
  <div style="display: block; width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; clear: both; margin-bottom: 20px;">
    <table style="width: 100%; min-width: 900px; border-collapse: collapse;">
      <thead>
        <tr>
          <th>ID</th>
          <th>Usuario</th>
          <th>Tabla</th>
          <th>Registro</th>
          <th>Accion</th>
          <th>Descripcion</th>
          <th>Datos anteriores</th>
          <th>Datos nuevos</th>
          <th>Fecha</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($historialRows): while ($row = $historialRows->fetch_assoc()): ?>
          <tr>
            <td><?php echo (int)($row['id_historial'] ?? 0); ?></td>
            <td><?php echo htmlspecialchars((string)($row['usuario_nombre'] ?? $row['usuario_id'] ?? '-')); ?></td>
            <td><?php echo htmlspecialchars((string)($row['tabla_afectada'] ?? '')); ?></td>
            <td><?php echo htmlspecialchars((string)($row['registro_id'] ?? '')); ?></td>
            <td>
              <?php
              $accion = strtoupper((string)($row['accion'] ?? ''));
              $badgeClass = 'chip';
              if ($accion === 'INSERT') {
                $badgeClass = 'chip success';
              } elseif ($accion === 'UPDATE') {
                $badgeClass = 'chip warning';
              } elseif ($accion === 'DELETE') {
                $badgeClass = 'chip danger';
              }
              ?>
              <span class="<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($accion); ?></span>
            </td>
            <td><?php echo htmlspecialchars((string)($row['descripcion'] ?? '')); ?></td>
            <td>
              <?php
              $datosAnteriores = $row['datos_anteriores'] ?? '';
              if ($datosAnteriores !== '' && $datosAnteriores !== null) {
                $decoded = json_decode((string)$datosAnteriores, true);
                if (is_array($decoded)) {
                  echo '<pre class="historial-json">' . htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
                } else {
                  echo htmlspecialchars((string)$datosAnteriores);
                }
              } else {
                echo '<span class="subtle">-</span>';
              }
              ?>
            </td>
            <td>
              <?php
              $datosNuevos = $row['datos_nuevos'] ?? '';
              if ($datosNuevos !== '' && $datosNuevos !== null) {
                $decoded = json_decode((string)$datosNuevos, true);
                if (is_array($decoded)) {
                  echo '<pre class="historial-json">' . htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
                } else {
                  echo htmlspecialchars((string)$datosNuevos);
                }
              } else {
                echo '<span class="subtle">-</span>';
              }
              ?>
            </td>
            <td><?php echo htmlspecialchars((string)($row['fecha'] ?? '')); ?></td>
          </tr>
        <?php endwhile; else: ?>
          <tr>
            <td colspan="9" class="subtle" style="text-align:center; padding:20px;">No se encontraron registros de historial.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<style>
  .historial-json {
    background: #f5f5f5;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    padding: 6px 8px;
    margin: 0;
    font-size: 11px;
    line-height: 1.4;
    max-width: 300px;
    max-height: 120px;
    overflow: auto;
    white-space: pre-wrap;
    word-break: break-word;
  }
  .chip.success {
    background: #d4edda;
    color: #155724;
  }
  .chip.warning {
    background: #fff3cd;
    color: #856404;
  }
  .chip.danger {
    background: #f8d7da;
    color: #721c24;
  }
</style>