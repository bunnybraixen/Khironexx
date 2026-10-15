<?php
$messages = $dbManagerData['messages'] ?? [];
$errors = $dbManagerData['errors'] ?? [];
$tables = $dbManagerData['tables'] ?? [];
$activeTable = $dbManagerData['activeTable'] ?? '';
$columns = $dbManagerData['columns'] ?? [];
$primaryKeys = $dbManagerData['primaryKeys'] ?? [];
$browseData = $dbManagerData['browseData'] ?? [];
$editRow = $dbManagerData['editRow'] ?? null;
$action = $dbManagerData['action'] ?? 'browse';
$canManage = (bool)($dbManagerData['canManage'] ?? false);
$canDelete = (bool)($dbManagerData['canDelete'] ?? false);

$rows = $browseData['rows'] ?? [];
$totalCount = (int)($browseData['totalCount'] ?? 0);
$page = (int)($browseData['page'] ?? 1);
$perPage = (int)($browseData['perPage'] ?? 25);
$totalPages = (int)($browseData['totalPages'] ?? 1);
$search = (string)($browseData['search'] ?? '');
$sort = (string)($browseData['sort'] ?? '');
$order = (string)($browseData['order'] ?? 'ASC');

// Encontrar detalles de la tabla activa
$currentTableInfo = null;
foreach ($tables as $t) {
  if ($t['name'] === $activeTable) {
    $currentTableInfo = $t;
    break;
  }
}
?>

