<?php
$departments = $practicantesData['departments'] ?? [];
$positions = $practicantesData['positions'] ?? [];
$messages = $practicantesData['messages'] ?? [];
$errors = $practicantesData['errors'] ?? [];
$viewId = (int)($practicantesData['viewId'] ?? 0);
$editId = (int)($practicantesData['editId'] ?? 0);
$filterName = $practicantesData['filterName'] ?? '';
$filterUniversidad = $practicantesData['filterUniversidad'] ?? '';
$filterCarrera = $practicantesData['filterCarrera'] ?? '';
$filterArea = $practicantesData['filterArea'] ?? '';
$filterStatus = $practicantesData['filterStatus'] ?? '';
$sort = $practicantesData['sort'] ?? 'fecha';
$rows = $practicantesData['rows'] ?? null;
$departmentsList = $practicantesData['departmentsList'] ?? null;
$positionsList = $practicantesData['positionsList'] ?? null;
$viewPract = $practicantesData['viewPract'] ?? null;
$horasList = $practicantesData['horasList'] ?? [];
$docs = $practicantesData['docs'] ?? [];
$obs = $practicantesData['obs'] ?? [];
$totalHoras = (int)($practicantesData['totalHoras'] ?? 0);
$horasPendientes = (int)($practicantesData['horasPendientes'] ?? 0);
$canAdd = $practicantesData['canAdd'] ?? false;
$canAdmin = $practicantesData['canAdmin'] ?? false;
$carreras = $practicantesData['carreras'] ?? [];
$universidades = $practicantesData['universidades'] ?? [];
$registerDraft = $practicantesData['registerDraft'] ?? [];
$editDraft = $practicantesData['editDraft'] ?? [];
$activeModule = $practicantesData['activeModule'] ?? 'registrar';
$horasPorDia = (int)($practicantesData['horasPorDia'] ?? 8);
if (!in_array($activeModule, ['registrar', 'buscar', 'editar'], true)) {
  $activeModule = 'registrar';
}
$catalogEdit = $practicantesData['catalogEdit'] ?? null;
$catalogEditType = (string)($practicantesData['catalogEditType'] ?? '');
$isEditingDepartment = is_array($catalogEdit) && $catalogEditType === 'department';
$isEditingPosition = is_array($catalogEdit) && $catalogEditType === 'position';
$catalogFormUrl = route_url('practicantes') . '&ui_module=' . urlencode($activeModule);

$draftValue = function ($key, $default = '') use ($registerDraft) {
  if (!array_key_exists($key, $registerDraft)) {
    return $default;
  }
  return (string)$registerDraft[$key];
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

$currentRegion = $draftValue('region');
$currentComuna = $draftValue('comuna');
$currentComunaOtra = $draftValue('comuna_otra');
$selectedRegionComunas = isset($regionComunas[$currentRegion]) ? $regionComunas[$currentRegion] : [];
if ($currentComuna !== '' && $currentComuna !== 'otra' && !in_array($currentComuna, $selectedRegionComunas, true)) {
  $currentComunaOtra = $currentComuna;
  $currentComuna = 'otra';
}

$draftExtranjero = $draftValue('extranjero') === '1';
$draftUbicacionExtranjero = $draftValue('ubicacion_extranjero');

// calcular mes/dia de cumpleaños para el formulario
$draftCumpleMes = '';
$draftCumpleDia = '';
$rawCumple = $draftValue('fecha_cumpleanos');
if ($rawCumple !== '') {
  $d = DateTime::createFromFormat('Y-m-d', $rawCumple);
  if ($d) {
    $draftCumpleMes = (int)$d->format('m');
    $draftCumpleDia = (int)$d->format('d');
  }
}

$searchUniversidades = $universidades;
if ($filterUniversidad !== '' && !in_array($filterUniversidad, $searchUniversidades, true)) {
  $searchUniversidades[] = $filterUniversidad;
}
$searchCarreras = $carreras;
if ($filterCarrera !== '' && !in_array($filterCarrera, $searchCarreras, true)) {
  $searchCarreras[] = $filterCarrera;
}

$editSource = $editDraft ?: (is_array($viewPract) ? $viewPract : []);
$editValue = function ($key, $default = '') use ($editSource) {
  if (!array_key_exists($key, $editSource)) {
    return $default;
  }
  return (string)$editSource[$key];
};
$editCarrera = $editValue('carrera', (string)($viewPract['carrera'] ?? ''));
$editCarreraOtra = $editValue('carrera_otra');
if ($editCarrera !== '' && $editCarrera !== 'otro' && !in_array($editCarrera, $carreras, true)) {
  $editCarreraOtra = $editCarrera;
  $editCarrera = 'otro';
}
$editUniversidad = $editValue('universidad', (string)($viewPract['universidad'] ?? ''));
$editUniversidadOtra = $editValue('universidad_otra');
if ($editUniversidad !== '' && $editUniversidad !== 'otro' && !in_array($editUniversidad, $universidades, true)) {
  $editUniversidadOtra = $editUniversidad;
  $editUniversidad = 'otro';
}
$editExtranjero = $editValue('extranjero', (string)($viewPract['extranjero'] ?? '0')) === '1';
$editRegion = $editValue('region', (string)($viewPract['region'] ?? ''));
$editComuna = $editValue('comuna', (string)($viewPract['comuna'] ?? ''));
$editComunaOtra = $editValue('comuna_otra');
$editRegionComunas = isset($regionComunas[$editRegion]) ? $regionComunas[$editRegion] : [];
if ($editComuna !== '' && $editComuna !== 'otra' && !in_array($editComuna, $editRegionComunas, true)) {
  $editComunaOtra = $editComuna;
  $editComuna = 'otra';
}
$editCumpleMes = '';
$editCumpleDia = '';
$rawEditCumple = $editValue('fecha_cumpleanos', '');
if ($rawEditCumple !== '') {
  $d2 = DateTime::createFromFormat('Y-m-d', $rawEditCumple);
  if ($d2) {
    $editCumpleMes = (int)$d2->format('m');
    $editCumpleDia = (int)$d2->format('d');
  }
}
?>
<style>
  .practicantes-tabs {
    display: flex;
    gap: 10px;
    margin: 12px 0 18px;
  }

  .practicantes-tab {
    min-width: 130px;
  }

  .practicantes-tab.is-active {
    background: linear-gradient(120deg, var(--app-accent), var(--app-accent-2));
    color: #f5fbfb;
  }

  .practicantes-panel[hidden] {
    display: none !important;
  }

  .birthday-inputs {
    display: flex;
    gap: 6px;
  }

  .birthday-fields select {
    width: auto;
  }

  .field.photo-field {
    display: block;
  }

  .field-label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 6px;
  }

  .field-label-row label {
    margin: 0;
  }

  .field-inline-check {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 700;
    color: var(--app-muted);
  }

  .field-inline-check input {
    width: auto;
    margin: 0;
  }

  .table-wrapper {
    overflow-x: auto;
    margin-bottom: 16px;
    border-radius: 6px;
  }

  .table-wrapper table {
    width: 100%;
    min-width: 900px;
  }
