<?php

if (!function_exists('load_dashboard_config')) {
    function load_dashboard_config($configPath)
    {
        if (is_file($configPath)) {
            try {
                $dashboardConfig = require $configPath;
                if (is_array($dashboardConfig)) {
                    return $dashboardConfig;
                }
            } catch (Throwable $e) {
                return [];
            }
        }
        return [];
    }
}

if (!function_exists('dashboard_menu')) {
    function dashboard_menu(array $dashboardConfig)
    {
        return $dashboardConfig['menu'] ?? [
            ['label' => 'Administrador ajustes', 'route' => 'usuarios'],
            ['label' => 'Gestión de base de datos', 'route' => 'database'],
            ['label' => 'Dashboard (resumen)', 'route' => 'dashboard'],
            ['label' => 'Contactos', 'route' => 'contactos'],
            ['label' => 'Postulantes', 'route' => 'postulantes'],
            ['label' => 'Practicantes', 'route' => 'practicantes'],
            ['label' => 'Relatores', 'route' => 'relatores'],
            ['label' => 'Cursos finalizados', 'route' => 'capacitaciones'],
            ['label' => 'Sedes de capacitaciones', 'route' => 'sedes'],
            ['label' => 'Informes', 'route' => 'informes'],
            ['label' => 'Solicitudes', 'route' => 'solicitudes'],
            ['label' => 'Tickets de soporte', 'route' => 'tickets'],
            ['label' => 'Tareas', 'route' => 'tareas'],
            ['label' => 'Herramientas', 'route' => 'herramientas'],
            ['label' => 'Archivos compartidos', 'route' => 'archivos'],
            ['label' => 'Historial', 'route' => 'historial'],
        ];
    }
}

if (!function_exists('dashboard_labels')) {
    function dashboard_labels()
    {
        return [
            'dashboard' => 'Dashboard',
            'contactos' => 'Contactos',
            'database' => 'Gestión de base de datos',
            'postulantes' => 'Postulantes',
            'practicantes' => 'Practicantes',
            'relatores' => 'Relatores',
            'capacitaciones' => 'Cursos finalizados',
            'sedes' => 'Sedes de capacitaciones',
            'informes' => 'Informes',
            'perfil' => 'Mi perfil',
            'usuarios' => 'Usuarios',
            'solicitudes' => 'Solicitudes',
            'tickets' => 'Tickets de soporte',
            'tareas' => 'Tareas',
            'herramientas' => 'Herramientas',
            'archivos' => 'Archivos compartidos',
            'historial' => 'Historial',
        ];
    }
}

