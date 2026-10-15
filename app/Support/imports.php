<?php

if (!function_exists('csv_detect_delimiter')) {
    function csv_detect_delimiter($line)
    {
        $delims = [',', ';', "\t"];
        $counts = [];
        foreach ($delims as $delim) {
            $counts[$delim] = substr_count($line, $delim);
        }
        $max = max($counts);
        if ($max > 0) {
            return array_keys($counts, $max)[0];
        }
        return ';';
    }
}

if (!function_exists('csv_normalize_header')) {
    function csv_normalize_header($col)
    {
        $clean = $col;
        if (function_exists('iconv')) {
            $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $clean);
        }
        $clean = strtolower(trim((string)$clean));
        $clean = preg_replace('/[^a-z0-9]+/', '_', $clean);
        return trim($clean, '_');
    }
}

if (!function_exists('csv_open_upload')) {
    function csv_open_upload(array $file, &$errors)
    {
        if (empty($file) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "No se pudo leer el archivo CSV.";
            return null;
        }
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            $errors[] = "No se pudo abrir el archivo CSV.";
            return null;
        }
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            $errors[] = "El CSV esta vacio.";
            fclose($handle);
            return null;
        }
        $delimiter = csv_detect_delimiter($firstLine);
        rewind($handle);
        $header = fgetcsv($handle, 0, $delimiter);
        if (!$header) {
            $errors[] = "No se detectaron columnas en el CSV.";
            fclose($handle);
            return null;
        }
        return [$handle, $header, $delimiter];
    }
}

if (!function_exists('csv_index_map')) {
    function csv_index_map(array $header, array $aliases)
    {
        $indexes = [];
        foreach ($header as $idx => $col) {
            $clean = csv_normalize_header($col);
            if (isset($aliases[$clean])) {
                $indexes[$aliases[$clean]] = $idx;
            }
        }
        return $indexes;
    }
}

if (!function_exists('csv_get_cell')) {
    function csv_get_cell(array $row, array $indexes, $key)
    {
        if (!isset($indexes[$key])) {
            return '';
        }
        $idx = $indexes[$key];
        return isset($row[$idx]) ? $row[$idx] : '';
    }
}

if (!function_exists('csv_bool_value')) {
    function csv_bool_value($value)
    {
        $val = strtolower(trim((string)$value));
        return in_array($val, ['1', 'si', 'sí', 'true', 'yes', 'y'], true) ? 1 : 0;
    }
}

