<?php
$postulanteStatuses = $postulantesData['postulanteStatuses'] ?? [];
$messages = $postulantesData['messages'] ?? [];
$errors = $postulantesData['errors'] ?? [];
$filterName = $postulantesData['filterName'] ?? '';
$filterUniversidad = $postulantesData['filterUniversidad'] ?? '';
$filterCarrera = $postulantesData['filterCarrera'] ?? '';
$filterArea = $postulantesData['filterArea'] ?? '';
$filterStatus = $postulantesData['filterStatus'] ?? '';
$postulantes = $postulantesData['postulantes'] ?? null;
$viewPostulante = $postulantesData['viewPostulante'] ?? null;
$historial = $postulantesData['historial'] ?? [];
$entrevistas = $postulantesData['entrevistas'] ?? [];
$docs = $postulantesData['docs'] ?? [];
$obs = $postulantesData['obs'] ?? [];
$canAdd = $postulantesData['canAdd'] ?? false;
$canModify = $postulantesData['canModify'] ?? false;
$canDelete = $postulantesData['canDelete'] ?? false;
$carreras = $postulantesData['carreras'] ?? [];
$universidades = $postulantesData['universidades'] ?? [];
$activeModule = $postulantesData['activeModule'] ?? 'registrar';
if (!in_array($activeModule, ['registrar', 'buscar'], true)) {
  $activeModule = 'registrar';
}
$registerDraft = $postulantesData['registerDraft'] ?? [];
$draftValue = function ($key, $default = '') use ($registerDraft) {
  if (!array_key_exists($key, $registerDraft)) {
    return $default;
  }
  return (string)$registerDraft[$key];
};

$currentCarrera = $draftValue('carrera');
$currentCarreraOtra = $draftValue('carrera_otra');
if ($currentCarrera !== '' && $currentCarrera !== 'otro' && !in_array($currentCarrera, $carreras, true)) {
  $currentCarreraOtra = $currentCarrera;
  $currentCarrera = 'otro';
}

$currentUniversidad = $draftValue('universidad');
$currentUniversidadOtra = $draftValue('universidad_otra');
if ($currentUniversidad !== '' && $currentUniversidad !== 'otro' && !in_array($currentUniversidad, $universidades, true)) {
  $currentUniversidadOtra = $currentUniversidad;
  $currentUniversidad = 'otro';
}

$searchUniversidades = $universidades;
if ($filterUniversidad !== '' && !in_array($filterUniversidad, $searchUniversidades, true)) {
  $searchUniversidades[] = $filterUniversidad;
}
$searchCarreras = $carreras;
if ($filterCarrera !== '' && !in_array($filterCarrera, $searchCarreras, true)) {
  $searchCarreras[] = $filterCarrera;
}
?>
<style>
  .postulantes-tabs {
    display: flex;
    gap: 10px;
    margin: 12px 0 18px;
  }

  .postulantes-tab {
    min-width: 130px;
  }

  .postulantes-tab.is-active {
    background: linear-gradient(120deg, var(--app-accent), var(--app-accent-2));
    color: #f5fbfb;
  }

  .postulantes-panel[hidden] {
    display: none !important;
  }
