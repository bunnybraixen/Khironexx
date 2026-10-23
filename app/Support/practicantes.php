<?php

if (!function_exists('practicantes_calcular_fecha_fin')) {
    function practicantes_calcular_fecha_fin($fechaInicio, $horasExigidas, $horasPorDia = 8)
    {
        $fechaInicio = trim((string)$fechaInicio);
        $horasExigidas = (int)$horasExigidas;
        $horasPorDia = max(1, (int)$horasPorDia);

        if ($fechaInicio === '' || $horasExigidas <= 0) {
            return '';
        }

        $inicio = DateTime::createFromFormat('Y-m-d', $fechaInicio);
        if (!$inicio || $inicio->format('Y-m-d') !== $fechaInicio) {
            return '';
        }

        $diaSemana = (int)$inicio->format('N');
        if ($diaSemana === 6) {
            $inicio->modify('+2 days');
        } elseif ($diaSemana === 7) {
            $inicio->modify('+1 day');
        }

        $diasHabiles = (int)ceil($horasExigidas / $horasPorDia);
        $diasHabiles = max(1, $diasHabiles);

        $fechaTermino = clone $inicio;
        for ($i = 1; $i < $diasHabiles; $i++) {
            do {
                $fechaTermino->modify('+1 day');
                $diaSemana = (int)$fechaTermino->format('N');
            } while ($diaSemana >= 6);
        }

        return $fechaTermino->format('Y-m-d');
    }
}

if (!function_exists('practicantes_bool_value')) {
    function practicantes_bool_value($value)
    {
        $val = strtolower(trim((string)$value));
        return in_array($val, ['1', 'si', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('practicantes_is_valid_date')) {
    function practicantes_is_valid_date($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return false;
        }
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    }
}

if (!function_exists('practicantes_fetch_select_options')) {
    function practicantes_fetch_select_options(mysqli $db, $column, array $defaults = [])
    {
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$column);
        if ($column === '') {
            return $defaults;
        }

        $options = [];
        $pushOption = function ($value) use (&$options) {
            $value = trim((string)$value);
            if ($value !== '' && !in_array($value, $options, true)) {
                $options[] = $value;
            }
        };

        foreach ($defaults as $value) {
            $pushOption($value);
        }

        foreach (['postulantes', 'practicantes'] as $table) {
            $tableColumns = db_table_columns($db, $table);
            if (!in_array($column, $tableColumns, true)) {
                continue;
            }
            $result = $db->query("SELECT DISTINCT " . $column . " AS value
                FROM " . $table . "
                WHERE " . $column . " IS NOT NULL AND TRIM(" . $column . ") <> ''
                ORDER BY " . $column . " ASC
                LIMIT 300");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $pushOption($row['value'] ?? '');
                }
            }
        }

        return $options;
    }
}

if (!function_exists('practicantes_fk_exists')) {
    function practicantes_fk_exists(mysqli $db, $table, $id)
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        $id = (int)$id;
        if ($table === '' || $id <= 0) {
            return false;
        }

        static $cache = [];
        $cacheKey = $table . ':' . $id;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        if (!function_exists('db_table_exists') || !db_table_exists($db, $table)) {
            $cache[$cacheKey] = false;
            return false;
        }

        try {
            $stmt = $db->prepare("SELECT id FROM " . $table . " WHERE id = ? LIMIT 1");
            if (!$stmt) {
                $cache[$cacheKey] = false;
                return false;
            }
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $cache[$cacheKey] = (bool)$stmt->get_result()->fetch_assoc();
            return $cache[$cacheKey];
        } catch (Throwable $e) {
            $cache[$cacheKey] = false;
            return false;
        }
    }
}

