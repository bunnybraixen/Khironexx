<?php

if (!function_exists('notificaciones_csrf_token')) {
    function notificaciones_csrf_token()
    {
        if (empty($_SESSION['notificaciones_csrf'])) {
            $_SESSION['notificaciones_csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['notificaciones_csrf'];
    }
}

if (!function_exists('notificaciones_route_allowed')) {
    function notificaciones_route_allowed($route)
    {
        return in_array((string)$route, ['dashboard', 'tareas', 'tickets', 'calendario'], true);
    }
}

if (!function_exists('notificaciones_create')) {
    function notificaciones_create(mysqli $db, $userId, $type, $title, $message, $route, $entityId)
    {
        $userId = (int)$userId;
        $entityId = (int)$entityId;
        $type = trim((string)$type);
        $title = trim((string)$title);
        $message = trim((string)$message);
        $route = (string)$route;
        if ($userId <= 0 || $entityId <= 0 || $type === '' || $title === '' || !notificaciones_route_allowed($route)) {
            return false;
        }
        if (!db_table_exists($db, 'notificaciones')) {
            return false;
        }

        try {
            $stmt = $db->prepare("INSERT IGNORE INTO notificaciones (user_id, tipo, titulo, mensaje, ruta, entity_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())");
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('issssi', $userId, $type, $title, $message, $route, $entityId);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('notificaciones_create_due_task_reminders')) {
    function notificaciones_create_due_task_reminders(mysqli $db, $userId)
    {
        $userId = (int)$userId;
        if ($userId <= 0 || !db_table_exists($db, 'notificaciones') || !db_table_exists($db, 'tareas')) {
            return false;
        }

        try {
            $stmt = $db->prepare("INSERT IGNORE INTO notificaciones (user_id, tipo, titulo, mensaje, ruta, entity_id, created_at)
                SELECT t.asignado_a, 'tarea_vencimiento', 'Tarea próxima a vencer',
                    CONCAT('Vence el ', DATE_FORMAT(t.fecha_limite, '%d/%m/%Y'), ': ', t.titulo),
                    'tareas', t.id, NOW()
                FROM tareas t
                WHERE t.asignado_a = ?
                  AND t.status = 'pendiente'
                  AND t.fecha_limite BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)");
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('i', $userId);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('notificaciones_unread_count')) {
    function notificaciones_unread_count(mysqli $db, $userId)
    {
        if (!db_table_exists($db, 'notificaciones')) {
            return 0;
        }
        $userId = (int)$userId;
        $stmt = $db->prepare('SELECT COUNT(*) FROM notificaciones WHERE user_id = ? AND read_at IS NULL');
        if (!$stmt) {
            return 0;
        }
        $count = 0;
        try {
            $stmt->bind_param('i', $userId);
            if ($stmt->execute()) {
                $stmt->bind_result($count);
                $stmt->fetch();
            }
            $stmt->close();
        } catch (Throwable $e) {
            return 0;
        }
        return (int)$count;
    }
}

if (!function_exists('notificaciones_fetch_latest')) {
    function notificaciones_fetch_latest(mysqli $db, $userId, $limit = 8)
    {
        $rows = [];
        if (!db_table_exists($db, 'notificaciones')) {
            return $rows;
        }
        $userId = (int)$userId;
        $limit = max(1, min(20, (int)$limit));
        $stmt = $db->prepare('SELECT id, tipo, titulo, mensaje, ruta, created_at, read_at FROM notificaciones WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ?');
        if (!$stmt) {
            return $rows;
        }
        try {
            $stmt->bind_param('ii', $userId, $limit);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $rows[] = $row;
                    }
                }
            }
            $stmt->close();
        } catch (Throwable $e) {
            return [];
        }
        return $rows;
    }
}

if (!function_exists('notificaciones_handle_post')) {
    function notificaciones_handle_post(mysqli $db, $userId)
    {
        $userId = (int)$userId;
        $token = (string)($_POST['csrf_token'] ?? '');
        if ($token === '' || !hash_equals(notificaciones_csrf_token(), $token)) {
            return ['error' => 'La sesión del formulario venció. Recarga la página e inténtalo otra vez.'];
        }
        if (!db_table_exists($db, 'notificaciones')) {
            return ['error' => 'La tabla de notificaciones todavía no está instalada.'];
        }

        $action = (string)($_POST['notificacion_action'] ?? '');
        if ($action === 'read_all') {
            $stmt = $db->prepare('UPDATE notificaciones SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL');
            if (!$stmt) {
                return ['error' => 'No se pudieron actualizar las notificaciones.'];
            }
            $stmt->bind_param('i', $userId);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok ? ['success' => true] : ['error' => 'No se pudieron actualizar las notificaciones.'];
        }

        if ($action !== 'open') {
            return ['error' => 'La acción de notificación no es válida.'];
        }
        $notificationId = (int)($_POST['notificacion_id'] ?? 0);
        if ($notificationId <= 0) {
            return ['error' => 'La notificación indicada no es válida.'];
        }

        $stmt = $db->prepare('SELECT ruta FROM notificaciones WHERE id = ? AND user_id = ? LIMIT 1');
        if (!$stmt) {
            return ['error' => 'No se pudo abrir la notificación.'];
        }
        $stmt->bind_param('ii', $notificationId, $userId);
        $route = 'dashboard';
        if ($stmt->execute()) {
            $stmt->bind_result($storedRoute);
            if ($stmt->fetch() && notificaciones_route_allowed($storedRoute)) {
                $route = (string)$storedRoute;
            }
        }
        $stmt->close();

        $update = $db->prepare('UPDATE notificaciones SET read_at = COALESCE(read_at, NOW()) WHERE id = ? AND user_id = ?');
        if (!$update) {
            return ['error' => 'No se pudo actualizar la notificación.'];
        }
        $update->bind_param('ii', $notificationId, $userId);
        $ok = $update->execute();
        $update->close();
        return $ok ? ['redirect' => $route] : ['error' => 'No se pudo actualizar la notificación.'];
    }
}

if (!function_exists('notificaciones_create_for_ticket_admins')) {
    function notificaciones_create_for_ticket_admins(mysqli $db, $ticketId, $actorId, $title, $message, $messageId, $type = 'ticket_respuesta')
    {
        if (!db_table_exists($db, 'notificaciones') || !db_table_exists($db, 'roles') || !db_table_exists($db, 'usuarios_roles')) {
            return false;
        }
        $stmt = $db->prepare("SELECT DISTINCT ur.user_id
            FROM usuarios_roles ur
            INNER JOIN roles r ON r.id = ur.role_id
            INNER JOIN usuarios u ON u.id = ur.user_id
            WHERE r.role_key = 'admin' AND u.status = 'active' AND ur.user_id <> ?");
        if (!$stmt) {
            return false;
        }
        $actorId = (int)$actorId;
        $stmt->bind_param('i', $actorId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    notificaciones_create($db, (int)$row['user_id'], $type, $title, $message, 'tickets', (int)$messageId);
                }
            }
        }
        $stmt->close();
        return true;
    }
}