</style>
<div class="stack">
  <div class="card">
    <div class="card-header">
      <div>
        <h3>Practicantes</h3>
        <p class="subtle">Control de practica activa con seguimiento de horas.</p>
      </div>
      <span class="chip">Control horas</span>
    </div>

    <?php foreach ($messages as $message): ?>
      <div class="notice"><?php echo htmlspecialchars($message); ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $error): ?>
      <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <div class="relatores-tabs" data-practicantes-tabs style="display: flex; flex-wrap: wrap; gap: 10px; width: 100%;">
      <button type="button" class="btn secondary practicantes-tab <?php echo $activeModule === 'registrar' ? 'is-active' : ''; ?>" data-module-target="registrar">Registrar</button>
      <button type="button" class="btn secondary practicantes-tab <?php echo $activeModule === 'buscar' ? 'is-active' : ''; ?>" data-module-target="buscar">Buscar</button>
      <button type="button" class="btn secondary practicantes-tab <?php echo $activeModule === 'editar' ? 'is-active' : ''; ?>" data-module-target="editar">Editar</button>
    </div>

    <section class="practicantes-panel" data-module-panel="registrar" <?php echo $activeModule === 'registrar' ? '' : 'hidden'; ?>>
      <?php if ($canAdd): ?>
        <form method="post" enctype="multipart/form-data" class="csv-upload">
          <input type="hidden" name="form_type" value="practicantes_csv">

          <input type="hidden" name="ui_module" value="registrar">
        <label>Importar practicantes desde CSV</label>
          <div class="csv-row" style="width: 100%; min-width: 0; max-width: 100%; overflow: hidden; box-sizing: border-box;">
            <div class="field" style="width: 100%; min-width: 0; max-width: 100%; overflow: hidden; box-sizing: border-box;">
              <input type="file" name="csv_file" accept=".csv,text/csv" required style="width: 100%; max-width: 100%; box-sizing: border-box;">
            </div>
            <button class="btn" type="submit">Subir CSV</button>
            <a class="btn secondary" href="<?php echo htmlspecialchars(route_url('practicantes') . '&export=template'); ?>">Descargar formato CSV</a>
          </div>
          <p class="subtle">Separador recomendado: punto y coma (;). Puedes usar coma o tabulacion.</p>
          <p class="subtle">Columnas: nombre_completo, correo, telefono, rut, departamento, cargo, correo_institucional, fecha_inicio, fecha_fin, fecha_cumpleanos (también solo mes/dia), horas_exigidas, carrera, universidad, region, comuna, extranjero, ubicacion_extranjero, estado.</p>
        </form>
        <hr>

        <form method="post" enctype="multipart/form-data" class="stack compact" id="practicante-register-form" data-module-form="registrar">
          <input type="hidden" name="form_type" value="practicante">
          <input type="hidden" name="ui_module" value="registrar">
          <div class="grid two">
            <div class="field">
              <label>Nombre</label>
              <input name="full_name" required value="<?php echo htmlspecialchars($draftValue('full_name')); ?>">
            </div>
            <div class="field">
              <label>Email personal</label>
              <input name="email" type="email" value="<?php echo htmlspecialchars($draftValue('email')); ?>">
            </div>
            <div class="field">
              <label>RUT</label>
              <input name="rut" value="<?php echo htmlspecialchars($draftValue('rut')); ?>">
            </div>
            <div class="field">
              <label>Telefono</label>
              <input name="telefono" value="<?php echo htmlspecialchars($draftValue('telefono')); ?>">
            </div>
            <div class="field birthday-fields">
              <label>Cumplea&ntilde;os</label>
              <div class="birthday-inputs">
                <select name="cumple_mes" required>
                  <option value="">Mes</option>
                  <?php
                  for ($m = 1; $m <= 12; $m++) {
                    $sel = $draftCumpleMes === $m ? ' selected' : '';
                    echo "                  <option value=\"$m\"$sel>$m</option>\n";
                  }
                  ?>
                </select>
                <select name="cumple_dia" required>
                  <option value="">Dia</option>
                  <?php
                  for ($d = 1; $d <= 31; $d++) {
                    $sel = $draftCumpleDia === $d ? ' selected' : '';
                    echo "                  <option value=\"$d\"$sel>$d</option>\n";
                  }
                  ?>
                </select>
              </div>
            </div>
            <div class="field">
              <label>Departamento</label>
              <select name="department_id">
                <option value="0">Sin asignar</option>
                <?php foreach ($departments as $id => $name): ?>
                  <option value="<?php echo (int)$id; ?>" <?php echo (int)$draftValue('department_id', '0') === (int)$id ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>Cargo</label>
              <select name="position_id">
                <option value="0">Sin asignar</option>
                <?php foreach ($positions as $id => $name): ?>
                  <option value="<?php echo (int)$id; ?>" <?php echo (int)$draftValue('position_id', '0') === (int)$id ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field photo-field">
              <label>Fotograf&iacute;a</label>
              <input type="file" name="photo" accept="image/*" required>
            </div>
            <div class="field">
              <label>Correo institucional</label>
              <input name="correo_institucional" type="email" value="<?php echo htmlspecialchars($draftValue('correo_institucional')); ?>">
            </div>
            <div class="field">
              <label>Horas exigidas</label>
              <input id="practicante-horas" name="horas_exigidas" type="number" min="0" value="<?php echo htmlspecialchars($draftValue('horas_exigidas')); ?>">
            </div>
            <div class="field">
              <label>Carrera</label>
              <select name="carrera" required id="practicante-carrera-select">
                <option value="">Selecciona</option>
                <?php foreach ($carreras as $option): ?>
                  <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $currentCarrera === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                <?php endforeach; ?>
                <option value="otro" <?php echo $currentCarrera === 'otro' ? 'selected' : ''; ?>>Otro</option>
              </select>
            </div>
            <div class="field" data-carrera-otra <?php echo $currentCarrera === 'otro' ? '' : 'hidden'; ?>>
              <label>Otra carrera</label>
              <input name="carrera_otra" value="<?php echo htmlspecialchars($currentCarreraOtra); ?>" <?php echo $currentCarrera === 'otro' ? '' : 'disabled'; ?>>
            </div>
            <div class="field">
              <label>Universidad</label>
              <select name="universidad" required id="practicante-universidad-select">
                <option value="">Selecciona</option>
                <?php foreach ($universidades as $option): ?>
                  <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $currentUniversidad === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                <?php endforeach; ?>
                <option value="otro" <?php echo $currentUniversidad === 'otro' ? 'selected' : ''; ?>>Otro</option>
              </select>
            </div>
            <div class="field" data-universidad-otra <?php echo $currentUniversidad === 'otro' ? '' : 'hidden'; ?>>
              <label>Otra universidad</label>
              <input name="universidad_otra" value="<?php echo htmlspecialchars($currentUniversidadOtra); ?>" <?php echo $currentUniversidad === 'otro' ? '' : 'disabled'; ?>>
            </div>
            <div class="field">
              <label>Fecha inicio</label>
              <input id="practicante-fecha-inicio" name="fecha_inicio" type="date" value="<?php echo htmlspecialchars($draftValue('fecha_inicio')); ?>">
            </div>
            <div class="field">
              <label>Fecha de termino</label>
              <input id="practicante-fecha-fin" name="fecha_fin" type="date" value="<?php echo htmlspecialchars($draftValue('fecha_fin')); ?>">
              <p class="subtle" id="practicante-fecha-fin-hint"></p>
            </div>
            <div class="field">
              <label>Region</label>
              <select id="practicante-region" name="region" <?php echo $draftExtranjero ? 'disabled' : ''; ?>>
                <option value="">Selecciona region</option>
                <?php foreach (array_keys($regionComunas) as $regionName): ?>
                  <option value="<?php echo htmlspecialchars($regionName); ?>" <?php echo $currentRegion === $regionName ? 'selected' : ''; ?>><?php echo htmlspecialchars($regionName); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <div class="field-label-row">
                <label for="practicante-comuna">Comuna</label>
                <label class="field-inline-check" for="practicante-extranjero">
                  <input id="practicante-extranjero" type="checkbox" name="extranjero" value="1" <?php echo $draftExtranjero ? 'checked' : ''; ?>>
                  Extranjero
                </label>
              </div>
              <select id="practicante-comuna" name="comuna" data-current="<?php echo htmlspecialchars($currentComuna); ?>" <?php echo $draftExtranjero ? 'disabled' : ''; ?>></select>
            </div>
            <div class="field" data-comuna-otra <?php echo $currentComuna === 'otra' && !$draftExtranjero ? '' : 'hidden'; ?>>
              <label>Otra comuna</label>
              <input name="comuna_otra" value="<?php echo htmlspecialchars($currentComunaOtra); ?>" <?php echo $currentComuna === 'otra' && !$draftExtranjero ? '' : 'disabled'; ?>>
            </div>
            <div class="field" data-ubicacion-extranjero <?php echo $draftExtranjero ? '' : 'hidden'; ?>>
              <label>Pais o ubicacion (extranjero)</label>
              <input name="ubicacion_extranjero" value="<?php echo htmlspecialchars($draftUbicacionExtranjero); ?>" <?php echo $draftExtranjero ? '' : 'disabled'; ?>>
            </div>
            <div class="field">
              <label>Estado</label>
              <select name="status">
                <option value="active" <?php echo $draftValue('status', 'active') === 'active' ? 'selected' : ''; ?>>Activo</option>
                <option value="inactive" <?php echo $draftValue('status') === 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
              </select>
            </div>
          </div>
          <button class="btn" type="submit">Agregar practicante</button>
        </form>
      <?php else: ?>
        <div class="notice">Solo lectura: no tienes permisos para agregar practicantes.</div>
      <?php endif; ?>
    </section>

    <section class="practicantes-panel" data-module-panel="buscar" <?php echo $activeModule === 'buscar' ? '' : 'hidden'; ?>>
      <form method="get" class="filters" data-module-form="buscar" id="practicante-search-form">
        <h3>Buscador de Practicantes</h3>
        <input type="hidden" name="route" value="practicantes">
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
              <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Activo</option>
              <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
            </select>
          </div>
          <div class="field">
            <label>Ordenar por</label>
            <select name="sort">
              <option value="fecha" <?php echo $sort === 'fecha' ? 'selected' : ''; ?>>Fecha de ingreso</option>
              <option value="cumplidas" <?php echo $sort === 'cumplidas' ? 'selected' : ''; ?>>Horas cumplidas</option>
              <option value="pendientes" <?php echo $sort === 'pendientes' ? 'selected' : ''; ?>>Horas pendientes</option>
            </select>
          </div>
        </div>
        <div class="filter-actions">
          <button class="btn" type="submit">Aplicar filtros</button>
          <a class="btn secondary" href="<?php echo htmlspecialchars(route_url('practicantes') . '&ui_module=buscar'); ?>">Limpiar</a>
        </div>
      </form>

      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>ID</th>
            <th>Foto</th>
            <th>Nombre</th>
            <th>Area</th>
            <th>Correo</th>
            <th>Correo institucional</th>
            <th>Inicio</th>
            <th>Horas exigidas</th>
            <th>Horas cumplidas</th>
            <th>Pendientes</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows): while ($row = $rows->fetch_assoc()): ?>
              <?php
              $horasExigidas = (int)($row['horas_exigidas'] ?? 0);
              $horasCumplidas = (int)($row['horas_cumplidas'] ?? 0);
              $pendientes = $horasExigidas > 0 ? max(0, $horasExigidas - $horasCumplidas) : 0;
              ?>
              <tr>
                <td><?php echo (int)$row['id']; ?></td>
                <td><?php
                    $thumb = '';
                    if (!empty($row['photo'])) {
                      $url = asset($row['photo']);
                      $thumb = '<img src="' . htmlspecialchars($url) . '" alt="Foto" style="max-height:50px;max-width:50px;">';
                    }
                    echo $thumb;
                    ?></td>
                <td><a class="link" href="<?php echo htmlspecialchars(route_url('practicantes') . '&ui_module=buscar&view=' . (int)$row['id']); ?>"><?php echo htmlspecialchars($row['full_name']); ?></a></td>
                <td><?php echo htmlspecialchars($row['department'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['email'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['correo_institucional'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['fecha_inicio'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars((string)$horasExigidas); ?></td>
                <td><?php echo htmlspecialchars((string)$horasCumplidas); ?></td>
                <td><?php echo htmlspecialchars((string)$pendientes); ?></td>
                <td><?php echo htmlspecialchars($row['status']); ?></td>
                <td>
                  <a class="btn warning" href="<?php echo htmlspecialchars(route_url('practicantes') . '&ui_module=editar&edit=' . (int)$row['id']); ?>">Editar</a>
                  <?php if ($canAdmin): ?>
                    <form method="post" style="display:inline-block;" onsubmit="return confirm('Eliminar practicante <?php echo htmlspecialchars($row['full_name']); ?>? Esta accion eliminara el practicante definitivamente.');">
                      <input type="hidden" name="form_type" value="practicante_delete">
                      <input type="hidden" name="practicante_id" value="<?php echo (int)$row['id']; ?>">
                      <input type="hidden" name="confirm_delete" value="si">
                      <input type="hidden" name="ui_module" value="buscar">
                      <button class="btn danger" type="submit">Eliminar</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
          <?php endwhile;
          endif; ?>
        </tbody>
      </table>
      </div>
    </section>

    <section class="practicantes-panel" data-module-panel="editar" <?php echo $activeModule === 'editar' ? '' : 'hidden'; ?>>
      <?php if (!$viewPract): ?>
        <div class="notice">Selecciona un practicante en la pestaña Buscar para editar sus datos.</div>
      <?php elseif (!$canAdd): ?>
        <div class="notice">Solo lectura: no tienes permisos para editar practicantes.</div>
      <?php else: ?>
        <form method="post" enctype="multipart/form-data" class="stack compact" id="practicante-edit-form">
          <input type="hidden" name="form_type" value="practicante_update">
          <input type="hidden" name="ui_module" value="editar">
          <input type="hidden" name="practicante_id" value="<?php echo (int)$viewPract['id']; ?>">
          <div class="grid two">
            <div class="field birthday-fields">
              <label>Cumplea&ntilde;os</label>
              <div class="birthday-inputs">
                <select name="cumple_mes" required>
                  <option value="">Mes</option>
                  <?php for ($m = 1; $m <= 12; $m++):
                    $sel = $editCumpleMes === $m ? ' selected' : '';
                    echo "                  <option value=\"$m\"$sel>$m</option>";
                    echo "\n";
                  endfor; ?>
                </select>
                <select name="cumple_dia" required>
                  <option value="">Dia</option>
                  <?php for ($d = 1; $d <= 31; $d++):
                    $sel = $editCumpleDia === $d ? ' selected' : '';
                    echo "                  <option value=\"$d\"$sel>$d</option>";
                    echo "\n";
                  endfor; ?>
                </select>
              </div>
            </div>
            <div class="field">
              <label>Estado</label>
              <select name="status">
                <option value="active" <?php echo $editValue('status', (string)($viewPract['status'] ?? 'active')) === 'active' ? 'selected' : ''; ?>>Activo</option>
                <option value="inactive" <?php echo $editValue('status', (string)($viewPract['status'] ?? 'active')) === 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
              </select>
            </div>
            <div class="field">
              <label>Fecha inicio</label>
              <input type="date" id="practicante-edit-fecha-inicio" name="fecha_inicio" value="<?php echo htmlspecialchars($editValue('fecha_inicio', (string)($viewPract['fecha_inicio'] ?? ''))); ?>">
            </div>
            <div class="field">
              <label>Horas exigidas</label>
              <input type="number" min="0" id="practicante-edit-horas" name="horas_exigidas" value="<?php echo htmlspecialchars($editValue('horas_exigidas', (string)($viewPract['horas_exigidas'] ?? '0'))); ?>">
            </div>
            <div class="field photo-field">
              <label>Fotograf&iacute;a (opcional reemplazo)</label>
              <input type="file" name="photo" accept="image/*">
            </div>
            <div class="field">
              <label>Fecha de termino</label>
              <input type="date" id="practicante-edit-fecha-fin" name="fecha_fin" value="<?php echo htmlspecialchars($editValue('fecha_fin', (string)($viewPract['fecha_fin'] ?? ''))); ?>">
            </div>
            <div class="field">
              <label>Carrera</label>
              <select name="carrera" id="practicante-edit-carrera-select" required>
                <option value="">Selecciona</option>
                <?php foreach ($carreras as $option): ?>
                  <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $editCarrera === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                <?php endforeach; ?>
                <option value="otro" <?php echo $editCarrera === 'otro' ? 'selected' : ''; ?>>Otro</option>
              </select>
            </div>
            <div class="field" data-edit-carrera-otra <?php echo $editCarrera === 'otro' ? '' : 'hidden'; ?>>
              <label>Otra carrera</label>
              <input name="carrera_otra" value="<?php echo htmlspecialchars($editCarreraOtra); ?>" <?php echo $editCarrera === 'otro' ? '' : 'disabled'; ?>>
            </div>
            <div class="field">
              <label>Universidad</label>
              <select name="universidad" id="practicante-edit-universidad-select" required>
                <option value="">Selecciona</option>
                <?php foreach ($universidades as $option): ?>
                  <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $editUniversidad === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                <?php endforeach; ?>
                <option value="otro" <?php echo $editUniversidad === 'otro' ? 'selected' : ''; ?>>Otro</option>
              </select>
            </div>
            <div class="field" data-edit-universidad-otra <?php echo $editUniversidad === 'otro' ? '' : 'hidden'; ?>>
              <label>Otra universidad</label>
              <input name="universidad_otra" value="<?php echo htmlspecialchars($editUniversidadOtra); ?>" <?php echo $editUniversidad === 'otro' ? '' : 'disabled'; ?>>
            </div>
            <div class="field">
              <label>Region</label>
              <select id="practicante-edit-region" name="region" <?php echo $editExtranjero ? 'disabled' : ''; ?>>
                <option value="">Selecciona region</option>
                <?php foreach (array_keys($regionComunas) as $regionName): ?>
                  <option value="<?php echo htmlspecialchars($regionName); ?>" <?php echo $editRegion === $regionName ? 'selected' : ''; ?>><?php echo htmlspecialchars($regionName); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <div class="field-label-row">
                <label for="practicante-edit-comuna">Comuna</label>
                <label class="field-inline-check" for="practicante-edit-extranjero">
                  <input id="practicante-edit-extranjero" type="checkbox" name="extranjero" value="1" <?php echo $editExtranjero ? 'checked' : ''; ?>>
                  Extranjero
                </label>
              </div>
              <select id="practicante-edit-comuna" name="comuna" data-current="<?php echo htmlspecialchars($editComuna); ?>" <?php echo $editExtranjero ? 'disabled' : ''; ?>></select>
            </div>
            <div class="field" data-edit-comuna-otra <?php echo $editComuna === 'otra' && !$editExtranjero ? '' : 'hidden'; ?>>
              <label>Otra comuna</label>
              <input name="comuna_otra" value="<?php echo htmlspecialchars($editComunaOtra); ?>" <?php echo $editComuna === 'otra' && !$editExtranjero ? '' : 'disabled'; ?>>
            </div>
            <div class="field" data-edit-ubicacion-extranjero <?php echo $editExtranjero ? '' : 'hidden'; ?>>
              <label>Pais o ubicacion (extranjero)</label>
              <input name="ubicacion_extranjero" value="<?php echo htmlspecialchars($editValue('ubicacion_extranjero', (string)($viewPract['ubicacion_extranjero'] ?? ''))); ?>" <?php echo $editExtranjero ? '' : 'disabled'; ?>>
            </div>
          </div>
          <button class="btn" type="submit">Guardar cambios del practicante</button>
        </form>
      <?php endif; ?>
    </section>
  </div>

  <?php if ($viewPract): ?>
    <?php
    $isExtranjeroView = !empty($viewPract['extranjero']);
    $regionView = trim((string)($viewPract['region'] ?? ''));
    $comunaView = trim((string)($viewPract['comuna'] ?? ''));
    $ubicacionExtranjeroView = trim((string)($viewPract['ubicacion_extranjero'] ?? ''));
    $ubicacionText = '-';
    if ($isExtranjeroView) {
      $ubicacionText = $ubicacionExtranjeroView !== '' ? $ubicacionExtranjeroView : ($comunaView !== '' ? $comunaView : 'Extranjero');
    } elseif ($regionView !== '' || $comunaView !== '') {
      $ubicacionText = trim($regionView . ' / ' . $comunaView, ' /');
    }
    $photoUrl = '';
    if (!empty($viewPract['photo'])) {
      $photoUrl = asset($viewPract['photo']);
    }
    ?>
    <div class="grid two detail-grid">
      <div class="card">
        <h4 class="section-title">Detalle del practicante</h4>
        <div class="info-grid">
          <?php if ($photoUrl !== ''): ?>
            <div><span class="info-label">Foto</span><span><img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="Foto" style="max-height:100px; max-width:100px;"></span></div>
          <?php endif; ?>
          <div><span class="info-label">Nombre</span><span><?php echo htmlspecialchars($viewPract['full_name']); ?></span></div>
          <div><span class="info-label">Area</span><span><?php echo htmlspecialchars($viewPract['department'] ?? ''); ?></span></div>
          <div><span class="info-label">Correo institucional</span><span><?php echo htmlspecialchars($viewPract['correo_institucional'] ?? ''); ?></span></div>
          <div><span class="info-label">Cumplea&ntilde;os</span><span><?php
                                                                      $bd = '';
                                                                      if (!empty($viewPract['fecha_cumpleanos'])) {
                                                                        $tmp = DateTime::createFromFormat('Y-m-d', $viewPract['fecha_cumpleanos']);
                                                                        if ($tmp) {
                                                                          $bd = $tmp->format('d/m');
                                                                        }
                                                                      }
                                                                      echo htmlspecialchars($bd);
                                                                      ?></span></div>
          <div><span class="info-label">Universidad</span><span><?php echo htmlspecialchars($viewPract['universidad'] ?? ''); ?></span></div>
          <div><span class="info-label">Carrera</span><span><?php echo htmlspecialchars($viewPract['carrera'] ?? ''); ?></span></div>
          <div><span class="info-label">Ubicacion</span><span><?php echo htmlspecialchars($ubicacionText); ?></span></div>
          <div><span class="info-label">Horas exigidas</span><span><?php echo htmlspecialchars((string)($viewPract['horas_exigidas'] ?? '')); ?></span></div>
          <div><span class="info-label">Horas cumplidas</span><span><?php echo htmlspecialchars((string)$totalHoras); ?></span></div>
          <div><span class="info-label">Horas pendientes</span><span><?php echo htmlspecialchars((string)$horasPendientes); ?></span></div>
        </div>
        <?php if ($canAdd): ?>
          <form method="post" class="stack compact">
            <input type="hidden" name="form_type" value="practicante_horas">
            <input type="hidden" name="practicante_id" value="<?php echo (int)$viewPract['id']; ?>">
            <div class="grid two">
              <div class="field">
                <label>Fecha</label>
                <input type="date" name="fecha">
              </div>
              <div class="field">
                <label>Horas realizadas</label>
                <input type="number" name="horas" min="1" required>
              </div>
              <div class="field full">
                <label>Detalle</label>
                <input name="detalle" placeholder="Actividad realizada">
              </div>
            </div>
            <button class="btn" type="submit">Registrar horas</button>
          </form>
        <?php endif; ?>
        <?php if ($horasList): ?>
          <table class="table-compact">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Horas</th>
                <th>Detalle</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($horasList as $row): ?>
                <tr>
                  <td><?php echo htmlspecialchars($row['fecha'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars((string)($row['horas'] ?? '')); ?></td>
                  <td><?php echo htmlspecialchars($row['detalle'] ?? ''); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p class="subtle">Sin registros de horas.</p>
        <?php endif; ?>
      </div>

      <div class="card">
        <h4 class="section-title">Documentos</h4>
        <?php if ($canAdd): ?>
          <form method="post" class="stack compact">
            <input type="hidden" name="form_type" value="practicante_documento">
            <input type="hidden" name="practicante_id" value="<?php echo (int)$viewPract['id']; ?>">
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
            <input type="hidden" name="form_type" value="practicante_observacion">
            <input type="hidden" name="practicante_id" value="<?php echo (int)$viewPract['id']; ?>">
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
    </div>
  <?php endif; ?>

  <div class="grid two">
    <div class="card">
      <h4 class="section-title">Departamentos</h4>
      <p class="subtle">Edita los catalogos base usados en Practicantes.</p>
      <?php if ($canAdmin): ?>
        <form method="post" class="stack compact">
          <input type="hidden" name="form_type" value="<?php echo $isEditingDepartment ? 'department_update' : 'department'; ?>">
          <input type="hidden" name="ui_module" value="<?php echo htmlspecialchars($activeModule); ?>">
          <?php if ($isEditingDepartment): ?>
            <input type="hidden" name="catalog_id" value="<?php echo (int)$catalogEdit['id']; ?>">
          <?php endif; ?>
          <div class="field">
            <label>Nombre</label>
            <input name="name" required value="<?php echo $isEditingDepartment ? htmlspecialchars((string)$catalogEdit['name']) : ''; ?>">
          </div>
          <div class="section-actions">
            <button class="btn" type="submit"><?php echo $isEditingDepartment ? 'Guardar cambios' : 'Agregar'; ?></button>
            <?php if ($isEditingDepartment): ?>
              <a class="btn secondary" href="<?php echo htmlspecialchars($catalogFormUrl); ?>">Cancelar</a>
            <?php endif; ?>
          </div>
        </form>
        <?php if ($isEditingDepartment): ?>
          <div class="notice">Editando departamento: <?php echo htmlspecialchars((string)$catalogEdit['name']); ?></div>
        <?php endif; ?>
        <hr>
      <?php else: ?>
        <div class="notice">Solo lectura: no tienes permisos para editar catalogos.</div>
      <?php endif; ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Practicantes</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($departmentsList): while ($row = $departmentsList->fetch_assoc()): ?>
              <tr>
                <td><?php echo (int)$row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo (int)($row['usage_count'] ?? 0); ?></td>
                <td>
                  <?php if ($canAdmin): ?>
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                      <a class="btn warning" style="margin-top:0;" href="<?php echo htmlspecialchars($catalogFormUrl . '&catalog_type=department&catalog_edit=' . (int)$row['id']); ?>">Editar</a>
                      <form method="post" style="display:inline-block;margin:0;" onsubmit="return confirm('Eliminar el departamento <?php echo htmlspecialchars($row['name']); ?>? Los practicantes asignados quedaran sin departamento.');">
                        <input type="hidden" name="form_type" value="department_delete">
                        <input type="hidden" name="catalog_id" value="<?php echo (int)$row['id']; ?>">
                        <input type="hidden" name="ui_module" value="<?php echo htmlspecialchars($activeModule); ?>">
                        <button class="btn danger" type="submit" style="margin-top:0;">Eliminar</button>
                      </form>
                    </div>
                  <?php else: ?>
                    <span class="subtle">Solo lectura</span>
                  <?php endif; ?>
                </td>
              </tr>
          <?php endwhile;
          endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h4 class="section-title">Cargos</h4>
      <p class="subtle">Edita los catalogos base usados en Practicantes.</p>
      <?php if ($canAdmin): ?>
        <form method="post" class="stack compact">
          <input type="hidden" name="form_type" value="<?php echo $isEditingPosition ? 'position_update' : 'position'; ?>">
          <input type="hidden" name="ui_module" value="<?php echo htmlspecialchars($activeModule); ?>">
          <?php if ($isEditingPosition): ?>
            <input type="hidden" name="catalog_id" value="<?php echo (int)$catalogEdit['id']; ?>">
          <?php endif; ?>
          <div class="field">
            <label>Nombre</label>
            <input name="name" required value="<?php echo $isEditingPosition ? htmlspecialchars((string)$catalogEdit['name']) : ''; ?>">
          </div>
          <div class="section-actions">
            <button class="btn" type="submit"><?php echo $isEditingPosition ? 'Guardar cambios' : 'Agregar'; ?></button>
            <?php if ($isEditingPosition): ?>
              <a class="btn secondary" href="<?php echo htmlspecialchars($catalogFormUrl); ?>">Cancelar</a>
            <?php endif; ?>
          </div>
        </form>
        <?php if ($isEditingPosition): ?>
          <div class="notice">Editando cargo: <?php echo htmlspecialchars((string)$catalogEdit['name']); ?></div>
        <?php endif; ?>
        <hr>
      <?php else: ?>
        <div class="notice">Solo lectura: no tienes permisos para editar catalogos.</div>
      <?php endif; ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Practicantes</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($positionsList): while ($row = $positionsList->fetch_assoc()): ?>
              <tr>
                <td><?php echo (int)$row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo (int)($row['usage_count'] ?? 0); ?></td>
                <td>
                  <?php if ($canAdmin): ?>
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                      <a class="btn warning" style="margin-top:0;" href="<?php echo htmlspecialchars($catalogFormUrl . '&catalog_type=position&catalog_edit=' . (int)$row['id']); ?>">Editar</a>
                      <form method="post" style="display:inline-block;margin:0;" onsubmit="return confirm('Eliminar el cargo <?php echo htmlspecialchars($row['name']); ?>? Los practicantes asignados quedaran sin cargo.');">
                        <input type="hidden" name="form_type" value="position_delete">
                        <input type="hidden" name="catalog_id" value="<?php echo (int)$row['id']; ?>">
                        <input type="hidden" name="ui_module" value="<?php echo htmlspecialchars($activeModule); ?>">
                        <button class="btn danger" type="submit" style="margin-top:0;">Eliminar</button>
                      </form>
                    </div>
                  <?php else: ?>
                    <span class="subtle">Solo lectura</span>
                  <?php endif; ?>
                </td>
              </tr>
          <?php endwhile;
          endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  (function() {
    var tabContainer = document.querySelector('[data-practicantes-tabs]');
    if (!tabContainer) {
      return;
    }

    var moduleButtons = tabContainer.querySelectorAll('[data-module-target]');
    var modulePanels = document.querySelectorAll('[data-module-panel]');
    var initialModule = '<?php echo htmlspecialchars($activeModule, ENT_QUOTES); ?>';
    var horasPorDia = <?php echo max(1, $horasPorDia); ?>;

    function setActiveModule(module, persist) {
      moduleButtons.forEach(function(button) {
        var active = button.getAttribute('data-module-target') === module;
        button.classList.toggle('is-active', active);
      });
      modulePanels.forEach(function(panel) {
        panel.hidden = panel.getAttribute('data-module-panel') !== module;
      });
      if (persist) {
        try {
          localStorage.setItem('khx_practicantes_active_module', module);
        } catch (err) {}
        try {
          var url = new URL(window.location.href);
          url.searchParams.set('ui_module', module);
          history.replaceState({}, '', url.toString());
        } catch (err) {}
      }
    }

    moduleButtons.forEach(function(button) {
      button.addEventListener('click', function() {
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
        fields.forEach(function(field) {
          if (!field.name) {
            return;
          }
          if (field.type === 'file' || field.type === 'password' || field.type === 'submit' || field.type === 'button') {
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
        fields.forEach(function(field) {
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
        } catch (err) {}
      }

      restore();
      form.addEventListener('input', save);
      form.addEventListener('change', save);
      form.addEventListener('submit', save);
    }

    var registerForm = document.getElementById('practicante-register-form');
    var searchForm = document.getElementById('practicante-search-form');
    bindPersistentForm(registerForm, 'khx_practicantes_registrar_form', true);
    bindPersistentForm(searchForm, 'khx_practicantes_buscar_form', true);

    if (registerForm) {
      var carreraSelect = registerForm.querySelector('[name="carrera"]');
      var carreraOtraField = registerForm.querySelector('[data-carrera-otra]');
      var carreraOtraInput = registerForm.querySelector('[name="carrera_otra"]');

      var universidadSelect = registerForm.querySelector('[name="universidad"]');
      var universidadOtraField = registerForm.querySelector('[data-universidad-otra]');
      var universidadOtraInput = registerForm.querySelector('[name="universidad_otra"]');

      var regionSelect = registerForm.querySelector('#practicante-region');
      var comunaSelect = registerForm.querySelector('#practicante-comuna');
      var comunaOtraField = registerForm.querySelector('[data-comuna-otra]');
      var comunaOtraInput = registerForm.querySelector('[name="comuna_otra"]');
      var extranjeroCheckbox = registerForm.querySelector('#practicante-extranjero');
      var ubicacionExtranjeroField = registerForm.querySelector('[data-ubicacion-extranjero]');
      var ubicacionExtranjeroInput = registerForm.querySelector('[name="ubicacion_extranjero"]');

      var fechaInicioInput = registerForm.querySelector('#practicante-fecha-inicio');
      var horasInput = registerForm.querySelector('#practicante-horas');
      var fechaFinInput = registerForm.querySelector('#practicante-fecha-fin');
      var fechaFinHint = registerForm.querySelector('#practicante-fecha-fin-hint');

      var regionComunas = <?php echo json_encode($regionComunas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

      function syncCarreraOtro() {
        var show = carreraSelect && carreraSelect.value === 'otro';
        if (carreraOtraField) {
          carreraOtraField.hidden = !show;
        }
        if (carreraOtraInput) {
          carreraOtraInput.disabled = !show;
          carreraOtraInput.required = show;
        }
      }

      function syncUniversidadOtra() {
        var show = universidadSelect && universidadSelect.value === 'otro';
        if (universidadOtraField) {
          universidadOtraField.hidden = !show;
        }
        if (universidadOtraInput) {
          universidadOtraInput.disabled = !show;
          universidadOtraInput.required = show;
        }
      }

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
        comunas.forEach(function(name) {
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
        if (!comunaSelect || !comunaOtraField || !comunaOtraInput || !extranjeroCheckbox) {
          return;
        }
        var show = !extranjeroCheckbox.checked && comunaSelect.value === 'otra';
        comunaOtraField.hidden = !show;
        comunaOtraInput.disabled = !show;
        comunaOtraInput.required = show;
      }

      function syncExtranjero() {
        var isExtranjero = extranjeroCheckbox && extranjeroCheckbox.checked;
        if (regionSelect) {
          regionSelect.disabled = isExtranjero;
          regionSelect.required = !isExtranjero;
        }
        if (comunaSelect) {
          comunaSelect.disabled = isExtranjero;
          comunaSelect.required = !isExtranjero;
        }
        if (ubicacionExtranjeroField) {
          ubicacionExtranjeroField.hidden = !isExtranjero;
        }
        if (ubicacionExtranjeroInput) {
          ubicacionExtranjeroInput.disabled = !isExtranjero;
          ubicacionExtranjeroInput.required = isExtranjero;
        }
        if (isExtranjero && comunaOtraField) {
          comunaOtraField.hidden = true;
        }
        syncComunaOtra();
      }

      function parseDate(value) {
        var parts = value.split('-');
        if (parts.length !== 3) {
          return null;
        }
        var year = parseInt(parts[0], 10);
        var month = parseInt(parts[1], 10) - 1;
        var day = parseInt(parts[2], 10);
        if (isNaN(year) || isNaN(month) || isNaN(day)) {
          return null;
        }
        return new Date(year, month, day);
      }

      function toIsoDate(date) {
        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
      }

      var lastComputedFechaFin = '';

      function calculateFechaFin() {
        if (!fechaInicioInput || !horasInput || !fechaFinInput) {
          return;
        }

        var fechaInicio = fechaInicioInput.value;
        var horas = parseInt(horasInput.value, 10);
        if (!fechaInicio || !horas || horas <= 0) {
          fechaFinInput.value = '';
          if (fechaFinHint) {
            fechaFinHint.textContent = '';
          }
          return;
        }

        var inicioDate = parseDate(fechaInicio);
        if (!inicioDate || isNaN(inicioDate.getTime())) {
          return;
        }

        while (inicioDate.getDay() === 0 || inicioDate.getDay() === 6) {
          inicioDate.setDate(inicioDate.getDate() + 1);
        }

        var diasHabiles = Math.max(1, Math.ceil(horas / horasPorDia));
        var terminoDate = new Date(inicioDate.getTime());
        var acumulados = 1;
        while (acumulados < diasHabiles) {
          terminoDate.setDate(terminoDate.getDate() + 1);
          if (terminoDate.getDay() !== 0 && terminoDate.getDay() !== 6) {
            acumulados++;
          }
        }

        var computed = toIsoDate(terminoDate);
        if (fechaFinInput.value === '' || fechaFinInput.value === lastComputedFechaFin) {
          fechaFinInput.value = computed;
        }
        lastComputedFechaFin = computed;
        if (fechaFinHint) {
          var semanas = Math.floor(diasHabiles / 5);
          var diasRestantes = diasHabiles % 5;
          fechaFinHint.textContent = diasHabiles + ' dias habiles aprox. (' + semanas + ' semanas y ' + diasRestantes + ' dias).';
        }
      }

      if (carreraSelect) {
        carreraSelect.addEventListener('change', syncCarreraOtro);
      }
      if (universidadSelect) {
        universidadSelect.addEventListener('change', syncUniversidadOtra);
      }
      if (regionSelect) {
        regionSelect.addEventListener('change', function() {
          fillComunas('');
          syncComunaOtra();
        });
      }
      if (comunaSelect) {
        comunaSelect.addEventListener('change', syncComunaOtra);
      }
      if (extranjeroCheckbox) {
        extranjeroCheckbox.addEventListener('change', syncExtranjero);
      }

      if (fechaInicioInput) {
        fechaInicioInput.addEventListener('change', calculateFechaFin);
        fechaInicioInput.addEventListener('input', calculateFechaFin);
      }
      if (horasInput) {
        horasInput.addEventListener('change', calculateFechaFin);
        horasInput.addEventListener('input', calculateFechaFin);
      }

      fillComunas(comunaSelect ? (comunaSelect.getAttribute('data-current') || '') : '');
      syncCarreraOtro();
      syncUniversidadOtra();
      syncExtranjero();
      calculateFechaFin();
    }

    var editForm = document.getElementById('practicante-edit-form');
    if (editForm) {
      var editCarreraSelect = editForm.querySelector('[name="carrera"]');
      var editCarreraOtraField = editForm.querySelector('[data-edit-carrera-otra]');
      var editCarreraOtraInput = editForm.querySelector('[name="carrera_otra"]');

      var editUniversidadSelect = editForm.querySelector('[name="universidad"]');
      var editUniversidadOtraField = editForm.querySelector('[data-edit-universidad-otra]');
      var editUniversidadOtraInput = editForm.querySelector('[name="universidad_otra"]');

      var editRegionSelect = editForm.querySelector('#practicante-edit-region');
      var editComunaSelect = editForm.querySelector('#practicante-edit-comuna');
      var editComunaOtraField = editForm.querySelector('[data-edit-comuna-otra]');
      var editComunaOtraInput = editForm.querySelector('[name="comuna_otra"]');
      var editExtranjeroCheckbox = editForm.querySelector('#practicante-edit-extranjero');
      var editUbicacionField = editForm.querySelector('[data-edit-ubicacion-extranjero]');
      var editUbicacionInput = editForm.querySelector('[name="ubicacion_extranjero"]');

      var editFechaInicioInput = editForm.querySelector('#practicante-edit-fecha-inicio');
      var editHorasInput = editForm.querySelector('#practicante-edit-horas');
      var editFechaFinInput = editForm.querySelector('#practicante-edit-fecha-fin');

      var editRegionComunas = <?php echo json_encode($regionComunas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

      function editAddOption(select, value, label) {
        var option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        select.appendChild(option);
      }

      function editHasOption(select, value) {
        for (var i = 0; i < select.options.length; i++) {
          if (select.options[i].value === value) {
            return true;
          }
        }
        return false;
      }

      function syncEditCarreraOtro() {
        var show = editCarreraSelect && editCarreraSelect.value === 'otro';
        if (editCarreraOtraField) {
          editCarreraOtraField.hidden = !show;
        }
        if (editCarreraOtraInput) {
          editCarreraOtraInput.disabled = !show;
          editCarreraOtraInput.required = show;
        }
      }

      function syncEditUniversidadOtra() {
        var show = editUniversidadSelect && editUniversidadSelect.value === 'otro';
        if (editUniversidadOtraField) {
          editUniversidadOtraField.hidden = !show;
        }
        if (editUniversidadOtraInput) {
          editUniversidadOtraInput.disabled = !show;
          editUniversidadOtraInput.required = show;
        }
      }

      function fillEditComunas(selectedValue) {
        if (!editComunaSelect) {
          return;
        }
        var region = editRegionSelect ? editRegionSelect.value : '';
        var comunas = (region && editRegionComunas[region]) ? editRegionComunas[region] : [];
        editComunaSelect.innerHTML = '';
        editAddOption(editComunaSelect, '', 'Selecciona comuna');
        comunas.forEach(function(name) {
          editAddOption(editComunaSelect, name, name);
        });
        editAddOption(editComunaSelect, 'otra', 'Otra comuna');

        if (selectedValue && editHasOption(editComunaSelect, selectedValue)) {
          editComunaSelect.value = selectedValue;
        } else if (selectedValue) {
          editComunaSelect.value = 'otra';
          if (editComunaOtraInput && editComunaOtraInput.value === '') {
            editComunaOtraInput.value = selectedValue;
          }
        }
      }

      function syncEditComunaOtra() {
        if (!editComunaSelect || !editComunaOtraField || !editComunaOtraInput || !editExtranjeroCheckbox) {
          return;
        }
        var show = !editExtranjeroCheckbox.checked && editComunaSelect.value === 'otra';
        editComunaOtraField.hidden = !show;
        editComunaOtraInput.disabled = !show;
        editComunaOtraInput.required = show;
      }

      function syncEditExtranjero() {
        var isExtranjero = editExtranjeroCheckbox && editExtranjeroCheckbox.checked;
        if (editRegionSelect) {
          editRegionSelect.disabled = isExtranjero;
          editRegionSelect.required = !isExtranjero;
        }
        if (editComunaSelect) {
          editComunaSelect.disabled = isExtranjero;
          editComunaSelect.required = !isExtranjero;
        }
        if (editUbicacionField) {
          editUbicacionField.hidden = !isExtranjero;
        }
        if (editUbicacionInput) {
          editUbicacionInput.disabled = !isExtranjero;
          editUbicacionInput.required = isExtranjero;
        }
        if (isExtranjero && editComunaOtraField) {
          editComunaOtraField.hidden = true;
        }
        syncEditComunaOtra();
      }

      function calculateEditFechaFin() {
        if (!editFechaInicioInput || !editHorasInput || !editFechaFinInput) {
          return;
        }
        var fechaInicio = editFechaInicioInput.value;
        var horas = parseInt(editHorasInput.value, 10);
        if (!fechaInicio || !horas || horas <= 0) {
          editFechaFinInput.value = '';
          return;
        }
        var inicioDate = parseDate(fechaInicio);
        if (!inicioDate || isNaN(inicioDate.getTime())) {
          return;
        }
        while (inicioDate.getDay() === 0 || inicioDate.getDay() === 6) {
          inicioDate.setDate(inicioDate.getDate() + 1);
        }
        var diasHabiles = Math.max(1, Math.ceil(horas / horasPorDia));
        var terminoDate = new Date(inicioDate.getTime());
        var acumulados = 1;
        while (acumulados < diasHabiles) {
          terminoDate.setDate(terminoDate.getDate() + 1);
          if (terminoDate.getDay() !== 0 && terminoDate.getDay() !== 6) {
            acumulados++;
          }
        }
        editFechaFinInput.value = toIsoDate(terminoDate);
      }

      if (editCarreraSelect) {
        editCarreraSelect.addEventListener('change', syncEditCarreraOtro);
      }
      if (editUniversidadSelect) {
        editUniversidadSelect.addEventListener('change', syncEditUniversidadOtra);
      }
      if (editRegionSelect) {
        editRegionSelect.addEventListener('change', function() {
          fillEditComunas('');
          syncEditComunaOtra();
        });
      }
      if (editComunaSelect) {
        editComunaSelect.addEventListener('change', syncEditComunaOtra);
      }
      if (editExtranjeroCheckbox) {
        editExtranjeroCheckbox.addEventListener('change', syncEditExtranjero);
      }
      if (editFechaInicioInput) {
        editFechaInicioInput.addEventListener('change', calculateEditFechaFin);
        editFechaInicioInput.addEventListener('input', calculateEditFechaFin);
      }
      if (editHorasInput) {
        editHorasInput.addEventListener('change', calculateEditFechaFin);
        editHorasInput.addEventListener('input', calculateEditFechaFin);
      }

      fillEditComunas(editComunaSelect ? (editComunaSelect.getAttribute('data-current') || '') : '');
      syncEditCarreraOtro();
      syncEditUniversidadOtra();
      syncEditExtranjero();
      calculateEditFechaFin();
    }
  })();
</script>