</style>
<div class="stack">
  <div class="card">
    <div class="card-header">
      <div>
        <h3>Postulantes</h3>
        <p class="subtle">Registro centralizado de postulantes a practica.</p>
      </div>
      <span class="chip">RRHH</span>
    </div>
    <?php foreach ($messages as $message): ?>
      <div class="notice"><?php echo htmlspecialchars($message); ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $error): ?>
      <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <div class="postulantes-tabs" data-postulantes-tabs style="display: flex; flex-wrap: wrap; gap: 10px; width: 100%;">
      <button type="button" class="btn secondary postulantes-tab <?php echo $activeModule === 'registrar' ? 'is-active' : ''; ?>" data-module-target="registrar">Registrar</button>
      <button type="button" class="btn secondary postulantes-tab <?php echo $activeModule === 'buscar' ? 'is-active' : ''; ?>" data-module-target="buscar">Buscar</button>
    </div>

    <section class="postulantes-panel" data-postulantes-panel="registrar" <?php echo $activeModule === 'registrar' ? '' : 'hidden'; ?>>
      <?php if ($canAdd): ?>
        <form id="postulante-register-form" method="post" class="stack compact">
          <input type="hidden" name="form_type" value="postulante_create">
          <input type="hidden" name="ui_module" value="registrar">
          <div class="grid two">
            <div class="field">
              <label>Nombre completo</label>
              <input name="full_name" required value="<?php echo htmlspecialchars($draftValue('full_name')); ?>">
            </div>
            <div class="field">
              <label>Correo personal</label>
              <input name="email" type="email" value="<?php echo htmlspecialchars($draftValue('email')); ?>">
            </div>
            <div class="field">
              <label>Numero de contacto</label>
              <input name="telefono" value="<?php echo htmlspecialchars($draftValue('telefono')); ?>">
            </div>
            <div class="field">
              <label>Carrera</label>
              <select name="carrera" id="postulante-carrera-select">
                <option value="">Selecciona carrera</option>
                <?php foreach ($carreras as $option): ?>
                  <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $currentCarrera === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                <?php endforeach; ?>
                <option value="otro" <?php echo $currentCarrera === 'otro' ? 'selected' : ''; ?>>Otro</option>
              </select>
            </div>
            <div class="field" data-postulante-carrera-otra <?php echo $currentCarrera === 'otro' ? '' : 'hidden'; ?>>
              <label>Otra carrera</label>
              <input name="carrera_otra" value="<?php echo htmlspecialchars($currentCarreraOtra); ?>" <?php echo $currentCarrera === 'otro' ? '' : 'disabled'; ?>>
            </div>
            <div class="field">
              <label>Universidad de origen</label>
              <select name="universidad" id="postulante-universidad-select">
                <option value="">Selecciona universidad</option>
                <?php foreach ($universidades as $option): ?>
                  <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $currentUniversidad === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                <?php endforeach; ?>
                <option value="otro" <?php echo $currentUniversidad === 'otro' ? 'selected' : ''; ?>>Otro</option>
              </select>
            </div>
            <div class="field" data-postulante-universidad-otra <?php echo $currentUniversidad === 'otro' ? '' : 'hidden'; ?>>
              <label>Otra universidad</label>
              <input name="universidad_otra" value="<?php echo htmlspecialchars($currentUniversidadOtra); ?>" <?php echo $currentUniversidad === 'otro' ? '' : 'disabled'; ?>>
            </div>
            <div class="field">
              <label>Area postulacion</label>
              <input name="area_postulacion" value="<?php echo htmlspecialchars($draftValue('area_postulacion')); ?>">
            </div>
            <div class="field">
              <label>Jefe/a de carrera</label>
              <input name="jefe_carrera" value="<?php echo htmlspecialchars($draftValue('jefe_carrera')); ?>">
            </div>
            <div class="field">
              <label>Correo jefe de carrera</label>
              <input name="jefe_carrera_email" type="email" value="<?php echo htmlspecialchars($draftValue('jefe_carrera_email')); ?>">
            </div>
            <div class="field">
              <label>Horas exigidas</label>
              <input name="horas_exigidas" type="number" min="0" value="<?php echo htmlspecialchars($draftValue('horas_exigidas')); ?>">
            </div>
          </div>
          <button class="btn" type="submit">Registrar postulante</button>
        </form>
      <?php else: ?>
        <div class="notice">Solo lectura: no tienes permisos para crear postulantes.</div>
      <?php endif; ?>
    </section>

    <section class="postulantes-panel" data-postulantes-panel="buscar" <?php echo $activeModule === 'buscar' ? '' : 'hidden'; ?>>
      <form id="postulante-search-form" method="get" class="filters">
        <h3>Buscador de Postulantes</h3>
        <input type="hidden" name="route" value="postulantes">
        <input type="hidden" name="ui_module" value="buscar">
        <div class="grid two">
          <div class="field">
            <label>Buscar por nombre</label>
            <input name="q" value="<?php echo htmlspecialchars($filterName); ?>">
          </div>
          <div class="field">
            <label>Universidad</label>
            <select name="universidad">
              <option value="">Todas</option>
              <?php foreach ($searchUniversidades as $option): ?>
                <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $filterUniversidad === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>Carrera</label>
            <select name="carrera">
              <option value="">Todas</option>
              <?php foreach ($searchCarreras as $option): ?>
                <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $filterCarrera === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>Area</label>
            <input name="area" value="<?php echo htmlspecialchars($filterArea); ?>">
          </div>
          <div class="field">
            <label>Estado</label>
            <select name="status">
              <option value="">Todos</option>
              <?php foreach ($postulanteStatuses as $key => $label): ?>
                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $filterStatus === $key ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($label); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="filter-actions">
          <button class="btn" type="submit">Aplicar filtros</button>
          <a class="btn secondary" href="<?php echo htmlspecialchars(route_url('postulantes') . '&ui_module=buscar'); ?>">Limpiar</a>
        </div>
      </form>
