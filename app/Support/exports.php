<?php

if (!function_exists('send_csv')) {
    function send_csv($filename, array $headers, array $rows)
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($out, array_values($row), ';');
        }
        fclose($out);
        exit;
    }
}

if (!function_exists('export_practicantes_template_if_requested')) {
    function export_practicantes_template_if_requested($route, $user)
    {
        if (!$user || $route !== 'practicantes' || ($_GET['export'] ?? '') !== 'template') {
            return false;
        }

        $filename = 'khironex-practicantes-template.csv';
        $headers = [
            'nombre_completo',
            'correo',
            'telefono',
            'rut',
            'departamento',
            'cargo',
            'correo_institucional',
            'fecha_inicio',
            'fecha_fin',
            'fecha_cumpleanos',
            'horas_exigidas',
            'carrera',
            'universidad',
            'region',
            'comuna',
            'extranjero',
            'ubicacion_extranjero',
            'estado',
        ];
        $rows = [[
            'Nombre Apellido',
            'correo@personal.com',
            '99999999',
            '12.345.678-9',
            'RRHH',
            'Analista',
            'correo@empresa.com',
            '2026-02-01',
            '2026-07-31',
            '2000-08-15',
            '360',
            'Psicologia',
            'Universidad Ejemplo',
            'Metropolitana',
            'Santiago',
            '0',
            '',
            'active',
        ]];

        send_csv($filename, $headers, $rows);
    }
}

if (!function_exists('export_herramientas_templates_if_requested')) {
    function export_herramientas_templates_if_requested($route, $canAccess)
    {
        if (!$canAccess || $route !== 'herramientas' || !isset($_GET['export'])) {
            return false;
        }

        $export = (string)$_GET['export'];
        if ($export === 'relatores_template') {
            send_csv('plantilla-relatores.csv', [
                'nombre_relator',
                'email',
                'telefono',
                'especialidades',
                'cursos',
                'carrera',
                'academico',
                'experiencia_cc',
                'sence',
                'universidad',
                'region',
                'comuna',
                'observaciones',
                'cv_path',
            ], [[
                'Relator Ejemplo',
                'relator@empresa.com',
                '99999999',
                'Excel; RRHH',
                'Induccion; Liderazgo',
                'Psicologia',
                'Universidad Ejemplo',
                'si',
                'si',
                'Universidad Ejemplo',
                'Region',
                'Comuna',
                'Observaciones',
                'https://ruta/cv.pdf',
            ]]);
        }

        if ($export === 'cursos_template') {
            send_csv('plantilla-cursos-finalizados.csv', [
                'title',
                'cliente',
                'execution_date',
                'relator',
                'monitor',
                'lugar',
                'horas',
                'description',
                'report_path',
            ], [[
                'Curso Ejemplo',
                'Empresa Cliente',
                '2026-02-01',
                'Relator Ejemplo',
                'Nombre Monitor',
                'Sala 1',
                '8',
                'Observaciones',
                'https://ruta/informe.pdf',
            ]]);
        }

        return false;
    }
}