if (!function_exists('import_relatores_csv')) {
    function import_relatores_csv(mysqli $db, array $file)
    {
        $messages = [];
        $errors = [];
        $opened = csv_open_upload($file, $errors);
        if (!$opened) {
            return [$messages, $errors];
        }
        [$handle, $header, $delimiter] = $opened;

        $relatorIdCol = relator_id_column($db);
        $relatorNameCol = relator_name_column($db);
        $relatorCols = db_table_columns($db, 'relatores');

        $aliases = [
            'nombre_relator' => $relatorNameCol,
            'nombre' => $relatorNameCol,
            'relator' => $relatorNameCol,
            'full_name' => $relatorNameCol,
            'email' => 'email',
            'correo' => 'email',
            'telefono' => 'telefono',
            'contacto' => 'telefono',
            'especialidades' => 'especialidades',
            'especialidad' => 'especialidades',
            'cursos' => 'cursos',
            'curso' => 'cursos',
            'carrera' => 'carrera',
            'academico' => 'academico',
            'datos_academicos' => 'academico',
            'experiencia_cc' => 'experiencia_cc',
            'experiencia' => 'experiencia_cc',
            'sence' => 'sence',
            'universidad' => 'universidad',
            'region' => 'region',
            'comuna' => 'comuna',
            'observaciones' => 'observaciones',
            'obs' => 'observaciones',
            'cv_path' => 'cv_path',
            'cv' => 'cv_path',
            'anio_egreso' => 'anio_egreso',
            'egreso' => 'anio_egreso',
        ];
        $indexes = csv_index_map($header, $aliases);

        if (!isset($indexes[$relatorNameCol])) {
            $errors[] = "El CSV debe incluir la columna nombre_relator (o nombre/relator).";
            fclose($handle);
            return [$messages, $errors];
        }

        $imported = 0;
        $skipped = 0;
        while (($row = fgetcsv($handle, 100000, $delimiter)) !== false) {
            if (count($row) === 1 && trim((string)$row[0]) === '') {
                continue;
            }
            $nombre = trim((string)csv_get_cell($row, $indexes, $relatorNameCol));
            echo "<pre>";
            var_dump($row);
            echo "</pre>";
            exit;
            if ($nombre === '') {
                $skipped++;
                continue;
            }
            $colValues = [
                $relatorNameCol => $nombre,
                'email' => trim((string)csv_get_cell($row, $indexes, 'email')),
                'telefono' => trim((string)csv_get_cell($row, $indexes, 'telefono')),
                'especialidades' => trim((string)csv_get_cell($row, $indexes, 'especialidades')),
                'cursos' => trim((string)csv_get_cell($row, $indexes, 'cursos')),
                'carrera' => trim((string)csv_get_cell($row, $indexes, 'carrera')),
                'academico' => trim((string)csv_get_cell($row, $indexes, 'academico')),
                'experiencia_cc' => csv_bool_value(csv_get_cell($row, $indexes, 'experiencia_cc')),
                'sence' => csv_bool_value(csv_get_cell($row, $indexes, 'sence')),
                'universidad' => trim((string)csv_get_cell($row, $indexes, 'universidad')),
                'region' => trim((string)csv_get_cell($row, $indexes, 'region')),
                'comuna' => trim((string)csv_get_cell($row, $indexes, 'comuna')),
                'calificacion' => trim((string)csv_get_cell($row, $indexes, 'calificacion')),
                'observaciones' => trim((string)csv_get_cell($row, $indexes, 'observaciones')),
                'cv_path' => trim((string)csv_get_cell($row, $indexes, 'cv_path')),
                'anio_egreso' => trim((string)csv_get_cell($row, $indexes, 'anio_egreso')),
            ];

            $columns = [];
            $placeholders = [];
            $types = '';
            $values = [];
            foreach ($colValues as $col => $val) {
                if (!in_array($col, $relatorCols, true)) {
                    continue;
                }
                if ($val === '' || $val === null) {
                    if (in_array($col, ['experiencia_cc', 'sence'], true)) {
                        $val = (int)$val;
                    } else {
                        continue;
                    }
                }
                $columns[] = $col;
                $placeholders[] = '?';
                if (in_array($col, ['experiencia_cc', 'sence'], true)) {
                    $types .= 'i';
                    $values[] = (int)$val;
                } elseif ($col === 'calificacion') {
                    $types .= 'd';
                    $values[] = (float)str_replace(',', '.', (string)$val);
                } elseif ($col === 'anio_egreso') {
                    $types .= 'i';
                    $values[] = (int)$val;
                } else {
                    $types .= 's';
                    $values[] = (string)$val;
                }
            }
            if ($columns) {
                $stmt = $db->prepare("INSERT INTO relatores (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")");
                if ($stmt) {
                    $stmt->bind_param($types, ...$values);
                    if ($stmt->execute()) {
                        $imported++;
                    } else {
                        $skipped++;
                    }
                } else {
                    $skipped++;
                }
            } else {
                $skipped++;
            }
        }
        fclose($handle);

        if ($imported > 0) {
            $messages[] = "Importados $imported relatores.";
        }
        if ($skipped > 0) {
            $messages[] = "Omitidos $skipped registros por datos incompletos.";
        }
        return [$messages, $errors];
    }
}