if (!function_exists('practicantes_insert_row')) {
    function practicantes_insert_row(mysqli $db, array $rowData, array $tableColumns)
    {
        $columnTypes = [
            'postulante_id' => 'i',
            'full_name' => 's',
            'email' => 's',
            'rut' => 's',
            'telefono' => 's',
            'photo' => 's',                           // ruta de imagen almacenada
            'department_id' => 'i',
            'position_id' => 'i',
            'fecha_inicio' => 's',
            'fecha_fin' => 's',
            'fecha_cumpleanos' => 's',
            'status' => 's',
            'correo_institucional' => 's',
            'horas_exigidas' => 'i',
            'carrera' => 's',
            'universidad' => 's',
            'region' => 's',
            'comuna' => 's',
            'extranjero' => 'i',
            'ubicacion_extranjero' => 's',
        ];

        $nullableStringColumns = [
            'email',
            'rut',
            'telefono',
            'photo',                          // se permite nulo hasta que se suba en el registro
            'fecha_inicio',
            'fecha_fin',
            'fecha_cumpleanos',
            'correo_institucional',
            'carrera',
            'universidad',
            'region',
            'comuna',
            'ubicacion_extranjero',
        ];

        $columns = [];
        $placeholders = [];
        $types = '';
        $values = [];
        $foreignTableByColumn = [
            'postulante_id' => 'postulantes',
            'department_id' => 'departamentos',
            'position_id' => 'cargos',
        ];

        foreach ($columnTypes as $column => $type) {
            if (!array_key_exists($column, $rowData)) {
                continue;
            }
            if (!in_array($column, $tableColumns, true)) {
                continue;
            }

            $value = $rowData[$column];
            if ($type === 'i') {
                $value = (int)$value;
                if (in_array($column, ['postulante_id', 'department_id', 'position_id'], true) && $value <= 0) {
                    continue;
                }
                if (isset($foreignTableByColumn[$column]) && $value > 0 && !practicantes_fk_exists($db, $foreignTableByColumn[$column], $value)) {
                    continue;
                }
            } else {
                $value = (string)$value;
                if (in_array($column, $nullableStringColumns, true) && trim($value) === '') {
                    $value = null;
                }
            }

            $columns[] = $column;
            $placeholders[] = '?';
            $types .= $type;
            $values[] = $value;
        }

        if (!$columns) {
            return false;
        }

        $sql = "INSERT INTO practicantes (" . implode(', ', $columns) . ", created_at)
            VALUES (" . implode(', ', $placeholders) . ", NOW())";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return false;
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$values);
        }
        try {
            return $stmt->execute();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('practicantes_build_data')) {
    function practicantes_build_data(mysqli $db, array $permissions)
    {
        $departments = fetchOptions($db, 'departamentos');
        $positions = fetchOptions($db, 'cargos');
        $messages = [];
        $errors = [];
        $registerDraft = [];
        $editDraft = [];
        $formType = $_POST['form_type'] ?? '';
        $viewId = (int)($_GET['view'] ?? 0);
        $editId = (int)($_GET['edit'] ?? 0);
        $canAdd = $permissions['canAdd'] ?? false;
        $canAdmin = $permissions['canAdmin'] ?? false;
        $userId = $permissions['userId'] ?? null;
        $horasPorDia = 8;
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $practicantesColumns = db_table_columns($db, 'practicantes');
        if (!$practicantesColumns) {
            $errors[] = "La tabla practicantes no existe o no esta disponible. Ejecuta el instalador para crear el esquema.";
            return [
                'departments' => $departments,
                'positions' => $positions,
                'messages' => $messages,
                'errors' => $errors,
                'formType' => $formType,
                'viewId' => $viewId,
                'editId' => $editId,
                'filterName' => '',
                'filterUniversidad' => '',
                'filterCarrera' => '',
                'filterArea' => '',
                'filterStatus' => '',
                'sort' => 'fecha',
                'rows' => [],
                'departmentsList' => [],
                'positionsList' => [],
                'viewPract' => null,
                'horasList' => [],
                'docs' => [],
                'obs' => [],
                'totalHoras' => 0,
                'horasPendientes' => 0,
                'canAdd' => $canAdd,
                'canAdmin' => $canAdmin,
                'carreras' => [],
                'universidades' => [],
                'registerDraft' => $registerDraft,
                'editDraft' => $editDraft,
                'activeModule' => 'registrar',
                'horasPorDia' => $horasPorDia,
            ];
        }

        $defaultCarreras = [
            'Administracion',
            'Comercial',
            'Contabilidad',
            'Derecho',
            'Diseno',
            'Informatica',
            'Ingenieria Civil',
            'Ingenieria Comercial',
            'Psicologia',
            'Recursos Humanos',
        ];
        $defaultUniversidades = [
            'Duoc UC',
            'INACAP',
            'Pontificia Universidad Catolica de Chile',
            'Universidad Andres Bello',
            'Universidad de Chile',
            'Universidad de Santiago de Chile',
            'Universidad Diego Portales',
            'Universidad Tecnica Federico Santa Maria',
        ];
        $carreras = practicantes_fetch_select_options($db, 'carrera', $defaultCarreras);
        $universidades = practicantes_fetch_select_options($db, 'universidad', $defaultUniversidades);

        $requestedModule = trim((string)($_REQUEST['ui_module'] ?? ''));
        $activeModule = in_array($requestedModule, ['registrar', 'buscar', 'editar'], true) ? $requestedModule : 'registrar';
        foreach (['q', 'universidad', 'carrera', 'area', 'status', 'sort'] as $key) {
            if (isset($_GET[$key]) && trim((string)$_GET[$key]) !== '') {
                $activeModule = 'buscar';
                break;
            }
        }
        if ($viewId > 0) {
            $activeModule = 'buscar';
        }
        if ($editId > 0) {
            $activeModule = 'editar';
        }

        if ($requestMethod === 'POST') {
            if (in_array($formType, ['practicantes_csv', 'practicante'], true)) {
                $activeModule = 'registrar';
            } elseif ($formType === 'practicante_update') {
                $activeModule = 'editar';
            } elseif ($formType === 'practicante_delete') {
                $activeModule = 'buscar';
            }

            if ($formType === 'practicantes_csv') {
                if (!$canAdd) {
                    $errors[] = "No tienes permisos para cargar practicantes.";
                } elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = "No se pudo leer el archivo CSV.";
                } else {
                    $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
                    if (!$handle) {
                        $errors[] = "No se pudo abrir el archivo.";
                    } else {
                        $firstLine = fgets($handle);
                        if ($firstLine === false) {
                            $errors[] = "El CSV esta vacio.";
                        } else {
                            $delims = [',', ';', "\t"];
                            $counts = [];
                            foreach ($delims as $delim) {
                                $counts[$delim] = substr_count($firstLine, $delim);
                            }
                            $delimiter = ';';
                            if (max($counts) > 0) {
                                $delimiter = array_keys($counts, max($counts))[0];
                            }

                            rewind($handle);
                            $header = fgetcsv($handle, 0, $delimiter);
                            if (!$header) {
                                $errors[] = "No se detectaron columnas en el CSV.";
                            } else {
                                $aliases = [
                                    'nombre' => 'full_name',
                                    'nombre_completo' => 'full_name',
                                    'full_name' => 'full_name',
                                    'correo' => 'email',
                                    'email' => 'email',
                                    'telefono' => 'telefono',
                                    'numero_contacto' => 'telefono',
                                    'rut' => 'rut',
                                    'departamento' => 'department',
                                    'area' => 'department',
                                    'department' => 'department',
                                    'cargo' => 'cargo',
                                    'position' => 'cargo',
                                    'correo_institucional' => 'correo_institucional',
                                    'email_institucional' => 'correo_institucional',
                                    'fecha_inicio' => 'fecha_inicio',
                                    'inicio' => 'fecha_inicio',
                                    'fecha_fin' => 'fecha_fin',
                                    'fin' => 'fecha_fin',
                                    'fecha_cumpleanos' => 'fecha_cumpleanos',
                                    'cumpleanos' => 'fecha_cumpleanos',
                                    'birthday' => 'fecha_cumpleanos',
                                    'horas_exigidas' => 'horas_exigidas',
                                    'horas' => 'horas_exigidas',
                                    'carrera' => 'carrera',
                                    'universidad' => 'universidad',
                                    'region' => 'region',
                                    'comuna' => 'comuna',
                                    'extranjero' => 'extranjero',
                                    'ubicacion_extranjero' => 'ubicacion_extranjero',
                                    'pais' => 'ubicacion_extranjero',
                                    'estado' => 'status',
                                    'status' => 'status',
                                ];
                                $indexes = [];
                                foreach ($header as $idx => $col) {
                                    $clean = $col;
                                    if (function_exists('iconv')) {
                                        $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $clean);
                                    }
                                    $clean = strtolower(trim((string)$clean));
                                    $clean = preg_replace('/[^a-z0-9]+/', '_', $clean);
                                    $clean = trim((string)$clean, '_');
                                    if (isset($aliases[$clean])) {
                                        $indexes[$aliases[$clean]] = $idx;
                                    }
                                }

                                if (!isset($indexes['full_name'])) {
                                    $errors[] = "El CSV debe incluir la columna nombre_completo (o full_name).";
                                } else {
                                    $getCell = function ($row, $indexes, $key) {
                                        if (!isset($indexes[$key])) {
                                            return '';
                                        }
                                        $idx = $indexes[$key];
                                        return isset($row[$idx]) ? $row[$idx] : '';
                                    };

                                    $imported = 0;
                                    $skipped = 0;
                                    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                                        if (count($row) === 1 && trim((string)$row[0]) === '') {
                                            continue;
                                        }

                                        $fullName = trim((string)$getCell($row, $indexes, 'full_name'));
                                        if ($fullName === '') {
                                            $skipped++;
                                            continue;
                                        }

                                        $email = trim((string)$getCell($row, $indexes, 'email'));
                                        $telefono = trim((string)$getCell($row, $indexes, 'telefono'));
                                        $rut = trim((string)$getCell($row, $indexes, 'rut'));
                                        $departmentName = trim((string)$getCell($row, $indexes, 'department'));
                                        $positionName = trim((string)$getCell($row, $indexes, 'cargo'));
                                        $correoInstitucional = trim((string)$getCell($row, $indexes, 'correo_institucional'));
                                        $fechaInicio = trim((string)$getCell($row, $indexes, 'fecha_inicio'));
                                        $fechaFin = trim((string)$getCell($row, $indexes, 'fecha_fin'));
                                        $fechaCumpleanos = trim((string)$getCell($row, $indexes, 'fecha_cumpleanos'));
                                        $horasExigidas = (int)$getCell($row, $indexes, 'horas_exigidas');
                                        $carrera = trim((string)$getCell($row, $indexes, 'carrera'));
                                        $universidad = trim((string)$getCell($row, $indexes, 'universidad'));
                                        $region = trim((string)$getCell($row, $indexes, 'region'));
                                        $comuna = trim((string)$getCell($row, $indexes, 'comuna'));
                                        $extranjero = practicantes_bool_value($getCell($row, $indexes, 'extranjero')) ? 1 : 0;
                                        $ubicacionExtranjero = trim((string)$getCell($row, $indexes, 'ubicacion_extranjero'));
                                        $status = strtolower(trim((string)$getCell($row, $indexes, 'status')));

                                        if ($status === 'activo') {
                                            $status = 'active';
                                        } elseif ($status === 'inactivo') {
                                            $status = 'inactive';
                                        }
                                        if (!in_array($status, ['active', 'inactive'], true)) {
                                            $status = 'active';
                                        }

                                        if (strpos($fechaInicio, '/') !== false) {
                                            $parts = explode('/', $fechaInicio);
                                            if (count($parts) === 3) {
                                                $fechaInicio = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
                                            }
                                        }
                                        if (strpos($fechaFin, '/') !== false) {
                                            $parts = explode('/', $fechaFin);
                                            if (count($parts) === 3) {
                                                $fechaFin = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
                                            }
                                        }
                                        if (strpos($fechaCumpleanos, '/') !== false) {
                                            $parts = explode('/', $fechaCumpleanos);
                                            if (count($parts) === 3) {
                                                $fechaCumpleanos = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
                                            }
                                        }
                                        // if only month/day given convert to year 2000
                                        if ($fechaCumpleanos !== '') {
                                            if (preg_match('/^\d{1,2}[\/\-]\d{1,2}$/', $fechaCumpleanos)) {
                                                $parts = preg_split('/[\/\-]/', $fechaCumpleanos);
                                                if (count($parts) === 2) {
                                                    $fechaCumpleanos = sprintf('2000-%02d-%02d', (int)$parts[0], (int)$parts[1]);
                                                }
                                            }
                                        }
                                        if ($fechaCumpleanos !== '' && !practicantes_is_valid_date($fechaCumpleanos)) {
                                            $skipped++;
                                            continue;
                                        }
                                        if ($fechaFin === '' && $fechaInicio !== '' && $horasExigidas > 0) {
                                            $fechaFin = practicantes_calcular_fecha_fin($fechaInicio, $horasExigidas, $horasPorDia);
                                        }

                                        if ($extranjero) {
                                            $region = 'Extranjero';
                                            if ($ubicacionExtranjero !== '') {
                                                $comuna = $ubicacionExtranjero;
                                            }
                                        } else {
                                            $ubicacionExtranjero = '';
                                        }

                                        if ($email !== '') {
                                            $check = $db->prepare("SELECT id FROM practicantes WHERE email = ? LIMIT 1");
                                            $check->bind_param('s', $email);
                                            $check->execute();
                                            if ($check->get_result()->fetch_assoc()) {
                                                $skipped++;
                                                continue;
                                            }
                                        }

                                        $departmentId = 0;
                                        if ($departmentName !== '') {
                                            $deptStmt = $db->prepare("SELECT id FROM departamentos WHERE LOWER(name) = LOWER(?) LIMIT 1");
                                            $deptStmt->bind_param('s', $departmentName);
                                            $deptStmt->execute();
                                            $deptRow = $deptStmt->get_result()->fetch_assoc();
                                            if ($deptRow) {
                                                $departmentId = (int)$deptRow['id'];
                                            } else {
                                                $insertDept = $db->prepare("INSERT INTO departamentos (name) VALUES (?)");
                                                $insertDept->bind_param('s', $departmentName);
                                                if ($insertDept->execute()) {
                                                    $departmentId = (int)$insertDept->insert_id;
                                                }
                                            }
                                        }

                                        $positionId = 0;
                                        if ($positionName !== '') {
                                            $posStmt = $db->prepare("SELECT id FROM cargos WHERE LOWER(name) = LOWER(?) LIMIT 1");
                                            $posStmt->bind_param('s', $positionName);
                                            $posStmt->execute();
                                            $posRow = $posStmt->get_result()->fetch_assoc();
                                            if ($posRow) {
                                                $positionId = (int)$posRow['id'];
                                            } else {
                                                $insertPos = $db->prepare("INSERT INTO cargos (name) VALUES (?)");
                                                $insertPos->bind_param('s', $positionName);
                                                if ($insertPos->execute()) {
                                                    $positionId = (int)$insertPos->insert_id;
                                                }
                                            }
                                        }

                                        $inserted = practicantes_insert_row($db, [
                                            'full_name' => $fullName,
                                            'email' => $email,
                                            'rut' => $rut,
                                            'telefono' => $telefono,
                                            'department_id' => $departmentId,
                                            'position_id' => $positionId,
                                            'fecha_inicio' => $fechaInicio,
                                            'fecha_fin' => $fechaFin,
                                            'fecha_cumpleanos' => $fechaCumpleanos,
                                            'status' => $status,
                                            'correo_institucional' => $correoInstitucional,
                                            'horas_exigidas' => $horasExigidas,
                                            'carrera' => $carrera,
                                            'universidad' => $universidad,
                                            'region' => $region,
                                            'comuna' => $comuna,
                                            'extranjero' => $extranjero,
                                            'ubicacion_extranjero' => $ubicacionExtranjero,
                                        ], $practicantesColumns);

                                        if ($inserted) {
                                            $imported++;
                                        } else {
                                            $skipped++;
                                        }
                                    }

                                    if ($imported > 0) {
                                        $messages[] = "Importados $imported practicantes desde CSV.";
                                    }
                                    if ($skipped > 0) {
                                        $messages[] = "Omitidos $skipped registros por duplicados o datos incompletos.";
                                    }
                                }
                            }
                        }
                        fclose($handle);
                    }
                }
            } elseif ($formType === 'practicante') {
                if (!$canAdd) {
                    $errors[] = "No tienes permisos para agregar practicantes.";
                } else {
                    // si el usuario selecciona mes y dia para cumpleaños construimos una fecha con año ficticio
                    $mes = trim((string)($_POST['cumple_mes'] ?? ''));
                    $dia = trim((string)($_POST['cumple_dia'] ?? ''));
                    if ($mes !== '' && $dia !== '') {
                        $mes = preg_replace('/[^0-9]/', '', $mes);
                        $dia = preg_replace('/[^0-9]/', '', $dia);
                        if ($mes !== '' && $dia !== '') {
                            $_POST['fecha_cumpleanos'] = sprintf('2000-%02d-%02d', (int)$mes, (int)$dia);
                        }
                    }

                    $registerDraft = [
                        'full_name' => trim((string)($_POST['full_name'] ?? '')),
                        'email' => trim((string)($_POST['email'] ?? '')),
                        'rut' => trim((string)($_POST['rut'] ?? '')),
                        'telefono' => trim((string)($_POST['telefono'] ?? '')),
                        'department_id' => (int)($_POST['department_id'] ?? 0),
                        'position_id' => (int)($_POST['position_id'] ?? 0),
                        'correo_institucional' => trim((string)($_POST['correo_institucional'] ?? '')),
                        'horas_exigidas' => (int)($_POST['horas_exigidas'] ?? 0),
                        'fecha_inicio' => trim((string)($_POST['fecha_inicio'] ?? '')),
                        'fecha_fin' => trim((string)($_POST['fecha_fin'] ?? '')),
                        'fecha_cumpleanos' => trim((string)($_POST['fecha_cumpleanos'] ?? '')),
                        'status' => trim((string)($_POST['status'] ?? 'active')),
                        'carrera' => trim((string)($_POST['carrera'] ?? '')),
                        'carrera_otra' => trim((string)($_POST['carrera_otra'] ?? '')),
                        'universidad' => trim((string)($_POST['universidad'] ?? '')),
                        'universidad_otra' => trim((string)($_POST['universidad_otra'] ?? '')),
                        'region' => trim((string)($_POST['region'] ?? '')),
                        'comuna' => trim((string)($_POST['comuna'] ?? '')),
                        'comuna_otra' => trim((string)($_POST['comuna_otra'] ?? '')),
                        'extranjero' => isset($_POST['extranjero']) ? '1' : '0',
                        'ubicacion_extranjero' => trim((string)($_POST['ubicacion_extranjero'] ?? '')),
                    ];

                    $fullName = $registerDraft['full_name'];
                    $email = $registerDraft['email'];
                    $rut = $registerDraft['rut'];
                    $telefono = $registerDraft['telefono'];
                    $departmentId = (int)$registerDraft['department_id'];
                    $positionId = (int)$registerDraft['position_id'];
                    $fechaInicio = $registerDraft['fecha_inicio'];
                    $fechaCumpleanos = $registerDraft['fecha_cumpleanos'];
                    $status = $registerDraft['status'];
                    $correoInstitucional = $registerDraft['correo_institucional'];
                    $horasExigidas = (int)$registerDraft['horas_exigidas'];
                    $carrera = $registerDraft['carrera'] === 'otro' ? $registerDraft['carrera_otra'] : $registerDraft['carrera'];
                    $universidad = $registerDraft['universidad'] === 'otro' ? $registerDraft['universidad_otra'] : $registerDraft['universidad'];

                    $extranjero = $registerDraft['extranjero'] === '1' ? 1 : 0;
                    $region = $registerDraft['region'];
                    $comuna = $registerDraft['comuna'] === 'otra' ? $registerDraft['comuna_otra'] : $registerDraft['comuna'];
                    $ubicacionExtranjero = $registerDraft['ubicacion_extranjero'];

                    // foto obligatoria
                    $photoPath = store_uploaded_file($_FILES['photo'] ?? [], 'practicantes');
                    if (!$photoPath) {
                        $errors[] = "Debes subir una fotografía valida para el practicante.";
                    }

                    if (!in_array($status, ['active', 'inactive'], true)) {
                        $status = 'active';
                    }

                    if ($extranjero) {
                        if ($ubicacionExtranjero === '') {
                            $errors[] = "Debes ingresar el pais o ubicacion para extranjero.";
                        }
                        $region = 'Extranjero';
                        $comuna = $ubicacionExtranjero;
                    } else {
                        $ubicacionExtranjero = '';
                        if ($region === '') {
                            $errors[] = "Debes seleccionar una region.";
                        }
                        if ($comuna === '') {
                            $errors[] = "Debes seleccionar una comuna.";
                        }
                    }

                    $fechaFin = practicantes_calcular_fecha_fin($fechaInicio, $horasExigidas, $horasPorDia);
                    if ($fechaFin === '') {
                        $fechaFin = trim((string)($_POST['fecha_fin'] ?? ''));
                    }
                    $registerDraft['fecha_fin'] = $fechaFin;

                    if ($fullName === '') {
                        $errors[] = "El nombre es obligatorio.";
                    }
                    if ($fechaCumpleanos === '') {
                        $errors[] = "La fecha de cumpleaños es obligatoria.";
                    } elseif (!practicantes_is_valid_date($fechaCumpleanos)) {
                        $errors[] = "La fecha de cumpleaños no es válida.";
                    }
                    if (trim((string)$carrera) === '') {
                        $errors[] = "Debes seleccionar una carrera.";
                    }
                    if (trim((string)$universidad) === '') {
                        $errors[] = "Debes seleccionar una universidad.";
                    }

                    if ($email !== '') {
                        $check = $db->prepare("SELECT id FROM practicantes WHERE email = ? LIMIT 1");
                        $check->bind_param('s', $email);
                        $check->execute();
                        if ($check->get_result()->fetch_assoc()) {
                            $errors[] = "Ya existe un practicante con ese correo.";
                        }
                    }
                    if ($fullName !== '') {
                        $check = $db->prepare("SELECT id FROM practicantes WHERE full_name = ? LIMIT 1");
                        $check->bind_param('s', $fullName);
                        $check->execute();
                        if ($check->get_result()->fetch_assoc()) {
                            $errors[] = "Ya existe un practicante registrado con ese nombre.";
                        }
                    }
                    if ($rut !== '') {
                        $check = $db->prepare("SELECT id FROM practicantes WHERE rut = ? LIMIT 1");
                        $check->bind_param('s', $rut);
                        $check->execute();
                        if ($check->get_result()->fetch_assoc()) {
                            $errors[] = "Ya existe un practicante registrado con ese RUT.";
                        }
                    }

                    if (!$errors) {
                        $inserted = practicantes_insert_row($db, [
                            'full_name' => $fullName,
                            'email' => $email,
                            'rut' => $rut,
                            'telefono' => $telefono,
                            'photo' => $photoPath,
                            'department_id' => $departmentId,
                            'position_id' => $positionId,
                            'fecha_inicio' => $fechaInicio,
                            'fecha_fin' => $fechaFin,
                            'fecha_cumpleanos' => $fechaCumpleanos,
                            'status' => $status,
                            'correo_institucional' => $correoInstitucional,
                            'horas_exigidas' => $horasExigidas,
                            'carrera' => $carrera,
                            'universidad' => $universidad,
                            'region' => $region,
                            'comuna' => $comuna,
                            'extranjero' => $extranjero,
                            'ubicacion_extranjero' => $ubicacionExtranjero,
                        ], $practicantesColumns);
                        if ($inserted) {
                            $messages[] = "Practicante agregado correctamente.";
                            $registerDraft = [];
                        } else {
                            $errors[] = "No se pudo guardar el practicante.";
                        }
                    }
                }
            }

            if ($formType === 'practicante_update') {
                if (!$canAdd) {
                    $errors[] = "No tienes permisos para editar practicantes.";
                } else {
                    $practicanteId = (int)($_POST['practicante_id'] ?? 0);
                    $editId = $practicanteId;
                    $fechaInicio = trim((string)($_POST['fecha_inicio'] ?? ''));
                    $horasExigidas = (int)($_POST['horas_exigidas'] ?? 0);
                    // birthday month/day parsing
                    $mes = trim((string)($_POST['cumple_mes'] ?? ''));
                    $dia = trim((string)($_POST['cumple_dia'] ?? ''));
                    if ($mes !== '' && $dia !== '') {
                        $mes = preg_replace('/[^0-9]/', '', $mes);
                        $dia = preg_replace('/[^0-9]/', '', $dia);
                        if ($mes !== '' && $dia !== '') {
                            $_POST['fecha_cumpleanos'] = sprintf('2000-%02d-%02d', (int)$mes, (int)$dia);
                        }
                    }
                    $fechaCumpleanos = trim((string)($_POST['fecha_cumpleanos'] ?? ''));
                    $status = trim((string)($_POST['status'] ?? 'active'));
                    $carreraRaw = trim((string)($_POST['carrera'] ?? ''));
                    $carrera = $carreraRaw === 'otro' ? trim((string)($_POST['carrera_otra'] ?? '')) : $carreraRaw;
                    $universidadRaw = trim((string)($_POST['universidad'] ?? ''));
                    $universidad = $universidadRaw === 'otro' ? trim((string)($_POST['universidad_otra'] ?? '')) : $universidadRaw;
                    $extranjero = isset($_POST['extranjero']) ? 1 : 0;
                    $region = trim((string)($_POST['region'] ?? ''));
                    $comunaRaw = trim((string)($_POST['comuna'] ?? ''));
                    $comuna = $comunaRaw === 'otra' ? trim((string)($_POST['comuna_otra'] ?? '')) : $comunaRaw;
                    $ubicacionExtranjero = trim((string)($_POST['ubicacion_extranjero'] ?? ''));
                    $fechaFin = practicantes_calcular_fecha_fin($fechaInicio, $horasExigidas, $horasPorDia);
                    if ($fechaFin === '') {
                        $fechaFin = trim((string)($_POST['fecha_fin'] ?? ''));
                    }
                    // photo upload (optional during edit) - replace if provided
                    $newPhoto = store_uploaded_file($_FILES['photo'] ?? [], 'practicantes');
                    $editDraft = [
                        'fecha_inicio' => $fechaInicio,
                        'horas_exigidas' => (string)$horasExigidas,
                        'fecha_fin' => $fechaFin,
                        'fecha_cumpleanos' => $fechaCumpleanos,
                        'status' => $status,
                        'carrera' => $carreraRaw,
                        'carrera_otra' => $carreraRaw === 'otro' ? $carrera : '',
                        'universidad' => $universidadRaw,
                        'universidad_otra' => $universidadRaw === 'otro' ? $universidad : '',
                        'extranjero' => $extranjero ? '1' : '0',
                        'region' => $region,
                        'comuna' => $comunaRaw,
                        'comuna_otra' => $comunaRaw === 'otra' ? $comuna : '',
                        'ubicacion_extranjero' => $ubicacionExtranjero,
                        'photo' => $newPhoto ? $newPhoto : null,
                    ];

                    if ($practicanteId <= 0) {
                        $errors[] = "Practicante no valido.";
                    }
                    if ($fechaCumpleanos === '') {
                        $errors[] = "La fecha de cumpleaños es obligatoria.";
                    } elseif (!practicantes_is_valid_date($fechaCumpleanos)) {
                        $errors[] = "La fecha de cumpleaños no es válida.";
                    }
                    if (!in_array($status, ['active', 'inactive'], true)) {
                        $status = 'active';
                    }
                    if (trim((string)$carrera) === '') {
                        $errors[] = "Debes seleccionar una carrera.";
                    }
                    if (trim((string)$universidad) === '') {
                        $errors[] = "Debes seleccionar una universidad.";
                    }
                    if ($extranjero) {
                        if ($ubicacionExtranjero === '') {
                            $errors[] = "Debes ingresar el pais o ubicacion para extranjero.";
                        }
                        $region = 'Extranjero';
                        $comuna = $ubicacionExtranjero;
                    } else {
                        $ubicacionExtranjero = '';
                        if ($region === '') {
                            $errors[] = "Debes seleccionar una region.";
                        }
                        if ($comuna === '') {
                            $errors[] = "Debes seleccionar una comuna.";
                        }
                    }

                    if (!$errors) {
                        $updates = [];
                        $types = '';
                        $values = [];
                        $map = [
                            'fecha_inicio' => ['value' => $fechaInicio, 'type' => 's'],
                            'fecha_fin' => ['value' => $fechaFin, 'type' => 's'],
                            'horas_exigidas' => ['value' => $horasExigidas, 'type' => 'i'],
                            'fecha_cumpleanos' => ['value' => $fechaCumpleanos, 'type' => 's'],
                            'status' => ['value' => $status, 'type' => 's'],
                            'carrera' => ['value' => $carrera, 'type' => 's'],
                            'universidad' => ['value' => $universidad, 'type' => 's'],
                            'region' => ['value' => $region, 'type' => 's'],
                            'comuna' => ['value' => $comuna, 'type' => 's'],
                            'extranjero' => ['value' => $extranjero, 'type' => 'i'],
                            'ubicacion_extranjero' => ['value' => $ubicacionExtranjero, 'type' => 's'],
                        ];
                        if ($newPhoto) {
                            $map['photo'] = ['value' => $newPhoto, 'type' => 's'];
                        }
                        foreach ($map as $column => $meta) {
                            if (!in_array($column, $practicantesColumns, true)) {
                                continue;
                            }
                            $updates[] = $column . " = ?";
                            $types .= $meta['type'];
                            $values[] = $meta['value'];
                        }
                        if ($updates) {
                            $types .= 'i';
                            $values[] = $practicanteId;
                            $sql = "UPDATE practicantes SET " . implode(', ', $updates) . " WHERE id = ? LIMIT 1";
                            $stmt = $db->prepare($sql);
                            if ($stmt) {
                                $stmt->bind_param($types, ...$values);
                                if ($stmt->execute()) {
                                    $messages[] = "Practicante actualizado.";
                                } else {
                                    $errors[] = "No se pudo actualizar el practicante.";
                                }
                            } else {
                                $errors[] = "No se pudo preparar la actualizacion.";
                            }
                        }
                    }
                }
            }

            if ($formType === 'practicante_horas') {
                $practicanteId = (int)($_POST['practicante_id'] ?? 0);
                $fecha = trim((string)($_POST['fecha'] ?? ''));
                $horas = (int)($_POST['horas'] ?? 0);
                $detalle = trim((string)($_POST['detalle'] ?? ''));
                if ($practicanteId > 0 && $horas > 0 && $canAdd) {
                    $stmt = $db->prepare("INSERT INTO practicante_horas (practicante_id, fecha, horas, detalle, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->bind_param('isis', $practicanteId, $fecha, $horas, $detalle);
                    $stmt->execute();
                    $messages[] = "Horas registradas.";
                } elseif (!$canAdd) {
                    $errors[] = "No tienes permisos para registrar horas.";
                }
            }

            if ($formType === 'practicante_documento') {
                $practicanteId = (int)($_POST['practicante_id'] ?? 0);
                $titulo = trim((string)($_POST['titulo'] ?? ''));
                $tipo = trim((string)($_POST['tipo'] ?? ''));
                $ruta = trim((string)($_POST['ruta'] ?? ''));
                if ($practicanteId > 0 && $titulo !== '' && $canAdd) {
                    $stmt = $db->prepare("INSERT INTO documentos (target_type, target_id, titulo, tipo, ruta, created_at) VALUES ('practicante', ?, ?, ?, ?, NOW())");
                    $stmt->bind_param('isss', $practicanteId, $titulo, $tipo, $ruta);
                    $stmt->execute();
                    $messages[] = "Documento asociado.";
                } elseif (!$canAdd) {
                    $errors[] = "No tienes permisos para agregar documentos.";
                }
            }

            if ($formType === 'practicante_observacion') {
                $practicanteId = (int)($_POST['practicante_id'] ?? 0);
                $detalle = trim((string)($_POST['detalle'] ?? ''));
                if ($practicanteId > 0 && $detalle !== '' && $canAdd) {
                    $stmt = $db->prepare("INSERT INTO observaciones (target_type, target_id, detalle, created_at) VALUES ('practicante', ?, ?, NOW())");
                    $stmt->bind_param('is', $practicanteId, $detalle);
                    $stmt->execute();
                    $messages[] = "Observacion registrada.";
                } elseif (!$canAdd) {
                    $errors[] = "No tienes permisos para agregar observaciones.";
                }
            }

            if ($formType === 'practicante_delete') {
                $practicanteId = (int)($_POST['practicante_id'] ?? 0);
                $confirmDelete = trim((string)($_POST['confirm_delete'] ?? ''));
                if ($practicanteId > 0 && $confirmDelete === 'si' && $canAdmin) {
                    $selStmt = $db->prepare("SELECT photo FROM practicantes WHERE id = ? LIMIT 1");
                    $photoPath = '';
                    if ($selStmt) {
                        $selStmt->bind_param('i', $practicanteId);
                        $selStmt->execute();
                        $selRow = $selStmt->get_result()->fetch_assoc();
                        if ($selRow) {
                            $photoPath = trim((string)($selRow['photo'] ?? ''));
                        }
                    }

                    $deleteStmt = $db->prepare("DELETE FROM practicantes WHERE id = ?");
                    if ($deleteStmt) {
                        $deleteStmt->bind_param('i', $practicanteId);
                        if ($deleteStmt->execute()) {
                            if ($photoPath !== '' && function_exists('sedes_delete_uploaded_file')) {
                                sedes_delete_uploaded_file($photoPath);
                            }
                            $messages[] = "Practicante eliminado correctamente.";
                            $viewId = 0;
                            $editId = 0;
                        } else {
                            $errors[] = "Error al eliminar el practicante.";
                        }
                    } else {
                        $errors[] = "No se pudo preparar la eliminacion.";
                    }
                } else {
                    $errors[] = "No tienes permisos para eliminar practicantes.";
                }
            }

            if (in_array($formType, ['department', 'position'], true)) {
                $name = trim((string)($_POST['name'] ?? ''));
                if (!$canAdmin) {
                    $detail = "Solicitud de actualizacion catalogo " . $formType . ": " . $name;
                    if (function_exists('create_change_request')) {
                        create_change_request($db, $userId, 'catalogos', 'crear', $detail);
                        $messages[] = "Solicitud enviada al Encargado de RRHH.";
                    } else {
                        $errors[] = "No tienes permisos para actualizar catalogos.";
                    }
                } elseif ($name !== '') {
                    $table = $formType === 'department' ? 'departamentos' : 'cargos';
                    $stmt = $db->prepare("INSERT INTO " . $table . " (name) VALUES (?)");
                    $stmt->bind_param('s', $name);
                    $stmt->execute();
                    $messages[] = "Catalogo actualizado.";
                }
            }
        }

        $filterName = trim((string)($_GET['q'] ?? ''));
        $filterUniversidad = trim((string)($_GET['universidad'] ?? ''));
        $filterCarrera = trim((string)($_GET['carrera'] ?? ''));
        $filterArea = trim((string)($_GET['area'] ?? ''));
        $filterStatus = trim((string)($_GET['status'] ?? ''));
        $sort = trim((string)($_GET['sort'] ?? 'fecha'));
        $sortSql = 'e.fecha_inicio DESC';
        if ($sort === 'cumplidas') {
            $sortSql = 'horas_cumplidas DESC';
        } elseif ($sort === 'pendientes') {
            $sortSql = '(IFNULL(e.horas_exigidas,0) - horas_cumplidas) DESC';
        }

        $filters = [];
        $params = [];
        $types = '';
        if ($filterName !== '') {
            $filters[] = "e.full_name LIKE ?";
            $params[] = '%' . $filterName . '%';
            $types .= 's';
        }
        if ($filterUniversidad !== '') {
            $filters[] = "e.universidad LIKE ?";
            $params[] = '%' . $filterUniversidad . '%';
            $types .= 's';
        }
        if ($filterCarrera !== '') {
            $filters[] = "e.carrera LIKE ?";
            $params[] = '%' . $filterCarrera . '%';
            $types .= 's';
        }
        if ($filterArea !== '') {
            $filters[] = "d.name LIKE ?";
            $params[] = '%' . $filterArea . '%';
            $types .= 's';
        }
        if ($filterStatus !== '' && in_array($filterStatus, ['active', 'inactive'], true)) {
            $filters[] = "e.status = ?";
            $params[] = $filterStatus;
            $types .= 's';
        }

        $optionalSelectColumns = [];
        foreach (['region', 'comuna', 'extranjero', 'ubicacion_extranjero'] as $column) {
            if (in_array($column, $practicantesColumns, true)) {
                $optionalSelectColumns[] = "e." . $column;
            }
        }
        $optionalSelectSql = $optionalSelectColumns ? ", " . implode(', ', $optionalSelectColumns) : '';

        $photoSelectSql = in_array('photo', $practicantesColumns, true) ? 'e.photo' : 'NULL AS photo';
        $sql = "SELECT e.id, " . $photoSelectSql . ", e.full_name, e.email, e.rut, e.telefono, e.fecha_inicio, e.fecha_fin, e.status,
                e.correo_institucional, e.horas_exigidas, e.carrera, e.universidad" . $optionalSelectSql . ",
                d.name AS department, p.name AS position, COALESCE(SUM(h.horas),0) AS horas_cumplidas
                FROM practicantes e
                LEFT JOIN departamentos d ON d.id = e.department_id
                LEFT JOIN cargos p ON p.id = e.position_id
                LEFT JOIN practicante_horas h ON h.practicante_id = e.id";
        if ($filters) {
            $sql .= " WHERE " . implode(" AND ", $filters);
        }
        $sql .= " GROUP BY e.id ORDER BY " . $sortSql . " LIMIT 200";
        $stmt = $db->prepare($sql);
        $rows = null;
        if (!$stmt) {
            $errors[] = "No se pudo preparar la consulta de practicantes. Revisa que las tablas y columnas de la base de datos estén disponibles.";
        } else {
            try {
                if ($params) {
                    $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $rows = $stmt->get_result();
            } catch (Throwable $e) {
                $errors[] = "No se pudo cargar el listado de practicantes. Revisa la estructura de la base de datos.";
            }
            $stmt->close();
        }

        $departmentsList = $db->query("SELECT id, name FROM departamentos ORDER BY name ASC");
        $positionsList = $db->query("SELECT id, name FROM cargos ORDER BY name ASC");

        $viewPract = null;
        $horasList = [];
        $docs = [];
        $obs = [];
        $totalHoras = 0;
        $horasPendientes = 0;
        $targetId = $viewId > 0 ? $viewId : $editId;
        if ($targetId > 0) {
            $detailStmt = $db->prepare("SELECT e.*, d.name AS department, p.name AS position
                FROM practicantes e
                LEFT JOIN departamentos d ON d.id = e.department_id
                LEFT JOIN cargos p ON p.id = e.position_id
                WHERE e.id = ? LIMIT 1");
            $detailStmt->bind_param('i', $targetId);
            $detailStmt->execute();
            $viewPract = $detailStmt->get_result()->fetch_assoc();
            if ($viewPract) {
                $horasRes = $db->query("SELECT fecha, horas, detalle, created_at FROM practicante_horas WHERE practicante_id = " . (int)$targetId . " ORDER BY fecha DESC, id DESC");
                if ($horasRes) {
                    while ($row = $horasRes->fetch_assoc()) {
                        $horasList[] = $row;
                        $totalHoras += (int)($row['horas'] ?? 0);
                    }
                }

                $docRes = $db->query("SELECT titulo, tipo, ruta, created_at FROM documentos WHERE target_type = 'practicante' AND target_id = " . (int)$targetId . " ORDER BY created_at DESC");
                if ($docRes) {
                    while ($row = $docRes->fetch_assoc()) {
                        $docs[] = $row;
                    }
                }

                $obsRes = $db->query("SELECT detalle, created_at FROM observaciones WHERE target_type = 'practicante' AND target_id = " . (int)$targetId . " ORDER BY created_at DESC");
                if ($obsRes) {
                    while ($row = $obsRes->fetch_assoc()) {
                        $obs[] = $row;
                    }
                }

                $horasExigidas = (int)($viewPract['horas_exigidas'] ?? 0);
                $horasPendientes = max(0, $horasExigidas - $totalHoras);
            }
        }

        return [
            'departments' => $departments,
            'positions' => $positions,
            'messages' => $messages,
            'errors' => $errors,
            'formType' => $formType,
            'viewId' => $viewId,
            'editId' => $editId,
            'filterName' => $filterName,
            'filterUniversidad' => $filterUniversidad,
            'filterCarrera' => $filterCarrera,
            'filterArea' => $filterArea,
            'filterStatus' => $filterStatus,
            'sort' => $sort,
            'rows' => $rows,
            'departmentsList' => $departmentsList,
            'positionsList' => $positionsList,
            'viewPract' => $viewPract,
            'horasList' => $horasList,
            'docs' => $docs,
            'obs' => $obs,
            'totalHoras' => $totalHoras,
            'horasPendientes' => $horasPendientes,
            'canAdd' => $canAdd,
            'canAdmin' => $canAdmin,
            'carreras' => $carreras,
            'universidades' => $universidades,
            'registerDraft' => $registerDraft,
            'editDraft' => $editDraft,
            'activeModule' => $activeModule,
            'horasPorDia' => $horasPorDia,
        ];
    }
}