if (!function_exists('export_informes_if_requested')) {
    function export_informes_if_requested($route, $user, mysqli $db)
    {
        if (!$user || $route !== 'informes' || !isset($_GET['export'])) {
            return false;
        }

        $getTableColumns = function (mysqli $db, $table) {
            $cols = [];
            $result = $db->query("SHOW COLUMNS FROM " . $table);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $cols[] = $row['Field'];
                }
            }
            return $cols;
        };

        $resolveRelatorNameColumn = function (mysqli $db) use ($getTableColumns) {
            $candidates = ['nombre_relator', 'full_name', 'nombre', 'name'];
            $cols = $getTableColumns($db, 'relatores');
            foreach ($candidates as $candidate) {
                if (in_array($candidate, $cols, true)) {
                    return $candidate;
                }
            }
            return null;
        };

        $resolveRelatorIdColumn = function (mysqli $db) use ($getTableColumns) {
            $candidates = ['id_relator', 'id', 'relator_id', 'trainer_id'];
            $cols = $getTableColumns($db, 'relatores');
            foreach ($candidates as $candidate) {
                if (in_array($candidate, $cols, true)) {
                    return $candidate;
                }
            }
            return null;
        };

        $resolveCapacitacionRelatorFk = function (mysqli $db) use ($getTableColumns) {
            $candidates = ['trainer_id', 'relator_id', 'relator'];
            $cols = $getTableColumns($db, 'capacitaciones');
            foreach ($candidates as $candidate) {
                if (in_array($candidate, $cols, true)) {
                    return $candidate;
                }
            }
            return null;
        };

        $export = (string)$_GET['export'];
        $filename = 'khironex-reporte.csv';
        $rows = [];
        $headers = [];

        if ($export === 'practicantes') {
            $filename = 'khironex-practicantes.csv';
            $headers = ['Nombre', 'Email', 'RUT', 'Telefono', 'Departamento', 'Cargo', 'Inicio', 'Fin', 'Estado'];
            $result = $db->query("SELECT e.full_name, e.email, e.rut, e.telefono, d.name AS departamento, p.name AS cargo, e.fecha_inicio, e.fecha_fin, e.status
                FROM practicantes e
                LEFT JOIN departamentos d ON d.id = e.department_id
                LEFT JOIN cargos p ON p.id = e.position_id
                ORDER BY e.id DESC");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
            }
        } elseif ($export === 'capacitaciones') {
            $filename = 'khironex-capacitaciones.csv';
            $headers = ['Titulo', 'Relator', 'Participantes', 'Inicio', 'Termino', 'Lugar', 'Horas', 'Estado', 'Informe'];
            $relatorCol = $resolveRelatorNameColumn($db);
            $relatorSelect = $relatorCol ? ("tr." . $relatorCol . " AS relator") : "'' AS relator";
            $relatorIdCol = $resolveRelatorIdColumn($db);
            $capRelatorFk = $resolveCapacitacionRelatorFk($db);
            $joinClause = '';
            if ($relatorIdCol && $capRelatorFk) {
                $joinClause = "LEFT JOIN relatores tr ON tr." . $relatorIdCol . " = t." . $capRelatorFk;
            }
            $result = $db->query("SELECT t.title, " . $relatorSelect . ",
                (SELECT COUNT(*) FROM capacitaciones_participantes tp WHERE tp.training_id = t.id) AS participantes,
                t.start_date, t.end_date, t.lugar, t.horas, t.estado, t.report_path
                FROM capacitaciones t
                " . $joinClause . "
                ORDER BY t.id DESC");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
            }
        } elseif ($export === 'departamentos') {
            $filename = 'khironex-departamentos.csv';
            $headers = ['Departamento', 'Total'];
            $result = $db->query("SELECT d.name, COUNT(e.id) AS total
                FROM departamentos d
                LEFT JOIN practicantes e ON e.department_id = d.id
                GROUP BY d.id ORDER BY total DESC");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
            }
        } elseif ($export === 'relatores') {
            $filename = 'khironex-relatores.csv';
            $headers = ['Relator', 'Total capacitaciones'];
            $relatorCol = $resolveRelatorNameColumn($db);
            $relatorSelect = $relatorCol ? ("t." . $relatorCol . " AS relator") : "'' AS relator";
            $relatorIdCol = $resolveRelatorIdColumn($db);
            $capRelatorFk = $resolveCapacitacionRelatorFk($db);
            $joinClause = '';
            $groupBy = '';
            $countExpr = '0';
            if ($relatorIdCol && $capRelatorFk) {
                $joinClause = "LEFT JOIN capacitaciones tr ON tr." . $capRelatorFk . " = t." . $relatorIdCol;
                $groupBy = "GROUP BY t." . $relatorIdCol;
                $countExpr = 'COUNT(tr.' . $capRelatorFk . ')';
            }
            $result = $db->query("SELECT " . $relatorSelect . ", " . $countExpr . " AS total
                FROM relatores t
                " . $joinClause . "
                " . $groupBy . "
                ORDER BY total DESC");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
            }
        } elseif ($export === 'relatores_reuf') {
            $filename = 'khironex-relatores-sistema-reuf.csv';
            $cols = $getTableColumns($db, 'relatores');
            $relatorCol = null;
            foreach (['nombre_relator', 'full_name', 'name'] as $candidate) {
                if (in_array($candidate, $cols, true)) {
                    $relatorCol = $candidate;
                    break;
                }
            }
            if (!$relatorCol) {
                $relatorCol = 'nombre_relator';
            }
            $relatorSelect = "t." . $relatorCol . " AS relator";
            $headers = ['Relator', 'Email', 'Telefono', 'Universidad', 'Carrera', 'Sistema REUF', 'Calificacion', 'Total capacitaciones'];
            $relatorIdCol = $resolveRelatorIdColumn($db);
            $capRelatorFk = $resolveCapacitacionRelatorFk($db);
            $joinClause = '';
            $groupBy = '';
            $countExpr = '0';
            if ($relatorIdCol && $capRelatorFk) {
                $joinClause = "LEFT JOIN capacitaciones tr ON tr." . $capRelatorFk . " = t." . $relatorIdCol;
                $groupBy = "GROUP BY t." . $relatorIdCol;
                $countExpr = 'COUNT(tr.' . $capRelatorFk . ')';
            }
            $result = $db->query("SELECT " . $relatorSelect . ", t.email, t.telefono, t.universidad, t.carrera, t.sistema_reuf, t.calificacion, " . $countExpr . " AS total
                FROM relatores t
                " . $joinClause . "
                " . $groupBy . "
                ORDER BY t." . $relatorCol . " ASC");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
            }
        }

        if ($headers) {
            send_csv($filename, $headers, $rows);
        }

        return false;
    }
}
