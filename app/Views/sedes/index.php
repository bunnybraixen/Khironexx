<?php
$messages = $sedesData['messages'] ?? [];
$errors = $sedesData['errors'] ?? [];
$canManage = (bool)($sedesData['canManage'] ?? false);
$canDelete = (bool)($sedesData['canDelete'] ?? false);
$rows = $sedesData['rows'] ?? [];
$viewId = (int)($sedesData['viewId'] ?? 0);
$viewSede = $sedesData['viewSede'] ?? null;
$viewFotos = $sedesData['viewFotos'] ?? [];
$filterName = $sedesData['filterName'] ?? '';
$filterRegion = $sedesData['filterRegion'] ?? '';
$filterExtranjero = $sedesData['filterExtranjero'] ?? '';
$draft = $sedesData['draft'] ?? [];
$editDraft = $sedesData['editDraft'] ?? [];

$draftValue = function ($key, $default = '') use ($draft) {
  if (!array_key_exists($key, $draft)) {
    return $default;
  }
  return (string)$draft[$key];
};
$editSource = $editDraft ?: (is_array($viewSede) ? $viewSede : []);
$editValue = function ($key, $default = '') use ($editSource) {
  if (!array_key_exists($key, $editSource)) {
    return $default;
  }
  return (string)$editSource[$key];
};