<style>
  .db-layout {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 20px;
    align-items: start;
  }

  @media (max-width: 900px) {
    .db-layout {
      grid-template-columns: 1fr;
    }
  }

  .db-sidebar-card {
    background: var(--app-surface, #ffffff);
    border: 1px solid var(--app-border, #e2e8f0);
    border-radius: var(--radius-md, 12px);
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .db-table-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
    max-height: 520px;
    overflow-y: auto;
    padding-right: 4px;
  }

  .db-table-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    border-radius: var(--radius-sm, 8px);
    text-decoration: none;
    color: var(--app-text, #0f172a);
    font-size: 13px;
    font-weight: 500;
    transition: all 0.15s ease;
    border: 1px solid transparent;
  }

  .db-table-item:hover {
    background: var(--app-surface-2, #f8fafc);
    border-color: var(--app-border, #e2e8f0);
  }

  .db-table-item.active {
    background: linear-gradient(120deg, var(--app-accent, #22d3ee), var(--app-accent-2, #38bdf8));
    color: #0b1226;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(34, 211, 238, 0.25);
  }

  .db-table-item .count-badge {
    background: rgba(15, 23, 42, 0.08);
    font-size: 11px;
    padding: 2px 7px;
    border-radius: 999px;
    font-weight: 600;
  }

  .db-table-item.active .count-badge {
    background: rgba(11, 18, 38, 0.2);
    color: #0b1226;
  }

  .db-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--app-border, #e2e8f0);
  }

  .db-toolbar-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  .null-badge {
    font-size: 10px;
    font-weight: 700;
    color: var(--app-muted, #64748b);
    background: var(--app-bg-2, #e2e8f0);
    padding: 2px 5px;
    border-radius: 4px;
    font-style: italic;
  }

  .pk-badge {
    font-size: 10px;
    color: #f59e0b;
    margin-left: 3px;
  }

  .table-sort-header {
    color: inherit;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
  }

  .table-sort-header:hover {
    color: var(--app-accent, #0f766e);
  }

  .pagination {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 14px;
  }

  .pagination a,
  .pagination span {
    padding: 5px 11px;
    border-radius: var(--radius-sm, 6px);
    font-size: 13px;
    text-decoration: none;
    border: 1px solid var(--app-border, #e2e8f0);
    background: var(--app-surface, #fff);
    color: var(--app-text, #0f172a);
  }

  .pagination a:hover {
    background: var(--app-surface-2, #f8fafc);
    border-color: var(--app-accent, #22d3ee);
  }

  .pagination .current {
    background: var(--app-dark, #0b1226);
    color: #ffffff;
    border-color: var(--app-dark, #0b1226);
    font-weight: 600;
  }

  .pagination .disabled {
    opacity: 0.4;
    pointer-events: none;
  }

  .db-field-meta {
    font-size: 11px;
    color: var(--app-muted, #64748b);
    margin-top: 2px;
  }
</style>

<div class="stack">
  <div class="card">
    <div class="card-header">
      <div>
        <h3><i class="fa-solid fa-database"></i> Gestión de Base de Datos</h3>
        <p class="subtle">Explorador y administrador directo de tablas, columnas y registros de MySQL.</p>
      </div>
      <span class="chip">Admin BDD</span>
    </div>

    <?php foreach ($messages as $message): ?>
      <div class="notice"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($message); ?></div>
    <?php endforeach; ?>

    <?php foreach ($errors as $error): ?>
      <div class="error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <div class="db-layout" style="margin-top: 14px;">
      <!-- Barra lateral de tablas -->
      <div class="db-sidebar-card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <strong style="font-size: 14px;">Tablas (<?php echo count($tables); ?>)</strong>
          <span class="chip" style="font-size: 10px; padding: 2px 6px;">MySQL</span>
        </div>

        <div>
          <input id="dbTableSearch" type="text" placeholder="Filtrar tablas..." style="width: 100%; font-size: 12px; padding: 6px 10px; border-radius: var(--radius-sm); border: 1px solid var(--app-border);" oninput="filterDbTables(this.value)">
        </div>

        <nav class="db-table-list" id="dbTableList" aria-label="Lista de tablas">
          <?php foreach ($tables as $tbl): ?>
            <?php
            $isTblActive = ($tbl['name'] === $activeTable);
            $tblUrl = route_url('database') . '&table=' . urlencode($tbl['name']);
            ?>
            <a href="<?php echo htmlspecialchars($tblUrl); ?>" class="db-table-item <?php echo $isTblActive ? 'active' : ''; ?>" data-table-name="<?php echo htmlspecialchars(strtolower($tbl['name'])); ?>" title="Motor: <?php echo htmlspecialchars($tbl['engine']); ?>, Tamaño: <?php echo htmlspecialchars($tbl['size']); ?>">
              <span><i class="fa-solid fa-table" style="opacity: 0.7; margin-right: 5px;"></i><?php echo htmlspecialchars($tbl['name']); ?></span>
              <span class="count-badge"><?php echo $tbl['rows']; ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
      </div>

      <!-- Contenido de la tabla seleccionada -->
      <div class="stack" style="min-width: 0;">
        <?php if ($activeTable !== ''): ?>
          <div class="card" style="border: 1px solid var(--app-border); padding: 18px;">
            <!-- Toolbar de la tabla activa -->
            <div class="db-toolbar">
              <div>
                <h4 style="margin: 0; font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                  <i class="fa-solid fa-table-cells"></i> Tabla: <code style="background: var(--app-surface-2); padding: 2px 8px; border-radius: 4px;"><?php echo htmlspecialchars($activeTable); ?></code>
                </h4>
                <p class="subtle" style="margin-top: 4px; font-size: 12px;">
                  <?php echo (int)($currentTableInfo['rows'] ?? $totalCount); ?> filas estimadas &bull;
                  Motor: <?php echo htmlspecialchars($currentTableInfo['engine'] ?? 'InnoDB'); ?> &bull;
                  Tamaño: <?php echo htmlspecialchars($currentTableInfo['size'] ?? '0 KB'); ?> &bull;
                  Cotejamiento: <?php echo htmlspecialchars($currentTableInfo['collation'] ?? 'utf8mb4'); ?>
                </p>
              </div>

              <div class="db-toolbar-actions">
                <?php if ($action !== 'create'): ?>
                  <a href="<?php echo htmlspecialchars(route_url('database') . '&table=' . urlencode($activeTable) . '&action=create'); ?>" class="btn primary" style="padding: 7px 14px; font-size: 13px;">
                    <i class="fa-solid fa-plus"></i> Añadir Registro
                  </a>
                <?php endif; ?>

                <?php if ($action !== 'browse'): ?>
                  <a href="<?php echo htmlspecialchars(route_url('database') . '&table=' . urlencode($activeTable)); ?>" class="btn secondary" style="padding: 7px 14px; font-size: 13px;">
                    <i class="fa-solid fa-list"></i> Ver Registros
                  </a>
                <?php endif; ?>

                <a href="<?php echo htmlspecialchars(route_url('database') . '&table=' . urlencode($activeTable)); ?>" class="btn secondary" style="padding: 7px 12px; font-size: 13px;" title="Refrescar datos">
                  <i class="fa-solid fa-arrows-rotate"></i>
                </a>

                <?php if ($canDelete): ?>
                  <form method="post" action="<?php echo htmlspecialchars(route_url('database') . '&table=' . urlencode($activeTable)); ?>" style="display: inline; margin: 0;" onsubmit="return confirm('⚠️ ¿ESTÁS TOTALMENTE SEGURO de vaciar TODOS los registros de la tabla \'<?php echo htmlspecialchars(addslashes($activeTable)); ?>\'?\n\nEsta acción no se puede deshacer.');">
                    <input type="hidden" name="form_action" value="truncate">
                    <button type="submit" class="btn danger" style="padding: 7px 12px; font-size: 13px;" title="Vaciar todos los registros de la tabla">
                      <i class="fa-solid fa-trash-can"></i> Vaciar Tabla
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </div>

            <!-- MODO: CREAR / INSERTAR REGISTRO -->
            <?php if ($action === 'create'): ?>
              <div style="margin-top: 18px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                  <h5 style="margin: 0; font-size: 16px; font-weight: 600;"><i class="fa-solid fa-plus-circle"></i> Insertar Nuevo Registro</h5>
                  <a href="<?php echo htmlspecialchars(route_url('database') . '&table=' . urlencode($activeTable)); ?>" class="btn secondary" style="padding: 4px 10px; font-size: 12px;">Volver</a>
                </div>

                <form method="post" action="<?php echo htmlspecialchars(route_url('database') . '&table=' . urlencode($activeTable)); ?>" class="stack compact">
                  <input type="hidden" name="form_action" value="insert">

                  <div class="grid two">
                    <?php foreach ($columns as $colName => $col): ?>
                      <?php
                      $isAutoInc = (stripos($col['extra'], 'auto_increment') !== false);
                      $isPk = ($col['key'] === 'PRI');
                      $typeLower = strtolower($col['type']);
                      ?>
                      <div class="field">
                        <label for="col_<?php echo htmlspecialchars($colName); ?>">
                          <code><?php echo htmlspecialchars($colName); ?></code>
                          <?php if ($isPk): ?><span class="pk-badge" title="Clave Primaria"><i class="fa-solid fa-key"></i> PK</span><?php endif; ?>
                          <?php if ($isAutoInc): ?><span class="chip" style="font-size: 10px; padding: 1px 5px;">auto_increment</span><?php endif; ?>
                        </label>

                        <?php if (strpos($typeLower, 'text') !== false || strpos($typeLower, 'json') !== false): ?>
                          <textarea id="col_<?php echo htmlspecialchars($colName); ?>" name="<?php echo htmlspecialchars($colName); ?>" rows="3" placeholder="<?php echo $isAutoInc ? '(Automático)' : htmlspecialchars($col['type']); ?>" <?php echo $isAutoInc ? 'readonly' : ''; ?>></textarea>
                        <?php elseif (strpos($typeLower, 'enum(') === 0): ?>
                          <?php
                          preg_match("/^enum\((.*)\)$/i", $col['type'], $enumMatches);
                          $enumValues = [];
                          if (!empty($enumMatches[1])) {
                            $rawEnums = explode(',', $enumMatches[1]);
                            foreach ($rawEnums as $eVal) {
                              $enumValues[] = trim($eVal, " '\"");
                            }
                          }
                          ?>
                          <select id="col_<?php echo htmlspecialchars($colName); ?>" name="<?php echo htmlspecialchars($colName); ?>">
                            <?php if ($col['null']): ?><option value="">[NULL / Vacío]</option><?php endif; ?>
                            <?php foreach ($enumValues as $ev): ?>
                              <option value="<?php echo htmlspecialchars($ev); ?>" <?php echo ($col['default'] === $ev) ? 'selected' : ''; ?>><?php echo htmlspecialchars($ev); ?></option>
                            <?php endforeach; ?>
                          </select>
                        <?php elseif (strpos($typeLower, 'date') === 0 && strpos($typeLower, 'datetime') === false): ?>
                          <input type="date" id="col_<?php echo htmlspecialchars($colName); ?>" name="<?php echo htmlspecialchars($colName); ?>" value="<?php echo htmlspecialchars((string)$col['default']); ?>">
                        <?php elseif (strpos($typeLower, 'datetime') !== false || strpos($typeLower, 'timestamp') !== false): ?>
                          <input type="datetime-local" id="col_<?php echo htmlspecialchars($colName); ?>" name="<?php echo htmlspecialchars($colName); ?>">
                        <?php elseif (strpos($typeLower, 'int') !== false || strpos($typeLower, 'decimal') !== false || strpos($typeLower, 'float') !== false): ?>
                          <input type="number" step="any" id="col_<?php echo htmlspecialchars($colName); ?>" name="<?php echo htmlspecialchars($colName); ?>" placeholder="<?php echo $isAutoInc ? '(Automático)' : htmlspecialchars($col['type']); ?>" value="<?php echo $isAutoInc ? '' : htmlspecialchars((string)$col['default']); ?>">
                        <?php else: ?>
                          <input type="text" id="col_<?php echo htmlspecialchars($colName); ?>" name="<?php echo htmlspecialchars($colName); ?>" placeholder="<?php echo $isAutoInc ? '(Automático)' : htmlspecialchars($col['type']); ?>" value="<?php echo htmlspecialchars((string)$col['default']); ?>" <?php echo $isAutoInc ? 'readonly' : ''; ?>>
                        <?php endif; ?>

                        <div class="db-field-meta">
                          Tipo: <code><?php echo htmlspecialchars($col['type']); ?></code>
                          <?php if ($col['null']): ?>
                            &bull; <label style="display: inline; font-size: 11px; cursor: pointer;"><input type="checkbox" name="null_flags[<?php echo htmlspecialchars($colName); ?>]" value="1"> Marcar como NULL</label>
                          <?php endif; ?>
                          <?php if ($col['comment'] !== ''): ?>
                            &bull; <em><?php echo htmlspecialchars($col['comment']); ?></em>
                          <?php endif; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>

                  <div style="display: flex; gap: 10px; margin-top: 14px;">
                    <button type="submit" class="btn primary"><i class="fa-solid fa-floppy-disk"></i> Guardar Registro</button>
                    <a href="<?php echo htmlspecialchars(route_url('database') . '&table=' . urlencode($activeTable)); ?>" class="btn secondary">Cancelar</a>
                  </div>
                </form>
              </div>

            <!-- MODO: EDITAR REGISTRO -->
            <?php elseif ($action === 'edit' && $editRow): ?>
              <div style="margin-top: 18px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                  <h5 style="margin: 0; font-size: 16px; font-weight: 600;"><i class="fa-solid fa-pen-to-square"></i> Modificar Registro</h5>
                  <a href="<?php echo htmlspecialchars(route_url('database') . '&table=' . urlencode($activeTable)); ?>" class="btn secondary" style="padding: 4px 10px; font-size: 12px;">Volver</a>
                </div>

                <form method="post" action="<?php echo htmlspecialchars(route_url('database') . '&table=' . urlencode($activeTable)); ?>" class="stack compact">
                  <input type="hidden" name="form_action" value="update">

                  <!-- Claves primarias para WHERE -->
                  <?php foreach ($primaryKeys as $pkCol): ?>
                    <input type="hidden" name="pk_values[<?php echo htmlspecialchars($pkCol); ?>]" value="<?php echo htmlspecialchars((string)($editRow[$pkCol] ?? '')); ?>">
                  <?php endforeach; ?>

                  <div class="grid two">
                    <?php foreach ($columns as $colName => $col): ?>
                      <?php
                      $isAutoInc = (stripos($col['extra'], 'auto_increment') !== false);
                      $isPk = ($col['key'] === 'PRI');
                      $typeLower = strtolower($col['type']);
                      $currentVal = $editRow[$colName] ?? null;
                      $isNull = ($currentVal === null);
                      ?>
                      <div class="field">
                        <label for="edit_col_<?php echo htmlspecialchars($colName); ?>">
                          <code><?php echo htmlspecialchars($colName); ?></code>
                          <?php if ($isPk): ?><span class="pk-badge" title="Clave Primaria"><i class="fa-solid fa-key"></i> PK</span><?php endif; ?>
                          <?php if ($isAutoInc): ?><span class="chip" style="font-size: 10px; padding: 1px 5px;">auto_increment</span><?php endif; ?>
                        </label>

                        <?php if (strpos($typeLower, 'text') !== false || strpos($typeLower, 'json') !== false): ?>
                          <textarea id="edit_col_<?php echo htmlspecialchars($colName); ?>" name="<?php echo htmlspecialchars($colName); ?>" rows="4"><?php echo htmlspecialchars((string)$currentVal); ?></textarea>
                        <?php elseif (strpos($typeLower, 'enum(') === 0): ?>
                          <?php
                          preg_match("/^enum\((.*)\)$/i", $col['type'], $enumMatches);
                          $enumValues = [];
                          if (!empty($enumMatches[1])) {
                            $rawEnums = explode(',', $enumMatches[1]);
                            foreach ($rawEnums as $eVal) {
                              $enumValues[] = trim($eVal, " '\"");
                            }
                          }
                          ?>
                          <select id="edit_col_<?php echo htmlspecialchars($colName); ?>" name="<?php echo htmlspecialchars($colName); ?>">
                            <?php if ($col['null']): ?><option value="" <?php echo $isNull ? 'selected' : ''; ?>>[NULL / Vacío]</option><?php endif; ?>
                            <?php foreach ($enumValues as $ev): ?>
                              <option value="<?php echo htmlspecialchars($ev); ?>" <?php echo ((string)$currentVal === $ev) ? 'selected' : ''; ?>><?php echo htmlspecialchars($ev); ?></option>
                            <?php endforeach; ?>
                          </select>
                        <?php elseif (strpos($typeLower, 'date') === 0 && strpos($typeLower, 'datetime') === false): ?>
                          <input type="date" id="edit_col_<?php echo htmlspecialchars($colName); ?>" name="<?php echo htmlspecialchars($colName); ?>" value="<?php echo htmlspecialchars((string)$currentVal); ?>">
                        <?php elseif (strpos($typeLower, 'datetime') !== false || strpos($typeLower, 'timestamp') !== false): ?>
                          <?php
                          $dtFormatted = '';
                          if ($currentVal && $currentVal !== '0000-00-00 00:00:00') {
                            $dtObj = DateTime::createFromFormat('Y-m-d H:i:s', (string)$currentVal);
                            if ($dtObj) {
                              $dtFormatted = $dtObj->format('Y-m-d\TH:i');
                            }
                          }
                          ?>
                          <input type="datetime-local" id="edit_col_<?php echo htmlspecialchars($colName); ?>" name="<?php echo htmlspecialchars($colName); ?>" value="<?php echo htmlspecialchars($dtFormatted); ?>">
                        <?php elseif (strpos($typeLower, 'int') !== false || strpos($typeLower, 'decimal') !== false || strpos($typeLower, 'float') !== false): ?>
                          <input type="number" step="any" id="edit_col_<?php echo htmlspecialchars($colName); ?>" name="<?php echo htmlspecialchars($colName); ?>" value="<?php echo htmlspecialchars((string)$currentVal); ?>" <?php echo $isAutoInc ? 'readonly' : ''; ?>>
                        <?php else: ?>
                          <input type="text" id="edit_col_<?php echo htmlspecialchars($colName); ?>" name="<?php echo htmlspecialchars($colName); ?>" value="<?php echo ($colName === 'password_hash') ? '' : htmlspecialchars((string)$currentVal); ?>" placeholder="<?php echo ($colName === 'password_hash') ? '(Dejar vacío para no cambiar la contraseña)' : ''; ?>" <?php echo $isAutoInc ? 'readonly' : ''; ?>>
                        <?php endif; ?>

                        <div class="db-field-meta">
                          Tipo: <code><?php echo htmlspecialchars($col['type']); ?></code>
                          <?php if ($col['null']): ?>
                            &bull; <label style="display: inline; font-size: 11px; cursor: pointer;"><input type="checkbox" name="null_flags[<?php echo htmlspecialchars($colName); ?>]" value="1" <?php echo $isNull ? 'checked' : ''; ?>> Marcar como NULL</label>
                          <?php endif; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>

                  <div style="display: flex; gap: 10px; margin-top: 14px;">
                    <button type="submit" class="btn primary"><i class="fa-solid fa-floppy-disk"></i> Guardar Cambios</button>
                    <a href="<?php echo htmlspecialchars(route_url('database') . '&table=' . urlencode($activeTable)); ?>" class="btn secondary">Cancelar</a>
                  </div>
                </form>
              </div>

            <!-- MODO: BROWSE / VISUALIZAR REGISTROS -->
            <?php else: ?>
              <div style="margin-top: 14px;">
                <!-- Filtro y buscador -->
                <form method="get" class="filters" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 14px;">
                  <input type="hidden" name="route" value="database">
                  <input type="hidden" name="table" value="<?php echo htmlspecialchars($activeTable); ?>">

                  <div style="display: flex; gap: 8px; align-items: center; flex: 1; min-width: 260px;">
                    <input type="text" name="search" placeholder="Buscar texto en cualquier columna..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; padding: 7px 10px; font-size: 13px; border-radius: var(--radius-sm); border: 1px solid var(--app-border);">
                    <button type="submit" class="btn secondary" style="padding: 7px 12px; font-size: 13px;"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
                    <?php if ($search !== ''): ?>
                      <a href="<?php echo htmlspecialchars(route_url('database') . '&table=' . urlencode($activeTable)); ?>" class="btn ghost" style="padding: 7px 10px; font-size: 13px;">Limpiar</a>
                    <?php endif; ?>
                  </div>

                  <div style="display: flex; gap: 8px; align-items: center;">
                    <label style="font-size: 12px; color: var(--app-muted); margin: 0;">Filas por página:</label>
                    <select name="per_page" onchange="this.form.submit()" style="padding: 5px 8px; font-size: 12px; border-radius: var(--radius-sm); border: 1px solid var(--app-border);">
                      <?php foreach ([10, 25, 50, 100, 250] as $pp): ?>
                        <option value="<?php echo $pp; ?>" <?php echo $perPage === $pp ? 'selected' : ''; ?>><?php echo $pp; ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </form>

                <!-- Tabla de datos -->
                <div style="display: block; width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; clear: both; box-sizing: border-box;">
                  <table style="width: 100%; min-width: 800px; border-collapse: collapse; font-size: 13px;">
                    <thead>
                      <tr>
                        <th style="width: 110px; text-align: center;">Acciones</th>
                        <?php foreach ($columns as $colName => $col): ?>
                          <?php
                          $isSorted = ($sort === $colName);
                          $nextOrder = ($isSorted && $order === 'ASC') ? 'DESC' : 'ASC';
                          $sortUrl = route_url('database') . '&table=' . urlencode($activeTable) . '&sort=' . urlencode($colName) . '&order=' . $nextOrder . ($search !== '' ? '&search=' . urlencode($search) : '') . '&per_page=' . $perPage;
                          ?>
                          <th style="white-space: nowrap;">
                            <a class="table-sort-header" href="<?php echo htmlspecialchars($sortUrl); ?>" title="Ordenar por <?php echo htmlspecialchars($colName); ?>">
                              <?php echo htmlspecialchars($colName); ?>
                              <?php if ($col['key'] === 'PRI'): ?><span class="pk-badge"><i class="fa-solid fa-key"></i></span><?php endif; ?>
                              <?php if ($isSorted): ?>
                                <i class="fa-solid fa-arrow-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>" style="font-size: 10px; color: var(--app-accent);"></i>
                              <?php endif; ?>
                            </a>
                          </th>
                        <?php endforeach; ?>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $rIdx => $row): ?>
                          <?php
                          $editParams = [];
                          foreach ($primaryKeys as $pk) {
                            $editParams['pk_values[' . $pk . ']'] = (string)($row[$pk] ?? '');
                          }
                          $editUrl = route_url('database') . '&table=' . urlencode($activeTable) . '&action=edit&' . http_build_query($editParams);
                          ?>
                          <tr>
                            <td style="text-align: center; white-space: nowrap;">
                              <div style="display: inline-flex; gap: 4px; align-items: center;">
                                <a href="<?php echo htmlspecialchars($editUrl); ?>" class="btn secondary" style="padding: 4px 8px; font-size: 11px;" title="Editar fila">
                                  <i class="fa-solid fa-pen-to-square"></i>
                                </a>

                                <?php if ($canDelete): ?>
                                  <form method="post" action="<?php echo htmlspecialchars(route_url('database') . '&table=' . urlencode($activeTable)); ?>" style="display: inline; margin: 0;" onsubmit="return confirm('¿Estás seguro de eliminar este registro?');">
                                    <input type="hidden" name="form_action" value="delete">
                                    <?php foreach ($primaryKeys as $pk): ?>
                                      <input type="hidden" name="pk_values[<?php echo htmlspecialchars($pk); ?>]" value="<?php echo htmlspecialchars((string)($row[$pk] ?? '')); ?>">
                                    <?php endforeach; ?>
                                    <button type="submit" class="btn danger" style="padding: 4px 8px; font-size: 11px;" title="Eliminar fila">
                                      <i class="fa-solid fa-trash"></i>
                                    </button>
                                  </form>
                                <?php endif; ?>
                              </div>
                            </td>

                            <?php foreach ($columns as $colName => $col): ?>
                              <?php
                              $val = $row[$colName] ?? null;
                              $valStr = (string)$val;
                              $isPassword = ($colName === 'password_hash' || strpos($colName, 'pass') !== false);
                              ?>
                              <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($valStr); ?>">
                                <?php if ($val === null): ?>
                                  <span class="null-badge">NULL</span>
                                <?php elseif ($isPassword && strlen($valStr) > 20): ?>
                                  <span style="font-family: monospace; opacity: 0.6; font-size: 11px;"><?php echo htmlspecialchars(substr($valStr, 0, 14)) . '...'; ?></span>
                                <?php elseif (strlen($valStr) > 60): ?>
                                  <?php echo htmlspecialchars(substr($valStr, 0, 60)) . '...'; ?>
                                <?php else: ?>
                                  <?php echo htmlspecialchars($valStr); ?>
                                <?php endif; ?>
                              </td>
                            <?php endforeach; ?>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr>
                          <td colspan="<?php echo count($columns) + 1; ?>" style="text-align: center; padding: 28px; color: var(--app-muted);">
                            <i class="fa-regular fa-folder-open" style="font-size: 26px; margin-bottom: 8px; display: block; opacity: 0.5;"></i>
                            No se encontraron registros en la tabla <code><?php echo htmlspecialchars($activeTable); ?></code><?php echo $search !== '' ? ' para la búsqueda actual.' : '.'; ?>
                          </td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

                <!-- Paginación -->
                <?php if ($totalPages > 1 || $totalCount > 0): ?>
                  <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-top: 14px;">
                    <div style="font-size: 12px; color: var(--app-muted);">
                      Mostrando registros <strong><?php echo $totalCount > 0 ? ($page - 1) * $perPage + 1 : 0; ?></strong> a <strong><?php echo min($page * $perPage, $totalCount); ?></strong> de un total de <strong><?php echo $totalCount; ?></strong>.
                    </div>

                    <?php if ($totalPages > 1): ?>
                      <div class="pagination">
                        <?php
                        $buildPageUrl = function ($p) use ($activeTable, $search, $sort, $order, $perPage) {
                          return route_url('database') . '&table=' . urlencode($activeTable) . '&page=' . $p . ($search !== '' ? '&search=' . urlencode($search) : '') . ($sort !== '' ? '&sort=' . urlencode($sort) . '&order=' . $order : '') . '&per_page=' . $perPage;
                        };
                        ?>

                        <a href="<?php echo htmlspecialchars($buildPageUrl(1)); ?>" class="<?php echo $page <= 1 ? 'disabled' : ''; ?>" title="Primera página">&laquo;</a>
                        <a href="<?php echo htmlspecialchars($buildPageUrl(max(1, $page - 1))); ?>" class="<?php echo $page <= 1 ? 'disabled' : ''; ?>" title="Página anterior">&lsaquo;</a>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        for ($p = $startPage; $p <= $endPage; $p++):
                        ?>
                          <?php if ($p === $page): ?>
                            <span class="current"><?php echo $p; ?></span>
                          <?php else: ?>
                            <a href="<?php echo htmlspecialchars($buildPageUrl($p)); ?>"><?php echo $p; ?></a>
                          <?php endif; ?>
                        <?php endfor; ?>

                        <a href="<?php echo htmlspecialchars($buildPageUrl(min($totalPages, $page + 1))); ?>" class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>" title="Página siguiente">&rsaquo;</a>
                        <a href="<?php echo htmlspecialchars($buildPageUrl($totalPages)); ?>" class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>" title="Última página">&raquo;</a>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="card" style="text-align: center; padding: 40px; color: var(--app-muted);">
            <i class="fa-solid fa-database" style="font-size: 40px; margin-bottom: 12px; opacity: 0.4;"></i>
            <h4>No hay tablas disponibles</h4>
            <p class="subtle">No se detectaron tablas en la base de datos conectada.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
  function filterDbTables(keyword) {
    var filter = keyword.toLowerCase().trim();
    var list = document.getElementById('dbTableList');
    if (!list) return;
    var items = list.getElementsByClassName('db-table-item');
    for (var i = 0; i < items.length; i++) {
      var tableName = items[i].getAttribute('data-table-name') || '';
      if (filter === '' || tableName.indexOf(filter) !== -1) {
        items[i].style.display = 'flex';
      } else {
        items[i].style.display = 'none';
      }
    }
  }
</script>