if (!function_exists('import_cursos_csv')) {
    function import_cursos_csv(mysqli $db, array $file)
    {
        $messages = [];
        $errors = [];
        $opened = csv_open_upload($file, $errors);
        if (!$opened) {
            return [$messages, $errors];
        }
        [$handle, $header, $delimiter] = $opened;

        $capCols = db_table_columns($db, 'capacitaciones');
        $relatorIdCol = relator_id_column($db);
        $relatorNameCol = relator_name_column($db);

        $aliases = [
            'title' => 'title',
            'curso' => 'title',
            'nombre_curso' => 'title',
            'cliente' => 'cliente',
            'execution_date' => 'execution_date',
            'fecha_ejecucion' => 'execution_date',
            'fecha' => 'execution_date',
            'trainer_id' => 'trainer_id',
            'relator_id' => 'trainer_id',
            'relator' => 'relator_name',
            'monitor' => 'monitor',
            'lugar' => 'lugar',
            'horas' => 'horas',
            'description' => 'description',
            'observaciones' => 'description',
            'report_path' => 'report_path',
            'informe' => 'report_path',
        ];
        $indexes = csv_index_map($header, $aliases);

        if (!isset($indexes['title'])) {
            $errors[] = "El CSV debe incluir la columna title (o curso/nombre_curso).";
            fclose($handle);
            return [$messages, $errors];
        }

        $imported = 0;
        $skipped = 0;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($row) === 1 && trim((string)$row[0]) === '') {
                continue;
            }
            $title = trim((string)csv_get_cell($row, $indexes, 'title'));
            if ($title === '') {
                $skipped++;
                continue;
            }
            $executionDate = trim((string)csv_get_cell($row, $indexes, 'execution_date'));
            $trainerId = (int)csv_get_cell($row, $indexes, 'trainer_id');
            $relatorName = trim((string)csv_get_cell($row, $indexes, 'relator_name'));
            if ($trainerId <= 0 && $relatorName !== '') {
                $stmtRel = $db->prepare("SELECT " . $relatorIdCol . " AS id FROM relatores WHERE " . $relatorNameCol . " = ? LIMIT 1");
                if ($stmtRel) {
                    $stmtRel->bind_param('s', $relatorName);
                    $stmtRel->execute();
                    $rowRel = $stmtRel->get_result()->fetch_assoc();
                    if ($rowRel) {
                        $trainerId = (int)$rowRel['id'];
                    }
                }
            }

            $colValues = [
                'title' => $title,
                'cliente' => trim((string)csv_get_cell($row, $indexes, 'cliente')),
                'execution_date' => $executionDate,
                'trainer_id' => $trainerId > 0 ? $trainerId : null,
                'monitor' => trim((string)csv_get_cell($row, $indexes, 'monitor')),
                'lugar' => trim((string)csv_get_cell($row, $indexes, 'lugar')),
                'horas' => (int)csv_get_cell($row, $indexes, 'horas'),
                'description' => trim((string)csv_get_cell($row, $indexes, 'description')),
                'estado' => 'finalizado',
                'report_path' => trim((string)csv_get_cell($row, $indexes, 'report_path')),
            ];

            $columns = [];
            $placeholders = [];
            $types = '';
            $values = [];
            foreach ($colValues as $col => $val) {
                if (!in_array($col, $capCols, true)) {
                    continue;
                }
                if ($val === '' || $val === null) {
                    continue;
                }
                $columns[] = $col;
                $placeholders[] = '?';
                if (in_array($col, ['trainer_id', 'horas'], true)) {
                    $types .= 'i';
                    $values[] = (int)$val;
                } else {
                    $types .= 's';
                    $values[] = (string)$val;
                }
            }
            if ($executionDate !== '' && in_array('start_date', $capCols, true)) {
                $columns[] = 'start_date';
                $placeholders[] = '?';
                $types .= 's';
                $values[] = $executionDate;
            }
            if ($executionDate !== '' && in_array('end_date', $capCols, true)) {
                $columns[] = 'end_date';
                $placeholders[] = '?';
                $types .= 's';
                $values[] = $executionDate;
            }

            if ($columns) {
                $stmt = $db->prepare("INSERT INTO capacitaciones (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")");
                if ($stmt) {
                    $stmt->bind_param($types, ...$values);
                    if ($stmt->execute()) {
                        $imported++;
                    } else {
                        $skipped++;
                    }
                } else {
                    $skipped++;
                }
            } else {
                $skipped++;
            }
        }
        fclose($handle);

        if ($imported > 0) {
            $messages[] = "Importados $imported cursos finalizados.";
        }
        if ($skipped > 0) {
            $messages[] = "Omitidos $skipped registros por datos incompletos.";
        }
        return [$messages, $errors];
    }
}