$regionComunas = [
  'Arica y Parinacota' => ['Arica', 'Camarones', 'General Lagos', 'Putre'],
  'Tarapaca' => ['Alto Hospicio', 'Camina', 'Colchane', 'Huara', 'Iquique', 'Pica', 'Pozo Almonte'],
  'Antofagasta' => ['Antofagasta', 'Calama', 'Maria Elena', 'Mejillones', 'Ollague', 'San Pedro de Atacama', 'Sierra Gorda', 'Taltal', 'Tocopilla'],
  'Atacama' => ['Alto del Carmen', 'Caldera', 'Chanaral', 'Copiapo', 'Diego de Almagro', 'Freirina', 'Huasco', 'Tierra Amarilla', 'Vallenar'],
  'Coquimbo' => ['Andacollo', 'Canela', 'Combarbala', 'Coquimbo', 'Illapel', 'La Higuera', 'La Serena', 'Los Vilos', 'Monte Patria', 'Ovalle', 'Paihuano', 'Punitaqui', 'Rio Hurtado', 'Salamanca', 'Vicuna'],
  'Valparaiso' => ['Algarrobo', 'Cabildo', 'Calera', 'Cartagena', 'Casablanca', 'Concon', 'El Quisco', 'El Tabo', 'Hijuelas', 'La Cruz', 'La Ligua', 'Limache', 'Los Andes', 'Nogales', 'Olmue', 'Petorca', 'Puchuncavi', 'Quilpue', 'Quillota', 'Quintero', 'San Antonio', 'San Esteban', 'San Felipe', 'Santa Maria', 'Valparaiso', 'Villa Alemana', 'Vina del Mar'],
  'Metropolitana' => ['Cerrillos', 'Cerro Navia', 'Conchali', 'El Bosque', 'Estacion Central', 'Huechuraba', 'Independencia', 'La Cisterna', 'La Florida', 'La Granja', 'La Pintana', 'La Reina', 'Las Condes', 'Lo Barnechea', 'Lo Espejo', 'Lo Prado', 'Macul', 'Maipu', 'Nunoa', 'Pedro Aguirre Cerda', 'Penalolen', 'Providencia', 'Pudahuel', 'Puente Alto', 'Quilicura', 'Quinta Normal', 'Recoleta', 'Renca', 'San Bernardo', 'San Joaquin', 'San Miguel', 'San Ramon', 'Santiago', 'Vitacura'],
  'OHiggins' => ['Chimbarongo', 'Codegua', 'Coinco', 'Coltauco', 'Donihue', 'Graneros', 'Las Cabras', 'Machali', 'Mostazal', 'Nancagua', 'Navidad', 'Olivar', 'Palmilla', 'Paredones', 'Peralillo', 'Pichidegua', 'Pichilemu', 'Placilla', 'Pumanque', 'Quinta de Tilcoco', 'Rancagua', 'Rengo', 'Requinoa', 'San Fernando', 'San Vicente'],
  'Maule' => ['Cauquenes', 'Chanco', 'Colbun', 'Constitucion', 'Curepto', 'Curico', 'Empedrado', 'Hualane', 'Linares', 'Longavi', 'Maule', 'Molina', 'Parral', 'Pelarco', 'Pelluhue', 'Pencahue', 'Rauco', 'Retiro', 'Rio Claro', 'Romeral', 'Sagrada Familia', 'San Clemente', 'San Javier', 'San Rafael', 'Talca', 'Teno', 'Vichuquen', 'Villa Alegre', 'Yerbas Buenas'],
  'Nuble' => ['Bulnes', 'Chillan', 'Chillan Viejo', 'Cobquecura', 'Coelemu', 'Coihueco', 'El Carmen', 'Ninhue', 'Pemuco', 'Pinto', 'Portezuelo', 'Quillon', 'Quirihue', 'Ranquil', 'San Carlos', 'San Fabian', 'San Ignacio', 'San Nicolas', 'Trehuaco', 'Yungay'],
  'Biobio' => ['Alto Biobio', 'Arauco', 'Cabrero', 'Canete', 'Chiguayante', 'Concepcion', 'Contulmo', 'Coronel', 'Curanilahue', 'Florida', 'Hualpen', 'Hualqui', 'Laja', 'Lebu', 'Los Alamos', 'Los Angeles', 'Lota', 'Mulchen', 'Nacimiento', 'Negrete', 'Penco', 'Quilaco', 'Quilleco', 'San Pedro de la Paz', 'San Rosendo', 'Santa Barbara', 'Santa Juana', 'Talcahuano', 'Tirua', 'Tome', 'Tucapel', 'Yumbel'],
  'Araucania' => ['Angol', 'Carahue', 'Cholchol', 'Collipulli', 'Cunco', 'Curacautin', 'Curarrehue', 'Ercilla', 'Freire', 'Galvarino', 'Gorbea', 'Lautaro', 'Loncoche', 'Lonquimay', 'Los Sauces', 'Lumaco', 'Melipeuco', 'Nueva Imperial', 'Padre Las Casas', 'Perquenco', 'Pitrufquen', 'Pucon', 'Puren', 'Renaico', 'Saavedra', 'Temuco', 'Teodoro Schmidt', 'Tolten', 'Traiguen', 'Victoria', 'Vilcun', 'Villarrica'],
  'Los Rios' => ['Corral', 'Futrono', 'Lago Ranco', 'Lanco', 'La Union', 'Los Lagos', 'Mariquina', 'Mafil', 'Paillaco', 'Panguipulli', 'Rio Bueno', 'Valdivia'],
  'Los Lagos' => ['Ancud', 'Calbuco', 'Castro', 'Chaiten', 'Cochamo', 'Curaco de Velez', 'Dalcahue', 'Fresia', 'Frutillar', 'Futaleufu', 'Hualaihue', 'Llanquihue', 'Los Muermos', 'Maullin', 'Osorno', 'Palena', 'Puerto Montt', 'Puerto Octay', 'Puerto Varas', 'Puqueldon', 'Purranque', 'Puyehue', 'Queilen', 'Quellon', 'Quemchi', 'Quinchao', 'Rio Negro', 'San Juan de la Costa', 'San Pablo'],
  'Aysen' => ['Aysen', 'Chile Chico', 'Cisnes', 'Cochrane', 'Coyhaique', 'Guaitecas', 'Lago Verde', 'OHiggins', 'Rio Ibanez', 'Tortel'],
  'Magallanes' => ['Antartica', 'Cabo de Hornos', 'Laguna Blanca', 'Natales', 'Porvenir', 'Primavera', 'Punta Arenas', 'Rio Verde', 'San Gregorio', 'Timaukel', 'Torres del Paine'],
];
?>
<style>
  .sede-photo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
    gap: 12px;
  }

  .sede-photo-card {
    border: 1px solid var(--app-border);
    border-radius: var(--radius-sm);
    background: #fff;
    padding: 8px;
    display: grid;
    gap: 8px;
  }

  .sede-photo-card img {
    width: 100%;
    height: 120px;
    border-radius: var(--radius-sm);
    object-fit: cover;
    background: #f8fafc;
  }

  .sede-preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 8px;
    margin-top: 8px;
  }

  .sede-preview-grid img {
    width: 100%;
    height: 90px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid var(--app-border);
  }
</style>

