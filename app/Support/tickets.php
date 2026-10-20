<?php

if (!function_exists('tickets_csrf_token')) {
    function tickets_csrf_token()
    {
        if (empty($_SESSION['tickets_csrf'])) {
            $_SESSION['tickets_csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['tickets_csrf'];
    }
}

if (!function_exists('tickets_handle_post')) {
    function tickets_handle_post(mysqli $db, array $user, $isAdmin)
    {
        $token = (string)($_POST['csrf_token'] ?? '');
        if ($token === '' || !hash_equals(tickets_csrf_token(), $token)) {
            return ['error' => 'La sesión del formulario venció. Recarga la página e inténtalo otra vez.'];
        }

        $action = (string)($_POST['ticket_action'] ?? '');
        if ($action === 'create') {
            $subject = trim((string)($_POST['asunto'] ?? ''));
            $module = trim((string)($_POST['modulo'] ?? ''));
            $priority = trim((string)($_POST['prioridad'] ?? 'media'));
            $description = trim((string)($_POST['descripcion'] ?? ''));
            $allowedModules = ['postulantes', 'practicantes', 'relatores', 'capacitaciones', 'informes', 'acceso', 'otro'];
            $allowedPriorities = ['baja', 'media', 'alta'];

            if ($subject === '' || mb_strlen($subject) > 160 || $description === '' || mb_strlen($description) > 10000) {
                return ['error' => 'Completa el asunto y la descripción. El asunto admite hasta 160 caracteres y la descripción hasta 10.000.'];
            }
            if (!in_array($module, $allowedModules, true) || !in_array($priority, $allowedPriorities, true)) {
                return ['error' => 'Selecciona un módulo y una prioridad válidos.'];
            }

            $stmt = $db->prepare("INSERT INTO tickets_soporte (user_id, asunto, modulo, prioridad, descripcion, status, created_at) VALUES (?, ?, ?, ?, ?, 'abierto', NOW())");
            if (!$stmt) {
                return ['error' => 'No se pudo preparar el registro del ticket.'];
            }
            $userId = (int)($user['id'] ?? 0);
            $stmt->bind_param('issss', $userId, $subject, $module, $priority, $description);
            if (!$stmt->execute()) {
                return ['error' => 'No se pudo guardar el ticket. Inténtalo nuevamente.'];
            }
            return ['success' => 'Ticket registrado correctamente.'];
        }

        if ($action === 'update' && $isAdmin) {
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            $status = trim((string)($_POST['status'] ?? ''));
            if ($ticketId <= 0 || !in_array($status, ['abierto', 'en_revision', 'resuelto', 'cerrado'], true)) {
                return ['error' => 'El ticket o el estado seleccionado no son válidos.'];
            }

            $resolvedAt = in_array($status, ['resuelto', 'cerrado'], true) ? date('Y-m-d H:i:s') : null;
            $stmt = $db->prepare("UPDATE tickets_soporte SET status = ?, resolved_at = ? WHERE id = ?");
            if (!$stmt) {
                return ['error' => 'No se pudo preparar la actualización del ticket.'];
            }
            $stmt->bind_param('ssi', $status, $resolvedAt, $ticketId);
            if (!$stmt->execute() || $stmt->affected_rows < 0) {
                return ['error' => 'No se pudo actualizar el ticket.'];
            }
            return ['success' => 'Seguimiento del ticket actualizado.'];
        }

        if ($action === 'delete' && $isAdmin) {
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            if ($ticketId <= 0) {
                return ['error' => 'Selecciona un ticket válido para eliminar.'];
            }

            try {
                $db->begin_transaction();
                $ticketStmt = $db->prepare('SELECT status FROM tickets_soporte WHERE id = ? FOR UPDATE');
                if (!$ticketStmt) {
                    $db->rollback();
                    return ['error' => 'No se pudo verificar el ticket.'];
                }
                $ticketStmt->bind_param('i', $ticketId);
                if (!$ticketStmt->execute()) {
                    $ticketStmt->close();
                    $db->rollback();
                    return ['error' => 'No se pudo verificar el ticket.'];
                }
                $ticketResult = $ticketStmt->get_result();
                $ticketRow = $ticketResult ? $ticketResult->fetch_assoc() : null;
                $ticketStmt->close();

                if (!$ticketRow || !in_array((string)$ticketRow['status'], ['resuelto', 'cerrado'], true)) {
                    $db->rollback();
                    return ['error' => 'Solo se pueden eliminar tickets resueltos o cerrados.'];
                }

                // Elimina primero la conversación para funcionar también si la FK no tiene ON DELETE CASCADE.
                if (function_exists('db_table_exists') && db_table_exists($db, 'tickets_mensajes')) {
                    $messagesStmt = $db->prepare('DELETE FROM tickets_mensajes WHERE ticket_id = ?');
                    if (!$messagesStmt) {
                        $db->rollback();
                        return ['error' => 'No se pudo preparar la eliminación de los mensajes del ticket.'];
                    }
                    $messagesStmt->bind_param('i', $ticketId);
                    if (!$messagesStmt->execute()) {
                        $messagesStmt->close();
                        $db->rollback();
                        return ['error' => 'No se pudieron eliminar los mensajes del ticket.'];
                    }
                    $messagesStmt->close();
                }

                $deleteStmt = $db->prepare('DELETE FROM tickets_soporte WHERE id = ?');
                if (!$deleteStmt) {
                    $db->rollback();
                    return ['error' => 'No se pudo preparar la eliminación del ticket.'];
                }
                $deleteStmt->bind_param('i', $ticketId);
                if (!$deleteStmt->execute() || $deleteStmt->affected_rows !== 1) {
                    $deleteStmt->close();
                    $db->rollback();
                    return ['error' => 'No se pudo eliminar el ticket.'];
                }
                $deleteStmt->close();
                $db->commit();
                return ['success' => 'Ticket resuelto eliminado junto con su historial de mensajes.'];
            } catch (Throwable $e) {
                try {
                    $db->rollback();
                } catch (Throwable $ignored) {
                }
                return ['error' => 'Ocurrió un problema al eliminar el ticket. No se guardaron cambios.'];
            }
        }

        if ($action === 'comment') {
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            $message = trim((string)($_POST['mensaje'] ?? ''));
            $userId = (int)($user['id'] ?? 0);
            $messageLength = function_exists('mb_strlen') ? mb_strlen($message) : strlen($message);
            if ($ticketId <= 0 || $message === '' || $messageLength > 4000) {
                return ['error' => 'Escribe un mensaje de hasta 4.000 caracteres.'];
            }

            $ownerStmt = $db->prepare('SELECT user_id FROM tickets_soporte WHERE id = ?');
            if (!$ownerStmt) {
                return ['error' => 'No se pudo verificar el ticket.'];
            }
            $ownerStmt->bind_param('i', $ticketId);
            if (!$ownerStmt->execute()) {
                return ['error' => 'No se pudo verificar el ticket.'];
            }
            $ownerRow = $ownerStmt->get_result()->fetch_assoc();
            if (!$ownerRow || (!$isAdmin && (int)$ownerRow['user_id'] !== $userId)) {
                return ['error' => 'No tienes permiso para responder este ticket.'];
            }

            $stmt = $db->prepare('INSERT INTO tickets_mensajes (ticket_id, user_id, mensaje, created_at) VALUES (?, ?, ?, NOW())');
            if (!$stmt) {
                return ['error' => 'No se pudo preparar el mensaje.'];
            }
            $stmt->bind_param('iis', $ticketId, $userId, $message);
            if (!$stmt->execute()) {
                return ['error' => 'No se pudo guardar el mensaje.'];
            }
            $touchStmt = $db->prepare('UPDATE tickets_soporte SET updated_at = NOW() WHERE id = ?');
            if ($touchStmt) {
                $touchStmt->bind_param('i', $ticketId);
                $touchStmt->execute();
            }
            return ['success' => 'Mensaje agregado a la conversación.'];
        }

        return ['error' => 'No tienes permiso para realizar esa acción.'];
    }
}

if (!function_exists('tickets_fetch_messages')) {
    function tickets_fetch_messages(mysqli $db, array $tickets)
    {
        $messagesByTicket = [];
        $ticketIds = [];
        foreach ($tickets as $ticket) {
            $ticketId = (int)($ticket['id'] ?? 0);
            if ($ticketId > 0) {
                $ticketIds[] = $ticketId;
            }
        }
        $ticketIds = array_values(array_unique($ticketIds));
        if (!$ticketIds) {
            return $messagesByTicket;
        }

        $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
        $stmt = $db->prepare("SELECT m.ticket_id, m.user_id, m.mensaje, m.created_at, u.full_name AS author_name
            FROM tickets_mensajes m
            INNER JOIN usuarios u ON u.id = m.user_id
            WHERE m.ticket_id IN ($placeholders)
            ORDER BY m.created_at ASC, m.id ASC");
        if (!$stmt) {
            return $messagesByTicket;
        }
        $types = str_repeat('i', count($ticketIds));
        $stmt->bind_param($types, ...$ticketIds);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $messagesByTicket[(int)$row['ticket_id']][] = $row;
            }
        }
        return $messagesByTicket;
    }
}

if (!function_exists('tickets_fetch_for_user')) {
    function tickets_fetch_for_user(mysqli $db, $userId, $isAdmin, array $filters = [])
    {
        $rows = [];

        $conditions = [];
        $params = [];
        $types = '';
        if (!$isAdmin) {
            $conditions[] = 't.user_id = ?';
            $params[] = (int)$userId;
            $types .= 'i';
        }

        $allowedStatuses = ['abierto', 'en_revision', 'resuelto', 'cerrado'];
        $allowedPriorities = ['baja', 'media', 'alta'];
        $allowedModules = ['postulantes', 'practicantes', 'relatores', 'capacitaciones', 'informes', 'acceso', 'otro'];
        foreach ([
            'status' => ['t.status', $allowedStatuses],
            'prioridad' => ['t.prioridad', $allowedPriorities],
            'modulo' => ['t.modulo', $allowedModules],
        ] as $filterKey => [$column, $allowedValues]) {
            $value = trim((string)($filters[$filterKey] ?? ''));
            if ($value !== '' && in_array($value, $allowedValues, true)) {
                $conditions[] = $column . ' = ?';
                $params[] = $value;
                $types .= 's';
            }
        }

        $search = trim((string)($filters['q'] ?? ''));
        if ($search !== '') {
            if (preg_match('/^TK-?0*(\d+)$/i', str_replace(' ', '', $search), $matches)) {
                $conditions[] = 't.id = ?';
                $params[] = (int)$matches[1];
                $types .= 'i';
            } else {
                $like = '%' . $search . '%';
                $searchColumns = ['t.asunto LIKE ?', 't.descripcion LIKE ?', 't.modulo LIKE ?', 't.prioridad LIKE ?', 't.status LIKE ?'];
                for ($index = 0; $index < count($searchColumns); $index++) {
                    $params[] = $like;
                    $types .= 's';
                }
                if ($isAdmin) {
                    $searchColumns[] = 'u.full_name LIKE ?';
                    $params[] = $like;
                    $types .= 's';
                }
                $conditions[] = '(' . implode(' OR ', $searchColumns) . ')';
            }
        }

        $sql = "SELECT t.*, u.full_name AS reporter_name FROM tickets_soporte t INNER JOIN usuarios u ON u.id = t.user_id";
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY t.created_at DESC';
        $stmt = $db->prepare($sql);
        if ($stmt && $params) {
            $stmt->bind_param($types, ...$params);
        }
        if ($stmt && $stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}