if (!function_exists('recent_history_entries')) {
    function recent_history_entries(mysqli $db, $limit = 3)
    {
        $rows = [];
        $stmt = $db->prepare("SELECT h.id_historial, h.usuario_id, h.tabla_afectada, h.registro_id, h.accion, h.descripcion, h.fecha, u.full_name AS usuario_nombre
            FROM historial h
            LEFT JOIN usuarios u ON u.id = h.usuario_id
            ORDER BY h.fecha DESC, h.id_historial DESC
            LIMIT ?");
        $stmt->bind_param('i', $limit);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}

if (!function_exists('dashboard_build_data')) {
    function dashboard_build_data(mysqli $db, array $dashboardConfig, $includeHistory = false)
    {
        $contactsRaw = $dashboardConfig['contacts'] ?? [];
        $contacts = [];
        $contactsText = '';
        if (is_array($contactsRaw)) {
            foreach ($contactsRaw as $item) {
                if (is_array($item)) {
                    $status = trim((string)($item['status'] ?? ''));
                    if ($status === '') {
                        $status = 'Activo';
                    }
                    $contact = [
                        'area' => trim((string)($item['area'] ?? $item['equipo'] ?? '')),
                        'cargo' => trim((string)($item['cargo'] ?? $item['rol'] ?? '')),
                        'nombre' => trim((string)($item['nombre'] ?? $item['name'] ?? '')),
                        'apellido' => trim((string)($item['apellido'] ?? $item['last_name'] ?? '')),
                        'contacto' => trim((string)($item['contacto'] ?? $item['email'] ?? $item['correo'] ?? '')),
                        'status' => $status,
                    ];
                    if (implode('', $contact) === '') {
                        continue;
                    }
                    $contacts[] = $contact;
                } else {
                    $label = trim((string)$item);
                    if ($label !== '') {
                        $contacts[] = [
                            'area' => '',
                            'cargo' => '',
                            'nombre' => $label,
                            'apellido' => '',
                            'contacto' => '',
                        ];
                    }
                }
            }
        } else {
            $contactsText = trim((string)$contactsRaw);
        }
        $birthdays = [];
        $birthdayWindowDays = max(1, (int)($dashboardConfig['birthdays_window_days'] ?? 30));
        $expiring = [];
        $alerts = [];
        if (function_exists('pending_requests_over_limit')) {
            try {
                $alerts = pending_requests_over_limit($db, 3);
            } catch (Throwable $e) {
                $alerts = [];
            }
        }

        $practicantesCols = db_table_columns($db, 'practicantes');
        $birthdayNameColumn = '';
        foreach (['full_name', 'nombre', 'name'] as $candidate) {
            if (in_array($candidate, $practicantesCols, true)) {
                $birthdayNameColumn = $candidate;
                break;
            }
        }
        if ($birthdayNameColumn !== '' && in_array('fecha_cumpleanos', $practicantesCols, true)) {
            $birthdaysRaw = [];
            $birthdaysWhere = "WHERE fecha_cumpleanos IS NOT NULL
                  AND CAST(fecha_cumpleanos AS CHAR(10)) <> '0000-00-00'
                  AND CAST(fecha_cumpleanos AS CHAR(10)) <> ''
                  AND " . $birthdayNameColumn . " IS NOT NULL
                  AND TRIM(" . $birthdayNameColumn . ") <> ''";
            $birthdaysQuery = $db->query("SELECT " . $birthdayNameColumn . " AS birthday_name, fecha_cumpleanos
                FROM practicantes
                " . $birthdaysWhere . "
                ORDER BY " . $birthdayNameColumn . " ASC");
            if ($birthdaysQuery) {
                $today = new DateTimeImmutable('today');
                $currentYear = (int)$today->format('Y');
                while ($row = $birthdaysQuery->fetch_assoc()) {
                    $birthdayValue = (string)($row['fecha_cumpleanos'] ?? '');
                    $birthdayDate = DateTimeImmutable::createFromFormat('Y-m-d', $birthdayValue);
                    if (!$birthdayDate || $birthdayDate->format('Y-m-d') !== $birthdayValue) {
                        continue;
                    }

                    $month = (int)$birthdayDate->format('m');
                    $day = (int)$birthdayDate->format('d');
                    $name = trim((string)($row['birthday_name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }

                    $buildOccurrence = function ($year) use ($month, $day) {
                        $targetDay = $day;
                        if (!checkdate($month, $targetDay, $year)) {
                            if ($month === 2 && $day === 29) {
                                $targetDay = 28;
                            } else {
                                return null;
                            }
                        }
                        $date = DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $year, $month, $targetDay));
                        if (!$date) {
                            return null;
                        }
                        return $date;
                    };

                    $nextBirthday = $buildOccurrence($currentYear);
                    if (!$nextBirthday) {
                        continue;
                    }
                    if ($nextBirthday < $today) {
                        $nextBirthday = $buildOccurrence($currentYear + 1);
                        if (!$nextBirthday) {
                            continue;
                        }
                    }

                    $daysUntil = (int)$today->diff($nextBirthday)->format('%a');
                    if ($daysUntil > $birthdayWindowDays) {
                        continue;
                    }

                    $birthdaysRaw[] = [
                        'name' => $name,
                        'date' => $nextBirthday->format('d/m'),
                        'days' => $daysUntil,
                    ];
                }
            }

            if ($birthdaysRaw) {
                usort($birthdaysRaw, function ($a, $b) {
                    if ($a['days'] === $b['days']) {
                        return strcmp((string)$a['name'], (string)$b['name']);
                    }
                    return $a['days'] <=> $b['days'];
                });
                $birthdays = array_slice($birthdaysRaw, 0, 12);
            }
        }

        $expiringAutoRows = [];
        if (function_exists('db_table_exists') && db_table_exists($db, 'practicantes')) {
            try {
                $expiringQuery = $db->query("SELECT exp.area, exp.name, exp.days
                        FROM (
                            SELECT d.name AS area, e.full_name AS name,
                                DATEDIFF(
                                    STR_TO_DATE(NULLIF(CAST(e.fecha_fin AS CHAR(10)), '0000-00-00'), '%Y-%m-%d'),
                                    CURDATE()
                                ) AS days
                            FROM practicantes e
                            LEFT JOIN departamentos d ON d.id = e.department_id
                            WHERE e.fecha_fin IS NOT NULL
                              AND e.status = 'active'
                        ) AS exp
                        WHERE exp.days IS NOT NULL
                          AND exp.days >= 0
                        ORDER BY exp.days ASC
                        LIMIT 8");
                if ($expiringQuery) {
                    while ($row = $expiringQuery->fetch_assoc()) {
                        $expiringAutoRows[] = [
                            'area' => $row['area'] ?? '',
                            'name' => $row['name'] ?? '',
                            'days' => $row['days'] ?? '',
                        ];
                    }
                }
            } catch (Throwable $e) {
                $expiringAutoRows = [];
            }
        }
        $expiring = $expiringAutoRows;

        $requests = [];
        if (function_exists('recent_requests')) {
            try {
                $requests = recent_requests($db, 8);
            } catch (Throwable $e) {
                $requests = [];
            }
        }

        $history = [];
        if ($includeHistory && function_exists('recent_history_entries')) {
            try {
                $history = recent_history_entries($db, 3);
            } catch (Throwable $e) {
                $history = [];
            }
        }

        // compute active/inactive counts for practicantes
        $prAct = 0;
        $prInact = 0;
        try {
            $resA = $db->query("SELECT status, COUNT(*) AS cnt FROM practicantes GROUP BY status");
            if ($resA) {
                while ($r = $resA->fetch_assoc()) {
                    if (($r['status'] ?? '') === 'inactive') {
                        $prInact = (int)$r['cnt'];
                    } else {
                        $prAct += (int)$r['cnt'];
                    }
                }
            }
        } catch (Throwable $e) {
            // ignore
        }

        return [
            'contacts' => $contacts,
            'contactsText' => $contactsText,
            'birthdays' => $birthdays,
            'birthdayWindowDays' => $birthdayWindowDays,
            'expiring' => $expiring,
            'alerts' => $alerts,
            'requests' => $requests,
            'history' => $history,
            'totals' => [
                'practicantes' => fetchCount($db, 'practicantes'),
                'practicantes_active' => $prAct,
                'practicantes_inactive' => $prInact,
                'relatores' => fetchCount($db, 'relatores'),
                'capacitaciones' => fetchCount($db, 'capacitaciones'),
            ],
        ];
    }
}