<div class="stack">
  <div class="card">
    <div class="card-header">
      <div>
        <h3>Sedes de capacitaciones</h3>
        <p class="subtle">Registro y detalle de lugares donde se realizan capacitaciones.</p>
      </div>
      <span class="chip">Administracion</span>
    </div>

    <?php foreach ($messages as $message): ?>
      <div class="notice"><?php echo htmlspecialchars($message); ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $error): ?>
      <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <?php if ($canManage): ?>
      <form method="post" enctype="multipart/form-data" class="stack compact" id="sedes-create-form">
        <input type="hidden" name="form_type" value="sede_create">
        <div class="grid two">
          <div class="field">
            <label>Encargado</label>
            <input name="encargado" required value="<?php echo htmlspecialchars($draftValue('encargado')); ?>">
          </div>
          <div class="field">
            <label>Numero y/o correo de contacto</label>
            <input name="contacto" value="<?php echo htmlspecialchars($draftValue('contacto')); ?>">
          </div>

          <!-- AQUÍ ESTÁ EL CAMBIO PARA LA PRIMERA PARTE -->
          <div class="field">
            <label>Origen de la Sede</label>
            <select name="extranjero" data-sede-extranjero>
              <option value="0" <?php echo $draftValue('extranjero') !== '1' ? 'selected' : ''; ?>>Regional / Nacional</option>
              <option value="1" <?php echo $draftValue('extranjero') === '1' ? 'selected' : ''; ?>>Extranjera</option>
            </select>
          </div>

          <div class="field" data-sede-ubicacion-extranjero <?php echo $draftValue('extranjero') === '1' ? '' : 'hidden'; ?>>
            <label>Pais o ubicacion (extranjero)</label>
            <input name="ubicacion_extranjero" value="<?php echo htmlspecialchars($draftValue('ubicacion_extranjero')); ?>" <?php echo $draftValue('extranjero') === '1' ? '' : 'disabled'; ?>>
          </div>
          <div class="field">
            <label>Region</label>
            <select name="region" data-sede-region <?php echo $draftValue('extranjero') === '1' ? 'disabled' : ''; ?>>
              <option value="">Selecciona region</option>
              <?php foreach (array_keys($regionComunas) as $regionName): ?>
                <option value="<?php echo htmlspecialchars($regionName); ?>" <?php echo $draftValue('region') === $regionName ? 'selected' : ''; ?>><?php echo htmlspecialchars($regionName); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>Comuna</label>
            <select name="comuna" data-sede-comuna data-current="<?php echo htmlspecialchars($draftValue('comuna')); ?>" <?php echo $draftValue('extranjero') === '1' ? 'disabled' : ''; ?>></select>
          </div>
          <div class="field" data-sede-comuna-otra <?php echo $draftValue('comuna') === 'otra' ? '' : 'hidden'; ?>>
            <label>Otra comuna</label>
            <input name="comuna_otra" value="<?php echo htmlspecialchars($draftValue('comuna_otra')); ?>" <?php echo $draftValue('comuna') === 'otra' ? '' : 'disabled'; ?>>
          </div>
          <div class="field">
            <label>Direccion</label>
            <input name="direccion" value="<?php echo htmlspecialchars($draftValue('direccion')); ?>">
          </div>
          <div class="field">
            <label>Cantidad de personas</label>
            <input name="capacidad_personas" type="number" min="0" value="<?php echo htmlspecialchars($draftValue('capacidad_personas')); ?>">
          </div>
          <div class="field full">
            <label>Fotos del lugar</label>
            <input type="file" name="fotos[]" multiple accept="image/*" data-sede-fotos-input>
            <div class="sede-preview-grid" data-sede-fotos-preview></div>
          </div>
        </div>
        <button class="btn" type="submit">Guardar sede</button>
      </form>
      <hr>
    <?php else: ?>
      <div class="notice">Solo lectura: no tienes permisos para crear/editar sedes.</div>
    <?php endif; ?>

    <form method="get" class="filters">
      <input type="hidden" name="route" value="sedes">
      <div class="grid two">
        <div class="field">
          <label>Buscar</label>
          <input name="q" value="<?php echo htmlspecialchars($filterName); ?>" placeholder="Encargado, contacto o direccion">
        </div>
        <div class="field">
          <label>Region</label>
          <input name="region" value="<?php echo htmlspecialchars($filterRegion); ?>">
        </div>
        <div class="field">
          <label>Origen</label>
          <select name="extranjero">
            <option value="">Todas</option>
            <option value="no" <?php echo $filterExtranjero === 'no' ? 'selected' : ''; ?>>Nacional</option>
            <option value="si" <?php echo $filterExtranjero === 'si' ? 'selected' : ''; ?>>Extranjero</option>
          </select>
        </div>
      </div>
      <div class="filter-actions">
        <button class="btn" type="submit">Aplicar filtros</button>
        <a class="btn secondary" href="<?php echo htmlspecialchars(route_url('sedes')); ?>">Limpiar</a>
      </div>
    </form>
    <div style="display: block; width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; clear: both; margin-bottom: 20px;">
      <table style="width: 100%; min-width: 600px; border-collapse: collapse;">
        <thead>
          <tr>
            <th>ID</th>
            <th>Encargado</th>
            <th>Contacto</th>
            <th>Ubicacion</th>
            <th>Direccion</th>
            <th>Capacidad</th>
            <th>Fotos</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <?php
            $isForeign = !empty($row['extranjero']);
            $locationText = $isForeign
              ? (trim((string)($row['ubicacion_extranjero'] ?? '')) !== '' ? $row['ubicacion_extranjero'] : 'Extranjero')
              : trim((string)($row['region'] ?? '') . ' / ' . (string)($row['comuna'] ?? ''), ' /');
            ?>
            <tr>
              <td><?php echo (int)$row['id']; ?></td>
              <td><a class="link" href="<?php echo htmlspecialchars(route_url('sedes') . '&view=' . (int)$row['id']); ?>"><?php echo htmlspecialchars((string)$row['encargado']); ?></a></td>
              <td><?php echo htmlspecialchars((string)($row['contacto'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars($locationText !== '' ? $locationText : '-'); ?></td>
              <td><?php echo htmlspecialchars((string)($row['direccion'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars((string)($row['capacidad_personas'] ?? '0')); ?></td>
              <td><?php echo (int)($row['fotos_count'] ?? 0); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($viewSede): ?>
    <?php
    $editRegion = $editValue('region', (string)($viewSede['region'] ?? ''));
    $editComuna = $editValue('comuna', (string)($viewSede['comuna'] ?? ''));
    $editIsForeign = $editValue('extranjero', (string)($viewSede['extranjero'] ?? '0')) === '1';
    if (!$editIsForeign && !isset($regionComunas[$editRegion])) {
      $editRegion = '';
    }
    ?>
    <div class="grid two detail-grid">
      <div class="card">
        <h4 class="section-title">Detalle de sede</h4>
        <div class="info-grid">
          <div><span class="info-label">Encargado</span><span><?php echo htmlspecialchars((string)$viewSede['encargado']); ?></span></div>
          <div><span class="info-label">Contacto</span><span><?php echo htmlspecialchars((string)($viewSede['contacto'] ?? '')); ?></span></div>
          <div><span class="info-label">Origen</span><span><?php echo !empty($viewSede['extranjero']) ? 'Extranjero' : 'Nacional'; ?></span></div>
          <div><span class="info-label">Region</span><span><?php echo htmlspecialchars((string)($viewSede['region'] ?? '')); ?></span></div>
          <div><span class="info-label">Comuna / ubicacion</span><span><?php echo htmlspecialchars((string)($viewSede['comuna'] ?? $viewSede['ubicacion_extranjero'] ?? '')); ?></span></div>
          <div><span class="info-label">Direccion</span><span><?php echo htmlspecialchars((string)($viewSede['direccion'] ?? '')); ?></span></div>
          <div><span class="info-label">Capacidad</span><span><?php echo htmlspecialchars((string)($viewSede['capacidad_personas'] ?? '0')); ?></span></div>
        </div>

        <?php if ($canManage): ?>
          <hr>
          <form method="post" enctype="multipart/form-data" class="stack compact" id="sedes-edit-form">
            <input type="hidden" name="form_type" value="sede_update">
            <input type="hidden" name="sede_id" value="<?php echo (int)$viewSede['id']; ?>">
            <div class="grid two">
              <div class="field">
                <label>Encargado</label>
                <input name="encargado" required value="<?php echo htmlspecialchars($editValue('encargado', (string)$viewSede['encargado'])); ?>">
              </div>
              <div class="field">
                <label>Numero y/o correo de contacto</label>
                <input name="contacto" value="<?php echo htmlspecialchars($editValue('contacto', (string)($viewSede['contacto'] ?? ''))); ?>">
              </div>
              <div class="field">
                <label>Origen de la Sede</label>
                <select name="extranjero" data-sede-extranjero>
                  <option value="0" <?php echo !$editIsForeign ? 'selected' : ''; ?>>Regional / Nacional</option>
                  <option value="1" <?php echo $editIsForeign ? 'selected' : ''; ?>>Extranjera</option>
                </select>
              </div>
              <div class="field" data-sede-ubicacion-extranjero <?php echo $editIsForeign ? '' : 'hidden'; ?>>
                <label>Pais o ubicacion (extranjero)</label>
                <input name="ubicacion_extranjero" value="<?php echo htmlspecialchars($editValue('ubicacion_extranjero', (string)($viewSede['ubicacion_extranjero'] ?? ''))); ?>" <?php echo $editIsForeign ? '' : 'disabled'; ?>>
              </div>
              <div class="field">
                <label>Region</label>
                <select name="region" data-sede-region <?php echo $editIsForeign ? 'disabled' : ''; ?>>
                  <option value="">Selecciona region</option>
                  <?php foreach (array_keys($regionComunas) as $regionName): ?>
                    <option value="<?php echo htmlspecialchars($regionName); ?>" <?php echo $editRegion === $regionName ? 'selected' : ''; ?>><?php echo htmlspecialchars($regionName); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field">
                <label>Comuna</label>
                <select name="comuna" data-sede-comuna data-current="<?php echo htmlspecialchars($editComuna); ?>" <?php echo $editIsForeign ? 'disabled' : ''; ?>></select>
              </div>
              <div class="field" data-sede-comuna-otra <?php echo $editComuna === 'otra' ? '' : 'hidden'; ?>>
                <label>Otra comuna</label>
                <input name="comuna_otra" value="<?php echo htmlspecialchars($editValue('comuna_otra')); ?>" <?php echo $editComuna === 'otra' ? '' : 'disabled'; ?>>
              </div>
              <div class="field">
                <label>Direccion</label>
                <input name="direccion" value="<?php echo htmlspecialchars($editValue('direccion', (string)($viewSede['direccion'] ?? ''))); ?>">
              </div>
              <div class="field">
                <label>Cantidad de personas</label>
                <input name="capacidad_personas" type="number" min="0" value="<?php echo htmlspecialchars($editValue('capacidad_personas', (string)($viewSede['capacidad_personas'] ?? '0'))); ?>">
              </div>
              <div class="field full">
                <label>Agregar mas fotos</label>
                <input type="file" name="fotos[]" multiple accept="image/*" data-sede-fotos-input>
                <div class="sede-preview-grid" data-sede-fotos-preview></div>
              </div>
            </div>
            <button class="btn" type="submit">Guardar cambios</button>
          </form>
        <?php endif; ?>

        <?php if ($canDelete): ?>
          <form method="post" onsubmit="return confirm('?Eliminar sede y todas sus fotos?');">
            <input type="hidden" name="form_type" value="sede_delete">
            <input type="hidden" name="sede_id" value="<?php echo (int)$viewSede['id']; ?>">
            <button class="btn secondary" type="submit">Eliminar sede</button>
          </form>
        <?php endif; ?>
      </div>

      <div class="card">
        <h4 class="section-title">Galeria de fotos</h4>
        <?php if ($viewFotos): ?>
          <div class="sede-photo-grid">
            <?php foreach ($viewFotos as $photo): ?>
              <div class="sede-photo-card">
                <a href="<?php echo htmlspecialchars(asset((string)$photo['ruta'])); ?>" target="_blank" rel="noopener">
                  <img src="<?php echo htmlspecialchars(asset((string)$photo['ruta'])); ?>" alt="Foto sede">
                </a>
                <span class="subtle"><?php echo htmlspecialchars((string)($photo['original_name'] ?? '')); ?></span>
                <?php if ($canManage): ?>
                  <form method="post" onsubmit="return confirm('?Eliminar foto?');">
                    <input type="hidden" name="form_type" value="sede_foto_delete">
                    <input type="hidden" name="sede_id" value="<?php echo (int)$viewSede['id']; ?>">
                    <input type="hidden" name="photo_id" value="<?php echo (int)$photo['id']; ?>">
                    <button class="btn secondary" type="submit">Eliminar foto</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="subtle">Esta sede aun no tiene fotos.</p>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
  (function () {
    var regionComunas = <?php echo json_encode($regionComunas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function initSedeForm(form) {
      if (!form) {
        return;
      }

      var regionSelect = form.querySelector('[data-sede-region]');
      var comunaSelect = form.querySelector('[data-sede-comuna]');
      var comunaOtraField = form.querySelector('[data-sede-comuna-otra]');
      var comunaOtraInput = form.querySelector('[name="comuna_otra"]');
      var extranjeroSelect = form.querySelector('[data-sede-extranjero]'); // Cambiado a Select
      var ubicacionField = form.querySelector('[data-sede-ubicacion-extranjero]');
      var ubicacionInput = form.querySelector('[name="ubicacion_extranjero"]');
      var photosInput = form.querySelector('[data-sede-fotos-input]');
      var photosPreview = form.querySelector('[data-sede-fotos-preview]');

      function addOption(select, value, label) {
        var option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        select.appendChild(option);
      }

      function hasOption(select, value) {
        for (var i = 0; i < select.options.length; i++) {
          if (select.options[i].value === value) {
            return true;
          }
        }
        return false;
      }

      function fillComunas(selectedValue) {
        if (!comunaSelect) {
          return;
        }
        var region = regionSelect ? regionSelect.value : '';
        var comunas = (region && regionComunas[region]) ? regionComunas[region] : [];
        comunaSelect.innerHTML = '';
        addOption(comunaSelect, '', 'Selecciona comuna');
        comunas.forEach(function (name) {
          addOption(comunaSelect, name, name);
        });
        addOption(comunaSelect, 'otra', 'Otra comuna');

        if (selectedValue && hasOption(comunaSelect, selectedValue)) {
          comunaSelect.value = selectedValue;
        } else if (selectedValue) {
          comunaSelect.value = 'otra';
          if (comunaOtraInput && comunaOtraInput.value === '') {
            comunaOtraInput.value = selectedValue;
          }
        }
      }

      function syncComunaOtra() {
        if (!comunaSelect || !comunaOtraField || !comunaOtraInput || !extranjeroSelect) {
          return;
        }
        // Adaptado para leer el value del select ("1" significa Extranjero)
        var isForeign = extranjeroSelect.value === '1';
        var show = !isForeign && comunaSelect.value === 'otra';
        comunaOtraField.hidden = !show;
        comunaOtraInput.disabled = !show;
        comunaOtraInput.required = show;
      }

      function syncExtranjero() {
        // Adaptado para leer el value del select ("1" significa Extranjero)
        var isForeign = extranjeroSelect && extranjeroSelect.value === '1';
        if (regionSelect) {
          regionSelect.disabled = isForeign;
          regionSelect.required = !isForeign;
        }
        if (comunaSelect) {
          comunaSelect.disabled = isForeign;
          comunaSelect.required = !isForeign;
        }
        if (ubicacionField) {
          ubicacionField.hidden = !isForeign;
        }
        if (ubicacionInput) {
          ubicacionInput.disabled = !isForeign;
          ubicacionInput.required = isForeign;
        }
        syncComunaOtra();
      }

      function renderPreview() {
        if (!photosInput || !photosPreview) {
          return;
        }
        photosPreview.innerHTML = '';
        var files = photosInput.files || [];
        for (var i = 0; i < files.length; i++) {
          var file = files[i];
          if (!file.type || file.type.indexOf('image/') !== 0) {
            continue;
          }
          var img = document.createElement('img');
          img.src = URL.createObjectURL(file);
          img.alt = 'Vista previa';
          photosPreview.appendChild(img);
        }
      }

      if (regionSelect) {
        regionSelect.addEventListener('change', function () {
          fillComunas('');
          syncComunaOtra();
        });
      }
      if (comunaSelect) {
        comunaSelect.addEventListener('change', syncComunaOtra);
      }
      if (extranjeroSelect) {
        extranjeroSelect.addEventListener('change', syncExtranjero); // Escucha el cambio del select
      }
      if (photosInput) {
        photosInput.addEventListener('change', renderPreview);
      }

      fillComunas(comunaSelect ? (comunaSelect.getAttribute('data-current') || '') : '');
      syncExtranjero();
      renderPreview();
    }

    initSedeForm(document.getElementById('sedes-create-form'));
    initSedeForm(document.getElementById('sedes-edit-form'));
  })();
</script>