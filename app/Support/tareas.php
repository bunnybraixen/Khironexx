<?php

if (!function_exists('tareas_csrf_token')) {
    function tareas_csrf_token()
    {
        if (empty($_SESSION['tareas_csrf'])) {
            $_SESSION['tareas_csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['tareas_csrf'];
    }
}

if (!function_exists('tareas_status_labels')) {
    function tareas_status_labels()
    {
        return [
            'pendiente' => 'Pendiente',
            'completada' => 'Lista',
        ];
    }
}

if (!function_exists('tareas_priority_labels')) {
    function tareas_priority_labels()
    {
        return [
            'baja' => 'Baja',
            'media' => 'Media',
            'alta' => 'Alta',
        ];
    }
}

if (!function_exists('tareas_ensure_schema')) {
    function tareas_ensure_schema(mysqli $db)
    {
        if (db_table_exists($db, 'tareas')) {
            return true;
        }

        $sql = "CREATE TABLE IF NOT EXISTS tareas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            titulo VARCHAR(160) NOT NULL,
            descripcion TEXT DEFAULT NULL,
            prioridad VARCHAR(20) NOT NULL DEFAULT 'media',
            fecha_limite DATE DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pendiente',
            creado_por INT NOT NULL,
            asignado_a INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME DEFAULT NULL,
            INDEX idx_tareas_status_created (status, created_at),
            INDEX idx_tareas_asignado_status (asignado_a, status),
            CONSTRAINT fk_tareas_creador FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE CASCADE,
            CONSTRAINT fk_tareas_asignado FOREIGN KEY (asignado_a) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        try {
            return (bool)$db->query($sql);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('tareas_fetch_users')) {
    function tareas_fetch_users(mysqli $db, $excludeUserId = 0)
    {
        $rows = [];
        try {
            $result = $db->query("SELECT id, full_name, email FROM usuarios WHERE status = 'active' ORDER BY full_name ASC");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    if ((int)($row['id'] ?? 0) === (int)$excludeUserId) {
                        continue;
                    }
                    $rows[] = $row;
                }
            }
        } catch (Throwable $e) {
            $rows = [];
        }
        return $rows;
    }
}

if (!function_exists('tareas_fetch_one')) {
    function tareas_fetch_one(mysqli $db, $tareaId)
    {
        $tareaId = (int)$tareaId;
        if ($tareaId <= 0) {
            return null;
        }
        try {
            $stmt = $db->prepare("SELECT id, titulo, prioridad, fecha_limite, status, creado_por, asignado_a, created_at, completed_at FROM tareas WHERE id = ? LIMIT 1");
            if (!$stmt) {
                return null;
            }
            $stmt->bind_param('i', $tareaId);
            if (!$stmt->execute()) {
                $stmt->close();
                return null;
            }
            $row = null;
            if (method_exists($stmt, 'get_result')) {
                $result = $stmt->get_result();
                if ($result) {
                    $row = $result->fetch_assoc();
                }
            }
            $stmt->close();
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('tareas_handle_post')) {
    function tareas_handle_post(mysqli $db, array $user, $canAssign, $isAdmin)
    {
        $token = (string)($_POST['csrf_token'] ?? '');
        if ($token === '' || !hash_equals(tareas_csrf_token(), $token)) {
            return ['error' => 'La sesión del formulario venció. Recarga la página e inténtalo otra vez.'];
        }

        if (!tareas_ensure_schema($db)) {
            return ['error' => 'No se pudo preparar la tabla de tareas en la base de datos.'];
        }

        $action = (string)($_POST['tarea_action'] ?? '');
        $currentUserId = (int)($user['id'] ?? 0);

        if ($action === 'create') {
            if (!$canAssign) {
                return ['error' => 'No tienes permisos para asignar tareas.'];
            }

            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $descripcion = trim((string)($_POST['descripcion'] ?? ''));
            $prioridad = trim((string)($_POST['prioridad'] ?? 'media'));
            $asignadoA = (int)($_POST['asignado_a'] ?? 0);
            $fechaLimite = trim((string)($_POST['fecha_limite'] ?? ''));

            if ($titulo === '' || mb_strlen($titulo) > 160) {
                return ['error' => 'Indica un título de hasta 160 caracteres.'];
            }
            if (mb_strlen($descripcion) > 5000) {
                return ['error' => 'La descripción admite hasta 5.000 caracteres.'];
            }
            if (!in_array($prioridad, ['baja', 'media', 'alta'], true)) {
                $prioridad = 'media';
            }
            if ($asignadoA <= 0) {
                return ['error' => 'Selecciona el usuario responsable de la tarea.'];
            }

            $assigneeOk = false;
            try {
                $stmt = $db->prepare("SELECT id FROM usuarios WHERE id = ? AND status = 'active' LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('i', $asignadoA);
                    if ($stmt->execute()) {
                        $assigneeOk = (bool)$stmt->get_result()->fetch_assoc();
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $assigneeOk = false;
            }
            if (!$assigneeOk) {
                return ['error' => 'El usuario seleccionado no existe o no está activo.'];
            }

            if ($fechaLimite !== '') {
                $dateCheck = DateTime::createFromFormat('Y-m-d', $fechaLimite);
                if (!$dateCheck || $dateCheck->format('Y-m-d') !== $fechaLimite) {
                    return ['error' => 'La fecha límite debe tener el formato AAAA-MM-DD.'];
                }
            } else {
                $fechaLimite = null;
            }

            try {
                $stmt = $db->prepare("INSERT INTO tareas (titulo, descripcion, prioridad, fecha_limite, status, creado_por, asignado_a, created_at) VALUES (?, ?, ?, ?, 'pendiente', ?, ?, NOW())");
                if (!$stmt) {
                    return ['error' => 'No se pudo preparar el registro de la tarea.'];
                }
                $stmt->bind_param('ssssii', $titulo, $descripcion, $prioridad, $fechaLimite, $currentUserId, $asignadoA);
                if (!$stmt->execute()) {
                    $stmt->close();
                    return ['error' => 'No se pudo guardar la tarea. Inténtalo nuevamente.'];
                }
                $stmt->close();
            } catch (Throwable $e) {
                return ['error' => 'No se pudo guardar la tarea. Inténtalo nuevamente.'];
            }

            return ['success' => 'Tarea asignada correctamente.'];
        }

        $tareaId = (int)($_POST['tarea_id'] ?? 0);
        $tarea = tareas_fetch_one($db, $tareaId);
        if (!$tarea) {
            return ['error' => 'La tarea indicada ya no existe.'];
        }

        $isOwner = (int)$tarea['creado_por'] === $currentUserId;
        $isAssignee = (int)$tarea['asignado_a'] === $currentUserId;

        if ($action === 'complete') {
            if (!$isAssignee && !$isOwner && !$isAdmin) {
                return ['error' => 'No puedes marcar esta tarea como lista.'];
            }
            $stmt = $db->prepare("UPDATE tareas SET status = 'completada', completed_at = NOW() WHERE id = ?");
            if (!$stmt) {
                return ['error' => 'No se pudo preparar la actualización de la tarea.'];
            }
            $stmt->bind_param('i', $tareaId);
            if (!$stmt->execute()) {
                $stmt->close();
                return ['error' => 'No se pudo actualizar la tarea.'];
            }
            $stmt->close();
            return ['success' => 'Tarea marcada como lista. Quedó archivada.'];
        }

        if ($action === 'reopen') {
            if (!$isOwner && !$isAdmin) {
                return ['error' => 'No puedes reabrir esta tarea.'];
            }
            $stmt = $db->prepare("UPDATE tareas SET status = 'pendiente', completed_at = NULL WHERE id = ?");
            if (!$stmt) {
                return ['error' => 'No se pudo preparar la actualización de la tarea.'];
            }
            $stmt->bind_param('i', $tareaId);
            if (!$stmt->execute()) {
                $stmt->close();
                return ['error' => 'No se pudo actualizar la tarea.'];
            }
            $stmt->close();
            return ['success' => 'Tarea reabierta.'];
        }

        if ($action === 'delete') {
            if (!$isOwner && !$isAdmin) {
                return ['error' => 'No puedes eliminar esta tarea.'];
            }
            $stmt = $db->prepare("DELETE FROM tareas WHERE id = ?");
            if (!$stmt) {
                return ['error' => 'No se pudo preparar la eliminación de la tarea.'];
            }
            $stmt->bind_param('i', $tareaId);
            if (!$stmt->execute()) {
                $stmt->close();
                return ['error' => 'No se pudo eliminar la tarea.'];
            }
            $stmt->close();
            return ['success' => 'Tarea eliminada.'];
        }

        return ['error' => 'Acción no reconocida.'];
    }
}


if (!function_exists('tareas_fetch_for_user')) {
    function tareas_fetch_for_user(mysqli $db, $userId, $canAssign, array $filters = [])
    {
        $rows = [];
        if (!tareas_ensure_schema($db)) {
            return $rows;
        }

        $conditions = [];
        $params = [];
        $types = '';

        $responsable = (int)($filters['responsable'] ?? 0);
        if ($canAssign && $responsable > 0) {
            $conditions[] = 't.asignado_a = ?';
            $params[] = $responsable;
            $types .= 'i';
        } elseif ((int)$userId > 0) {
            $conditions[] = '(t.asignado_a = ? OR t.creado_por = ?)';
            $params[] = (int)$userId;
            $params[] = (int)$userId;
            $types .= 'ii';
        } else {
            return $rows;
        }

        $status = trim((string)($filters['status'] ?? ''));
        if (in_array($status, ['pendiente', 'completada'], true)) {
            $conditions[] = 't.status = ?';
            $params[] = $status;
            $types .= 's';
        }

        $prioridad = trim((string)($filters['prioridad'] ?? ''));
        if (in_array($prioridad, ['baja', 'media', 'alta'], true)) {
            $conditions[] = 't.prioridad = ?';
            $params[] = $prioridad;
            $types .= 's';
        }

        $search = trim((string)($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            $conditions[] = '(t.titulo LIKE ? OR t.descripcion LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $types .= 'ss';
        }

        $sql = "SELECT t.*, a.full_name AS asignado_a_nombre, c.full_name AS creado_por_nombre
                FROM tareas t
                INNER JOIN usuarios a ON a.id = t.asignado_a
                LEFT JOIN usuarios c ON c.id = t.creado_por";
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= " ORDER BY CASE WHEN t.status = 'pendiente' THEN 0 ELSE 1 END, t.fecha_limite IS NULL, t.fecha_limite ASC, t.created_at DESC";

        try {
            $stmt = $db->prepare($sql);
            if ($stmt) {
                if ($params) {
                    $stmt->bind_param($types, ...$params);
                }
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            $rows[] = $row;
                        }
                    }
                }
                $stmt->close();
            }
        } catch (Throwable $e) {
            $rows = [];
        }

        return $rows;
    }
}



if (!function_exists('tareas_pending_summary')) {
    function tareas_pending_summary(mysqli $db, $userId, $limit = 5)
    {
        $summary = ['rows' => [], 'total' => 0];
        $userId = (int)$userId;
        if ($userId <= 0 || !tareas_ensure_schema($db)) {
            return $summary;
        }

        // El usuario ve sus tareas pendientes y las que él mismo asignó.
        $condition = '(t.asignado_a = ? OR t.creado_por = ?)';
        $conditionParams = [$userId, $userId];
        $conditionTypes = 'ii';

        try {
            $stmt = $db->prepare("SELECT COUNT(*) AS total FROM tareas t WHERE " . $condition . " AND t.status = 'pendiente'");
            if ($stmt) {
                $stmt->bind_param($conditionTypes, ...$conditionParams);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    if ($result) {
                        $row = $result->fetch_assoc();
                        $summary['total'] = (int)($row['total'] ?? 0);
                    }
                }
                $stmt->close();
            }

            $limit = max(1, min(20, (int)$limit));
            $stmt = $db->prepare("SELECT t.id, t.titulo, t.prioridad, t.fecha_limite, t.created_at,
                    a.full_name AS asignado_a_nombre, c.full_name AS creado_por_nombre
                FROM tareas t
                INNER JOIN usuarios a ON a.id = t.asignado_a
                LEFT JOIN usuarios c ON c.id = t.creado_por
                WHERE " . $condition . " AND t.status = 'pendiente'
                ORDER BY t.fecha_limite IS NULL, t.fecha_limite ASC, t.created_at DESC
                LIMIT ?");
            if ($stmt) {
                $stmt->bind_param($conditionTypes . 'i', ...array_merge($conditionParams, [$limit]));
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            $summary['rows'][] = $row;
                        }
                    }
                }
                $stmt->close();
            }
        } catch (Throwable $e) {
            $summary = ['rows' => [], 'total' => 0];
        }

        return $summary;
    }
}