<div style="display: block; width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; clear: both; margin-bottom: 20px;">
  <table style="width: 100%; min-width: 600px; border-collapse: collapse;">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Correo</th>
            <th>Universidad</th>
            <th>Carrera</th>
            <th>Area</th>
            <th>Estado</th>
            <th>Actualizado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($postulantes): while ($row = $postulantes->fetch_assoc()): ?>
              <?php $statusKey = $row['status'] ?? 'registrado'; ?>
              <tr>
                <td><?php echo (int)$row['id']; ?></td>
                <td><a class="link" href="<?php echo htmlspecialchars(route_url('postulantes') . '&ui_module=buscar&view=' . (int)$row['id']); ?>"><?php echo htmlspecialchars($row['full_name']); ?></a></td>
                <td><?php echo htmlspecialchars($row['email'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['universidad'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['carrera'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['area_postulacion'] ?? ''); ?></td>
                <td><span class="status-pill status-<?php echo htmlspecialchars($statusKey); ?>"><?php echo htmlspecialchars($postulanteStatuses[$statusKey] ?? $statusKey); ?></span></td>
                <td><?php echo htmlspecialchars($row['status_updated_at'] ?? $row['created_at']); ?></td>
                <td>
                  <?php if ($canDelete): ?>
                    <form method="post" onsubmit="return confirm('Eliminar postulante <?php echo htmlspecialchars($row['full_name']); ?>? Esta accion eliminara el postulante definitivamente.');">
                      <input type="hidden" name="form_type" value="postulante_delete">
                      <input type="hidden" name="postulante_id" value="<?php echo (int)$row['id']; ?>">
                      <input type="hidden" name="confirm_delete" value="si">
                      <input type="hidden" name="ui_module" value="buscar">
                      <button class="btn danger" type="submit">Eliminar</button>
                    </form>
                  <?php else: ?>
                    <span class="subtle">Solo lectura</span>
                  <?php endif; ?>
                </td>
              </tr>
          <?php endwhile;
          endif; ?>
        </tbody>
      </table>
    </section>
    </div>
  </div>

  <?php if ($viewPostulante && $activeModule === 'buscar'): ?>
    <div class="grid two detail-grid">
      <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
          <h4 class="section-title" style="margin: 0;">Detalle del postulante</h4>
          <div style="display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end;">
            <a class="btn secondary" style="font-size: 0.8em; padding: 5px 10px; white-space: nowrap;" href="<?php echo htmlspecialchars(route_url('postulantes') . '&ui_module=buscar'); ?>">Deseleccionar</a>
            <?php if ($canDelete): ?>
              <button class="btn danger" style="font-size: 0.8em; padding: 5px 10px; white-space: nowrap;" type="button" onclick="mostrarConfirmacionEliminar()">Eliminar</button>
            <?php endif; ?>
          </div>
        </div>
        <div class="info-grid">
          <div><span class="info-label">Nombre</span><span><?php echo htmlspecialchars($viewPostulante['full_name']); ?></span></div>
          <div><span class="info-label">Correo</span><span><?php echo htmlspecialchars($viewPostulante['email'] ?? ''); ?></span></div>
          <div><span class="info-label">Telefono</span><span><?php echo htmlspecialchars($viewPostulante['telefono'] ?? ''); ?></span></div>
          <div><span class="info-label">Universidad</span><span><?php echo htmlspecialchars($viewPostulante['universidad'] ?? ''); ?></span></div>
          <div><span class="info-label">Carrera</span><span><?php echo htmlspecialchars($viewPostulante['carrera'] ?? ''); ?></span></div>
          <div><span class="info-label">Horas exigidas</span><span><?php echo htmlspecialchars((string)($viewPostulante['horas_exigidas'] ?? '')); ?></span></div>
          <div><span class="info-label">Area</span><span><?php echo htmlspecialchars($viewPostulante['area_postulacion'] ?? ''); ?></span></div>
          <div><span class="info-label">Jefe/a carrera</span><span><?php echo htmlspecialchars($viewPostulante['jefe_carrera'] ?? ''); ?></span></div>
        </div>
        <?php if ($canModify): ?>
          <form method="post" class="stack compact">
            <input type="hidden" name="form_type" value="postulante_status">
            <input type="hidden" name="postulante_id" value="<?php echo (int)$viewPostulante['id']; ?>">
            <input type="hidden" name="ui_module" value="buscar">
            <div class="grid two">
              <div class="field">
                <label>Actualizar estado</label>
                <select name="status">
                  <?php foreach ($postulanteStatuses as $key => $label): ?>
                    <option value="<?php echo htmlspecialchars($key); ?>" <?php echo ($viewPostulante['status'] ?? '') === $key ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($label); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field">
                <label>Nota</label>
                <input name="nota" placeholder="Observacion de cambio">
              </div>
            </div>
            <button class="btn" type="submit">Guardar estado</button>
          </form>
        <?php endif; ?>
      </div>
      <div class="card">
        <h4 class="section-title">Entrevistas</h4>
        <?php if ($canAdd): ?>
          <form method="post" class="stack compact">
            <input type="hidden" name="form_type" value="postulante_entrevista">
            <input type="hidden" name="postulante_id" value="<?php echo (int)$viewPostulante['id']; ?>">
            <input type="hidden" name="ui_module" value="buscar">
            <div class="grid two">
              <div class="field">
                <label>Tipo</label>
                <select name="tipo">
                  <option value="psicolaboral">Entrevista psicolaboral</option>
                  <option value="entrevista_area">Entrevista de area</option>
                </select>
              </div>
              <div class="field">
                <label>Fecha</label>
                <input type="date" name="fecha">
              </div>
              <div class="field full">
                <label>Observaciones</label>
                <textarea name="observaciones" rows="3"></textarea>
              </div>
            </div>
            <button class="btn" type="submit">Agregar entrevista</button>
          </form>
        <?php endif; ?>
        <?php if ($entrevistas): ?>
          <table class="table-compact">
            <thead>
              <tr>
                <th>Tipo</th>
                <th>Fecha</th>
                <th>Observaciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($entrevistas as $row): ?>
                <tr>
                  <td><?php echo htmlspecialchars($postulanteStatuses[$row['tipo']] ?? $row['tipo']); ?></td>
                  <td><?php echo htmlspecialchars($row['fecha'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($row['observaciones'] ?? ''); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p class="subtle">Sin entrevistas registradas.</p>
        <?php endif; ?>
      </div>
      <div class="card">
        <h4 class="section-title">Documentos</h4>
        <?php if ($canAdd): ?>
          <form method="post" class="stack compact">
            <input type="hidden" name="form_type" value="postulante_documento">
            <input type="hidden" name="postulante_id" value="<?php echo (int)$viewPostulante['id']; ?>">
            <input type="hidden" name="ui_module" value="buscar">
            <div class="grid two">
              <div class="field">
                <label>Titulo</label>
                <input name="titulo" required>
              </div>
              <div class="field">
                <label>Tipo</label>
                <input name="tipo">
              </div>
              <div class="field full">
                <label>Ruta/URL</label>
                <input name="ruta">
              </div>
            </div>
            <button class="btn" type="submit">Agregar documento</button>
          </form>
        <?php endif; ?>
        <?php if ($docs): ?>
          <ul class="list-plain">
            <?php foreach ($docs as $row): ?>
              <li>
                <strong><?php echo htmlspecialchars($row['titulo']); ?></strong>
                <span class="subtle"><?php echo htmlspecialchars($row['tipo'] ?? ''); ?></span>
                <?php if (!empty($row['ruta'])): ?>
                  <a class="link" href="<?php echo htmlspecialchars($row['ruta']); ?>" target="_blank" rel="noopener">Abrir</a>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="subtle">Sin documentos asociados.</p>
        <?php endif; ?>
      </div>
      <div class="card">
        <h4 class="section-title">Observaciones</h4>
        <?php if ($canAdd): ?>
          <form method="post" class="stack compact">
            <input type="hidden" name="form_type" value="postulante_observacion">
            <input type="hidden" name="postulante_id" value="<?php echo (int)$viewPostulante['id']; ?>">
            <input type="hidden" name="ui_module" value="buscar">
            <div class="field">
              <label>Detalle</label>
              <textarea name="detalle" rows="3" required></textarea>
            </div>
            <button class="btn" type="submit">Agregar observacion</button>
          </form>
        <?php endif; ?>
        <?php if ($obs): ?>
          <ul class="list-plain">
            <?php foreach ($obs as $row): ?>
              <li>
                <span><?php echo htmlspecialchars($row['detalle']); ?></span>
                <span class="subtle"><?php echo htmlspecialchars($row['created_at'] ?? ''); ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="subtle">Sin observaciones.</p>
        <?php endif; ?>
      </div>
      <div class="card">
        <h4 class="section-title">Historial de estado</h4>
        <?php if ($historial): ?>
          <table class="table-compact">
            <thead>
              <tr>
                <th>Estado</th>
                <th>Nota</th>
                <th>Fecha</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($historial as $row): ?>
                <tr>
                  <td><?php echo htmlspecialchars($postulanteStatuses[$row['estado']] ?? $row['estado']); ?></td>
                  <td><?php echo htmlspecialchars($row['nota'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($row['created_at'] ?? ''); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p class="subtle">Sin historial registrado.</p>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<form id="deleteForm" method="post" style="display: none;">
  <input type="hidden" name="form_type" value="postulante_delete">
  <input type="hidden" name="postulante_id" id="deletePostulanteId" value="">
  <input type="hidden" name="confirm_delete" id="deleteConfirm" value="">
  <input type="hidden" name="ui_module" value="buscar">
</form>

<script>
  (function () {
    var tabContainer = document.querySelector('[data-postulantes-tabs]');
    if (!tabContainer) {
      return;
    }

    var moduleButtons = tabContainer.querySelectorAll('[data-module-target]');
    var modulePanels = document.querySelectorAll('[data-postulantes-panel]');
    var initialModule = '<?php echo htmlspecialchars($activeModule, ENT_QUOTES); ?>';

    function setActiveModule(module, persist) {
      moduleButtons.forEach(function (button) {
        var active = button.getAttribute('data-module-target') === module;
        button.classList.toggle('is-active', active);
      });
      modulePanels.forEach(function (panel) {
        panel.hidden = panel.getAttribute('data-postulantes-panel') !== module;
      });
      if (persist) {
        try {
          localStorage.setItem('khx_postulantes_active_module', module);
        } catch (err) {
        }
        try {
          var url = new URL(window.location.href);
          url.searchParams.set('ui_module', module);
          history.replaceState({}, '', url.toString());
        } catch (err) {
        }
      }
    }

    moduleButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        var module = button.getAttribute('data-module-target') || 'registrar';
        setActiveModule(module, true);
      });
    });

    setActiveModule(initialModule, false);

    function bindPersistentForm(form, storageKey, keepServerValue) {
      if (!form) {
        return;
      }

      function serialize() {
        var data = {};
        var fields = form.querySelectorAll('input, select, textarea');
        fields.forEach(function (field) {
          if (!field.name) {
            return;
          }
          if (field.type === 'password' || field.type === 'submit' || field.type === 'button') {
            return;
          }
          if (field.type === 'checkbox') {
            data[field.name] = field.checked ? '1' : '0';
          } else {
            data[field.name] = field.value;
          }
        });
        return data;
      }

      function restore() {
        var raw;
        try {
          raw = localStorage.getItem(storageKey);
        } catch (err) {
          raw = null;
        }
        if (!raw) {
          return;
        }

        var data;
        try {
          data = JSON.parse(raw);
        } catch (err) {
          return;
        }

        var fields = form.querySelectorAll('input, select, textarea');
        fields.forEach(function (field) {
          if (!field.name || !Object.prototype.hasOwnProperty.call(data, field.name)) {
            return;
          }
          if (keepServerValue && field.value !== '' && field.type !== 'checkbox') {
            return;
          }
          if (field.type === 'checkbox') {
            field.checked = data[field.name] === '1';
          } else {
            field.value = data[field.name];
          }
        });
      }

      function save() {
        try {
          localStorage.setItem(storageKey, JSON.stringify(serialize()));
        } catch (err) {
        }
      }

      restore();
      form.addEventListener('input', save);
      form.addEventListener('change', save);
      form.addEventListener('submit', save);
    }

    bindPersistentForm(document.getElementById('postulante-register-form'), 'khx_postulantes_registrar_form', true);
    bindPersistentForm(document.getElementById('postulante-search-form'), 'khx_postulantes_buscar_form', true);

    function bindOtherToggle(selectId, wrapperSelector, inputName) {
      var select = document.getElementById(selectId);
      var wrapper = document.querySelector(wrapperSelector);
      var input = document.querySelector('input[name="' + inputName + '"]');
      if (!select || !wrapper || !input) {
        return;
      }

      function sync() {
        var show = select.value === 'otro';
        wrapper.hidden = !show;
        input.disabled = !show;
      }

      select.addEventListener('change', sync);
      sync();
    }

    bindOtherToggle('postulante-carrera-select', '[data-postulante-carrera-otra]', 'carrera_otra');
    bindOtherToggle('postulante-universidad-select', '[data-postulante-universidad-otra]', 'universidad_otra');
  })();

  function mostrarConfirmacionEliminar() {
    if (confirm('Estas seguro? Esta accion eliminara el postulante definitivamente.')) {
      var postulanteId = <?php echo (int)($viewPostulante['id'] ?? 0); ?>;
      if (postulanteId > 0) {
        document.getElementById('deletePostulanteId').value = postulanteId;
        document.getElementById('deleteConfirm').value = 'si';
        document.getElementById('deleteForm').submit();
      }
    }
  }
</script>
