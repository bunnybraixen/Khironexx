<?php

if (!function_exists('create_change_request')) {
    function create_change_request(mysqli $db, $userId, $module, $action, $detail)
    {
        if (!$userId) {
            return false;
        }
        $stmt = $db->prepare("INSERT INTO solicitudes_cambio (user_id, modulo, accion, detalle, status, created_at) VALUES (?, ?, ?, ?, 'pendiente', NOW())");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('isss', $userId, $module, $action, $detail);
        return $stmt->execute();
    }
}

if (!function_exists('pending_requests_over_limit')) {
    function pending_requests_over_limit(mysqli $db, $limit = 3)
    {
        $rows = [];
        $stmt = $db->prepare("SELECT u.full_name, u.email, COUNT(s.id) AS total
            FROM solicitudes_cambio s
            INNER JOIN usuarios u ON u.id = s.user_id
            WHERE s.status = 'pendiente'
            GROUP BY s.user_id
            HAVING COUNT(s.id) > ?");
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

if (!function_exists('recent_requests')) {
    function recent_requests(mysqli $db, $limit = 8)
    {
        $rows = [];
        $stmt = $db->prepare("SELECT s.id, s.user_id, s.modulo, s.accion, s.detalle, s.status, s.created_at, s.resolved_at, u.full_name, u.email
            FROM solicitudes_cambio s
            INNER JOIN usuarios u ON u.id = s.user_id
            ORDER BY s.created_at DESC
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
