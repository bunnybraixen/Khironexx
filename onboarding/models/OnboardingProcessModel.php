<?php

class OnboardingModel
{
    private $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public function isStorageReady()
    {
        if (function_exists('tareas_ensure_schema')) {
            tareas_ensure_schema($this->db);
        }
        foreach (['onboarding_processes', 'onboarding_phases', 'onboarding_phase_templates', 'onboarding_task_templates', 'onboarding_phase_tasks', 'onboarding_history', 'tareas'] as $table) {
            if (!db_table_exists($this->db, $table)) {
                return false;
            }
        }
        return true;
    }

    public function fetchPracticantes()
    {
        $rows = [];
        if (!db_table_exists($this->db, 'practicantes')) {
            return $rows;
        }
        $columns = db_table_columns($this->db, 'practicantes');
        $name = $this->firstColumn($columns, ['full_name', 'nombre', 'name']);
        if ($name === '') {
            return $rows;
        }
        $projection = $this->practicanteProjection();
        $active = in_array('status', $columns, true) ? " WHERE status = 'active'" : '';
        try {
            $result = $this->db->query('SELECT * FROM (' . $projection . ') p' . $active . ' ORDER BY p.full_name');
            while ($result && ($row = $result->fetch_assoc())) {
                $rows[] = $row;
            }
        } catch (Throwable $e) {
            return [];
        }
        return $rows;
    }

    public function fetchUsuarios()
    {
        $rows = [];
        if (!db_table_exists($this->db, 'usuarios')) {
            return $rows;
        }
        try {
            $result = $this->db->query("SELECT id, full_name FROM usuarios WHERE status = 'active' ORDER BY full_name");
            while ($result && ($row = $result->fetch_assoc())) {
                $rows[] = $row;
            }
        } catch (Throwable $e) {
            return [];
        }
        return $rows;
    }

    public function fetchProcesses(array $filters = [])
    {
        $this->refreshOpenStatuses();
        $conditions = [];
        $params = [];
        $types = '';
        $query = trim((string)($filters['q'] ?? ''));
        if ($query !== '') {
            $conditions[] = '(p.full_name LIKE ? OR p.rut LIKE ?)';
            $like = '%' . $query . '%';
            $params[] = $like;
            $params[] = $like;
            $types .= 'ss';
        }
        if (in_array(($filters['status'] ?? ''), ['in_progress', 'ready_to_close', 'completed', 'cancelled'], true)) {
            $conditions[] = 'op.status = ?';
            $params[] = $filters['status'];
            $types .= 's';
        }
        if ((int)($filters['responsible'] ?? 0) > 0) {
            $conditions[] = 'op.responsible_user_id = ?';
            $params[] = (int)$filters['responsible'];
            $types .= 'i';
        }
        foreach (['start_from' => ['op.start_date', '>='], 'start_to' => ['op.start_date', '<='], 'end_from' => ['op.estimated_end_date', '>='], 'end_to' => ['op.estimated_end_date', '<=']] as $key => $rule) {
            $value = (string)($filters[$key] ?? '');
            if ($this->validDate($value)) {
                $conditions[] = $rule[0] . ' ' . $rule[1] . ' ?';
                $params[] = $value;
                $types .= 's';
            }
        }
        $sql = "SELECT op.*, p.full_name AS practicante_name, p.rut, p.career,
                ru.full_name AS responsible_name, su.full_name AS supervisor_name,
                bu.full_name AS buddy_name, au.full_name AS backup_buddy_name,
                CASE WHEN COALESCE(pr.required_total, 0) = 0 THEN 0 ELSE ROUND(100 * pr.required_done / pr.required_total) END AS progress_percent
            FROM onboarding_processes op INNER JOIN (" . $this->practicanteProjection() . ") p ON p.id = op.practicante_id
            INNER JOIN usuarios ru ON ru.id = op.responsible_user_id INNER JOIN usuarios su ON su.id = op.supervisor_user_id
            LEFT JOIN usuarios bu ON bu.id = op.buddy_user_id LEFT JOIN usuarios au ON au.id = op.backup_buddy_user_id
            LEFT JOIN (SELECT ph.process_id, SUM(l.is_required = 1) AS required_total,
                SUM(l.is_required = 1 AND t.status = 'completada') AS required_done
                FROM onboarding_phases ph LEFT JOIN onboarding_phase_tasks l ON l.phase_id = ph.id
                LEFT JOIN tareas t ON t.id = l.task_id GROUP BY ph.process_id) pr ON pr.process_id = op.id";
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= " ORDER BY CASE op.status WHEN 'in_progress' THEN 0 WHEN 'ready_to_close' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END, op.start_date DESC, op.id DESC";
        $rows = [];
        try {
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return $rows;
            }
            if ($params) {
                $stmt->bind_param($types, ...$params);
            }
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
            }
            $stmt->close();
        } catch (Throwable $e) {
            return [];
        }
        return $rows;
    }

    public function fetchProcess($id)
    {
        $id = (int)$id;
        if ($id <= 0) {
            return null;
        }
        $this->refreshOpenStatuses();
        $sql = "SELECT op.*, p.full_name AS practicante_name, p.rut, p.email AS practicante_email,
                p.career, p.status AS practicante_status, p.current_start_date, p.current_end_date,
                ru.full_name AS responsible_name, su.full_name AS supervisor_name,
                bu.full_name AS buddy_name, au.full_name AS backup_buddy_name,
                CASE WHEN COALESCE(pr.required_total, 0) = 0 THEN 0 ELSE ROUND(100 * pr.required_done / pr.required_total) END AS progress_percent
            FROM onboarding_processes op INNER JOIN (" . $this->practicanteProjection() . ") p ON p.id = op.practicante_id
            INNER JOIN usuarios ru ON ru.id = op.responsible_user_id INNER JOIN usuarios su ON su.id = op.supervisor_user_id
            LEFT JOIN usuarios bu ON bu.id = op.buddy_user_id LEFT JOIN usuarios au ON au.id = op.backup_buddy_user_id
            LEFT JOIN (SELECT ph.process_id, SUM(l.is_required = 1) AS required_total,
                SUM(l.is_required = 1 AND t.status = 'completada') AS required_done
                FROM onboarding_phases ph LEFT JOIN onboarding_phase_tasks l ON l.phase_id = ph.id
                LEFT JOIN tareas t ON t.id = l.task_id GROUP BY ph.process_id) pr ON pr.process_id = op.id
            WHERE op.id = ? LIMIT 1";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    public function fetchPhases($processId)
    {
        $phases = [];
        $stmt = $this->db->prepare('SELECT * FROM onboarding_phases WHERE process_id = ? ORDER BY phase_order');
        $processId = (int)$processId;
        $stmt->bind_param('i', $processId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($phase = $result->fetch_assoc()) {
                $phase['tasks'] = [];
                $phases[(int)$phase['id']] = $phase;
            }
        }
        $stmt->close();
        if (!$phases) {
            return [];
        }
        $ids = array_keys($phases);
        $stmt = $this->db->prepare("SELECT l.phase_id, l.is_required, t.id, t.titulo, t.descripcion, t.prioridad, t.fecha_limite,
                t.status, t.created_at, t.completed_at, t.asignado_a, u.full_name AS assignee_name
            FROM onboarding_phase_tasks l INNER JOIN tareas t ON t.id = l.task_id INNER JOIN usuarios u ON u.id = t.asignado_a
            WHERE l.phase_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ") ORDER BY t.fecha_limite IS NULL, t.fecha_limite, t.id");
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($task = $result->fetch_assoc()) {
                $task['overdue'] = $task['status'] === 'pendiente' && !empty($task['fecha_limite']) && $task['fecha_limite'] < date('Y-m-d');
                $phases[(int)$task['phase_id']]['tasks'][] = $task;
            }
        }
        $stmt->close();
        return array_values($phases);
    }

    public function fetchTaskSummary($processId)
    {
        $summary = ['pending' => 0, 'completed' => 0, 'blocked' => 0, 'overdue' => 0, 'total' => 0];
        $stmt = $this->db->prepare("SELECT COUNT(*) AS total, SUM(t.status = 'completada') AS completed,
            SUM(t.status = 'bloqueada') AS blocked,
            SUM(t.status = 'pendiente' AND t.fecha_limite < CURDATE()) AS overdue,
            SUM(t.status = 'pendiente' AND (t.fecha_limite IS NULL OR t.fecha_limite >= CURDATE())) AS pending
            FROM onboarding_phases ph INNER JOIN onboarding_phase_tasks l ON l.phase_id = ph.id
            INNER JOIN tareas t ON t.id = l.task_id WHERE ph.process_id = ?");
        $processId = (int)$processId;
        $stmt->bind_param('i', $processId);
        if ($stmt->execute()) {
            $summary = array_merge($summary, $stmt->get_result()->fetch_assoc() ?: []);
        }
        $stmt->close();
        foreach ($summary as $key => $value) {
            $summary[$key] = (int)$value;
        }
        return $summary;
    }

    public function fetchHistory($processId)
    {
        $rows = [];
        $stmt = $this->db->prepare('SELECT h.*, u.full_name AS user_name FROM onboarding_history h LEFT JOIN usuarios u ON u.id = h.user_id WHERE h.process_id = ? ORDER BY h.created_at DESC, h.id DESC LIMIT 100');
        $processId = (int)$processId;
        $stmt->bind_param('i', $processId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        $stmt->close();
        return $rows;
    }

    public function createProcess(array $input, $actorId)
    {
        $data = $this->validatePayload($input, true);
        if (isset($data['error'])) {
            return $data;
        }
        if (!$this->isStorageReady() || !$this->validUser((int)$actorId)) {
            return ['error' => 'No está disponible el almacenamiento requerido o el usuario de sesión ya no está activo.'];
        }
        $actorId = (int)$actorId;
        $this->db->begin_transaction();
        try {
            $stmt = $this->db->prepare("INSERT INTO onboarding_processes (practicante_id, start_date, estimated_end_date, modality, responsible_user_id, supervisor_user_id, buddy_user_id, backup_buddy_user_id, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'in_progress', ?)");
            $stmt->bind_param('isssiiiii', $data['practicante_id'], $data['start_date'], $data['estimated_end_date'], $data['modality'], $data['responsible_user_id'], $data['supervisor_user_id'], $data['buddy_user_id'], $data['backup_buddy_user_id'], $actorId);
            if (!$stmt->execute()) {
                if ($stmt->errno === 1062) {
                    throw new RuntimeException('duplicate_active');
                }
                throw new RuntimeException('insert_process');
            }
            $processId = (int)$this->db->insert_id;
            $stmt->close();
            $this->generatePhasesAndTasks($processId, $data, $actorId);
            $this->recordHistory($processId, $actorId, 'created', 'Se creó el proceso con siete fases y sus tareas iniciales.', null, $data);
            $this->db->commit();
            return ['success' => 'Proceso creado con sus siete fases y tareas.', 'id' => $processId];
        } catch (Throwable $e) {
            $this->db->rollback();
            return ['error' => $e->getMessage() === 'duplicate_active' || (int)$e->getCode() === 1062
                ? 'Ya existe un proceso activo para este practicante.'
                : 'No se pudo crear el proceso. Verifica los datos e inténtalo nuevamente.'];
        }
    }

    public function updateProcess($id, array $input, $actorId)
    {
        $id = (int)$id;
        $current = $this->fetchProcess($id);
        if (!$current || !in_array($current['status'], ['in_progress', 'ready_to_close'], true)) {
            return ['error' => 'Solo se pueden editar procesos activos.'];
        }
        $input['practicante_id'] = $current['practicante_id'];
        $data = $this->validatePayload($input, false);
        if (isset($data['error'])) {
            return $data;
        }
        $this->db->begin_transaction();
        try {
            $stmt = $this->db->prepare("UPDATE onboarding_processes SET start_date = ?, estimated_end_date = ?, modality = ?, responsible_user_id = ?, supervisor_user_id = ?, buddy_user_id = ?, backup_buddy_user_id = ? WHERE id = ? AND status IN ('in_progress', 'ready_to_close')");
            $stmt->bind_param('sssiiiii', $data['start_date'], $data['estimated_end_date'], $data['modality'], $data['responsible_user_id'], $data['supervisor_user_id'], $data['buddy_user_id'], $data['backup_buddy_user_id'], $id);
            if (!$stmt->execute()) {
                throw new RuntimeException('update');
            }
            $stmt->close();
            $stmt = $this->db->prepare("UPDATE tareas t INNER JOIN onboarding_phase_tasks l ON l.task_id = t.id INNER JOIN onboarding_phases ph ON ph.id = l.phase_id INNER JOIN onboarding_task_templates tt ON tt.id = l.template_task_id INNER JOIN onboarding_processes op ON op.id = ph.process_id SET t.asignado_a = CASE tt.assignee_key WHEN 'supervisor' THEN op.supervisor_user_id WHEN 'buddy' THEN COALESCE(op.buddy_user_id, op.responsible_user_id) WHEN 'backup_buddy' THEN COALESCE(op.backup_buddy_user_id, op.responsible_user_id) ELSE op.responsible_user_id END WHERE op.id = ? AND t.status IN ('pendiente', 'bloqueada')");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $stmt = $this->db->prepare('UPDATE onboarding_phases ph INNER JOIN onboarding_phase_templates pt ON pt.id = ph.template_id SET ph.start_date = DATE_ADD(?, INTERVAL pt.start_offset_days DAY), ph.end_date = DATE_ADD(?, INTERVAL pt.end_offset_days DAY) WHERE ph.process_id = ?');
            $stmt->bind_param('ssi', $data['start_date'], $data['start_date'], $id);
            $stmt->execute();
            $stmt->close();
            $stmt = $this->db->prepare("UPDATE tareas t INNER JOIN onboarding_phase_tasks l ON l.task_id = t.id INNER JOIN onboarding_phases ph ON ph.id = l.phase_id INNER JOIN onboarding_task_templates tt ON tt.id = l.template_task_id SET t.fecha_limite = DATE_ADD(?, INTERVAL tt.due_offset_days DAY) WHERE ph.process_id = ? AND t.status IN ('pendiente', 'bloqueada')");
            $stmt->bind_param('si', $data['start_date'], $id);
            $stmt->execute();
            $stmt->close();
            $this->recordHistory($id, (int)$actorId, 'updated', 'Se actualizaron fechas, modalidad o responsables.', $this->snapshot($current), $data);
            $this->db->commit();
            return ['success' => 'Proceso actualizado correctamente.'];
        } catch (Throwable $e) {
            $this->db->rollback();
            return ['error' => 'No se pudo actualizar el proceso.'];
        }
    }

    public function cancelProcess($id, $reason, $actorId)
    {
        $id = (int)$id;
        $reason = trim((string)$reason);
        if (strlen($reason) < 5 || strlen($reason) > 2000) {
            return ['error' => 'El motivo de cancelación debe tener entre 5 y 2.000 caracteres.'];
        }
        $current = $this->fetchProcess($id);
        if (!$current || !in_array($current['status'], ['in_progress', 'ready_to_close'], true)) {
            return ['error' => 'Solo se pueden cancelar procesos activos.'];
        }
        $actorId = (int)$actorId;
        $this->db->begin_transaction();
        try {
            $stmt = $this->db->prepare("UPDATE onboarding_processes SET status = 'cancelled', cancellation_reason = ?, cancelled_by = ?, cancelled_at = NOW() WHERE id = ? AND status IN ('in_progress', 'ready_to_close')");
            $stmt->bind_param('sii', $reason, $actorId, $id);
            if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                throw new RuntimeException('cancel');
            }
            $stmt->close();
            $this->recordHistory($id, $actorId, 'cancelled', 'Se canceló el proceso; se registró el motivo.', ['status' => $current['status']], ['status' => 'cancelled', 'reason' => $reason]);
            $this->db->commit();
            return ['success' => 'Proceso cancelado. Se conservaron las fases, tareas y el historial.'];
        } catch (Throwable $e) {
            $this->db->rollback();
            return ['error' => 'No se pudo cancelar el proceso.'];
        }
    }

    public function completeProcess($id, $actorId)
    {
        $id = (int)$id;
        $current = $this->fetchProcess($id);
        if (!$current || !in_array($current['status'], ['in_progress', 'ready_to_close'], true)) {
            return ['error' => 'Solo se pueden finalizar procesos activos.'];
        }
        $stmt = $this->db->prepare("SELECT COUNT(*) AS pending FROM onboarding_phases ph INNER JOIN onboarding_phase_tasks l ON l.phase_id = ph.id AND l.is_required = 1 INNER JOIN tareas t ON t.id = l.task_id WHERE ph.process_id = ? AND t.status <> 'completada'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $pending = (int)($stmt->get_result()->fetch_assoc()['pending'] ?? 0);
        $stmt->close();
        $stmt = $this->db->prepare('SELECT COUNT(*) AS required_total FROM onboarding_phases ph INNER JOIN onboarding_phase_tasks l ON l.phase_id = ph.id AND l.is_required = 1 WHERE ph.process_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $requiredTotal = (int)($stmt->get_result()->fetch_assoc()['required_total'] ?? 0);
        $stmt->close();
        if ($requiredTotal === 0 || $pending > 0) {
            return ['error' => 'No se puede finalizar: deben existir y estar completadas todas las tareas obligatorias.'];
        }
        $this->db->begin_transaction();
        try {
            $stmt = $this->db->prepare("UPDATE onboarding_processes SET status = 'completed', actual_end_date = CURDATE() WHERE id = ? AND status IN ('in_progress', 'ready_to_close')");
            $stmt->bind_param('i', $id);
            if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                throw new RuntimeException('complete');
            }
            $stmt->close();
            $this->refreshPhaseStatuses($id);
            $this->recordHistory($id, (int)$actorId, 'completed', 'Se finalizó el proceso de onboarding.', ['status' => $current['status']], ['status' => 'completed', 'actual_end_date' => date('Y-m-d')]);
            $this->db->commit();
            return ['success' => 'Proceso finalizado y fecha real registrada.'];
        } catch (Throwable $e) {
            $this->db->rollback();
            return ['error' => 'No se pudo finalizar el proceso.'];
        }
    }

    public function fetchSummary()
    {
        $this->refreshOpenStatuses();
        $summary = ['processes' => 0, 'inProgress' => 0, 'completed' => 0, 'pendingTasks' => 0];
        $result = $this->db->query("SELECT COUNT(*) AS processes, SUM(status IN ('in_progress', 'ready_to_close')) AS in_progress, SUM(status = 'completed') AS completed FROM onboarding_processes");
        if ($result && ($row = $result->fetch_assoc())) {
            $summary['processes'] = (int)$row['processes'];
            $summary['inProgress'] = (int)$row['in_progress'];
            $summary['completed'] = (int)$row['completed'];
        }
        $result = $this->db->query("SELECT COUNT(*) AS total FROM onboarding_phase_tasks l INNER JOIN tareas t ON t.id = l.task_id INNER JOIN onboarding_phases ph ON ph.id = l.phase_id INNER JOIN onboarding_processes op ON op.id = ph.process_id AND op.status IN ('in_progress', 'ready_to_close') WHERE t.status IN ('pendiente', 'bloqueada')");
        if ($result && ($row = $result->fetch_assoc())) {
            $summary['pendingTasks'] = (int)$row['total'];
        }
        return $summary;
    }

    public function csrfToken()
    {
        if (empty($_SESSION['onboarding_csrf'])) {
            $_SESSION['onboarding_csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['onboarding_csrf'];
    }

    private function generatePhasesAndTasks($processId, array $process, $actorId)
    {
        $result = $this->db->query('SELECT * FROM onboarding_phase_templates WHERE is_active = 1 ORDER BY phase_order');
        $templates = [];
        while ($result && ($template = $result->fetch_assoc())) {
            $templates[] = $template;
        }
        if (count($templates) !== 7) {
            throw new RuntimeException('phase_template_count');
        }
        foreach ($templates as $template) {
            $start = $this->dateOffset($process['start_date'], (int)$template['start_offset_days']);
            $end = $this->dateOffset($process['start_date'], (int)$template['end_offset_days']);
            $phaseStatus = $start > date('Y-m-d') ? 'pending' : 'in_progress';
            $stmt = $this->db->prepare('INSERT INTO onboarding_phases (process_id, template_id, phase_order, title, description, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('iiisssss', $processId, $template['id'], $template['phase_order'], $template['title'], $template['description'], $start, $end, $phaseStatus);
            if (!$stmt->execute()) {
                throw new RuntimeException('phase_insert');
            }
            $phaseId = (int)$this->db->insert_id;
            $stmt->close();
            $stmt = $this->db->prepare('SELECT * FROM onboarding_task_templates WHERE phase_template_id = ? AND is_active = 1 ORDER BY id');
            $stmt->bind_param('i', $template['id']);
            $stmt->execute();
            $tasks = $stmt->get_result();
            $required = 0;
            while ($taskTemplate = $tasks->fetch_assoc()) {
                $required += (int)$taskTemplate['is_required'];
                $assigneeId = (int)($process[$taskTemplate['assignee_key'] . '_user_id'] ?? 0);
                if ($assigneeId <= 0) {
                    $assigneeId = (int)$process['responsible_user_id'];
                }
                $due = $this->dateOffset($process['start_date'], (int)$taskTemplate['due_offset_days']);
                $taskStmt = $this->db->prepare("INSERT INTO tareas (titulo, descripcion, prioridad, fecha_limite, status, creado_por, asignado_a, created_at) VALUES (?, ?, ?, ?, 'pendiente', ?, ?, NOW())");
                $taskStmt->bind_param('ssssii', $taskTemplate['title'], $taskTemplate['description'], $taskTemplate['priority'], $due, $actorId, $assigneeId);
                if (!$taskStmt->execute()) {
                    throw new RuntimeException('task_insert');
                }
                $taskId = (int)$this->db->insert_id;
                $taskStmt->close();
                $linkStmt = $this->db->prepare('INSERT INTO onboarding_phase_tasks (phase_id, task_id, is_required, template_task_id) VALUES (?, ?, ?, ?)');
                $linkStmt->bind_param('iiii', $phaseId, $taskId, $taskTemplate['is_required'], $taskTemplate['id']);
                if (!$linkStmt->execute()) {
                    throw new RuntimeException('task_link');
                }
                $linkStmt->close();
            }
            $stmt->close();
            if ($required === 0) {
                throw new RuntimeException('phase_without_required_tasks');
            }
        }
    }

    private function validatePayload(array $input, $includePracticante)
    {
        $data = [
            'practicante_id' => (int)($input['practicante_id'] ?? 0),
            'start_date' => trim((string)($input['start_date'] ?? '')),
            'estimated_end_date' => trim((string)($input['estimated_end_date'] ?? '')),
            'modality' => trim((string)($input['modality'] ?? '')),
            'responsible_user_id' => (int)($input['responsible_user_id'] ?? 0),
            'supervisor_user_id' => (int)($input['supervisor_user_id'] ?? 0),
            'buddy_user_id' => (int)($input['buddy_user_id'] ?? 0),
            'backup_buddy_user_id' => (int)($input['backup_buddy_user_id'] ?? 0),
        ];
        if ($includePracticante && !$this->validPracticante($data['practicante_id'])) {
            return ['error' => 'Selecciona un practicante existente y activo.'];
        }
        if (!$this->validDate($data['start_date']) || !$this->validDate($data['estimated_end_date']) || $data['estimated_end_date'] < $data['start_date']) {
            return ['error' => 'Indica fechas válidas y asegúrate de que el término sea igual o posterior al inicio.'];
        }
        if (!in_array($data['modality'], ['presencial', 'hibrida', 'remota'], true)) {
            return ['error' => 'Selecciona una modalidad válida.'];
        }
        foreach (['responsible_user_id', 'supervisor_user_id'] as $key) {
            if (!$this->validUser($data[$key])) {
                return ['error' => 'El responsable y supervisor deben ser usuarios activos existentes.'];
            }
        }
        foreach (['buddy_user_id', 'backup_buddy_user_id'] as $key) {
            if ($data[$key] && !$this->validUser($data[$key])) {
                return ['error' => 'Los buddies seleccionados deben ser usuarios activos existentes.'];
            }
            if (!$data[$key]) {
                $data[$key] = null;
            }
        }
        return $data;
    }

    private function validPracticante($id)
    {
        if ($id <= 0) {
            return false;
        }
        $columns = db_table_columns($this->db, 'practicantes');
        $where = in_array('status', $columns, true) ? " AND status = 'active'" : '';
        $stmt = $this->db->prepare('SELECT id FROM practicantes WHERE id = ?' . $where . ' LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $exists;
    }

    private function validUser($id)
    {
        if ((int)$id <= 0) {
            return false;
        }
        $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE id = ? AND status = 'active' LIMIT 1");
        $id = (int)$id;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $exists;
    }

    private function refreshOpenStatuses()
    {
        $this->db->query("INSERT INTO onboarding_history (process_id, user_id, action, summary, before_data, after_data)
            SELECT op.id, NULL, 'ready_to_close', 'Todas las tareas obligatorias se completaron; el proceso está listo para cierre.', '{\"status\":\"in_progress\"}', '{\"status\":\"ready_to_close\"}'
            FROM onboarding_processes op
            WHERE op.status = 'in_progress'
                AND EXISTS (SELECT 1 FROM onboarding_phases ph INNER JOIN onboarding_phase_tasks l ON l.phase_id = ph.id AND l.is_required = 1 WHERE ph.process_id = op.id)
                AND NOT EXISTS (SELECT 1 FROM onboarding_phases ph INNER JOIN onboarding_phase_tasks l ON l.phase_id = ph.id AND l.is_required = 1 INNER JOIN tareas t ON t.id = l.task_id WHERE ph.process_id = op.id AND t.status <> 'completada')");
        $this->db->query("INSERT INTO onboarding_history (process_id, user_id, action, summary, before_data, after_data)
            SELECT op.id, NULL, 'reopened', 'Se reabrió una tarea obligatoria; el proceso volvió a estar en curso.', '{\"status\":\"ready_to_close\"}', '{\"status\":\"in_progress\"}'
            FROM onboarding_processes op
            WHERE op.status = 'ready_to_close'
                AND EXISTS (SELECT 1 FROM onboarding_phases ph INNER JOIN onboarding_phase_tasks l ON l.phase_id = ph.id AND l.is_required = 1 INNER JOIN tareas t ON t.id = l.task_id WHERE ph.process_id = op.id AND t.status <> 'completada')");
        $this->db->query("UPDATE onboarding_processes op SET op.status = CASE
            WHEN EXISTS (SELECT 1 FROM onboarding_phases ph INNER JOIN onboarding_phase_tasks l ON l.phase_id = ph.id AND l.is_required = 1 INNER JOIN tareas t ON t.id = l.task_id WHERE ph.process_id = op.id AND t.status <> 'completada') THEN 'in_progress'
            ELSE 'ready_to_close' END WHERE op.status IN ('in_progress', 'ready_to_close')
            AND EXISTS (SELECT 1 FROM onboarding_phases ph INNER JOIN onboarding_phase_tasks l ON l.phase_id = ph.id AND l.is_required = 1 WHERE ph.process_id = op.id);");
        $this->db->query("UPDATE onboarding_phases ph INNER JOIN onboarding_processes op ON op.id = ph.process_id AND op.status IN ('in_progress', 'ready_to_close')
            SET ph.status = CASE WHEN EXISTS (SELECT 1 FROM onboarding_phase_tasks l INNER JOIN tareas t ON t.id = l.task_id WHERE l.phase_id = ph.id AND l.is_required = 1 AND t.status <> 'completada')
                THEN CASE WHEN ph.start_date <= CURDATE() THEN 'in_progress' ELSE 'pending' END ELSE 'completed' END");
    }

    private function refreshPhaseStatuses($id)
    {
        $stmt = $this->db->prepare("UPDATE onboarding_phases ph SET ph.status = CASE
            WHEN EXISTS (SELECT 1 FROM onboarding_phase_tasks l INNER JOIN tareas t ON t.id = l.task_id WHERE l.phase_id = ph.id AND l.is_required = 1 AND t.status <> 'completada')
            THEN CASE WHEN ph.start_date <= CURDATE() THEN 'in_progress' ELSE 'pending' END ELSE 'completed' END WHERE ph.process_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }

    private function recordHistory($processId, $userId, $action, $summary, $before, $after)
    {
        $beforeJson = $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $afterJson = $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = $this->db->prepare('INSERT INTO onboarding_history (process_id, user_id, action, summary, before_data, after_data) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iissss', $processId, $userId, $action, $summary, $beforeJson, $afterJson);
        if (!$stmt->execute()) {
            throw new RuntimeException('history');
        }
        $stmt->close();
    }

    private function snapshot(array $process)
    {
        return ['start_date' => $process['start_date'], 'estimated_end_date' => $process['estimated_end_date'], 'modality' => $process['modality'],
            'responsible_user_id' => (int)$process['responsible_user_id'], 'supervisor_user_id' => (int)$process['supervisor_user_id'],
            'buddy_user_id' => $process['buddy_user_id'] === null ? null : (int)$process['buddy_user_id'],
            'backup_buddy_user_id' => $process['backup_buddy_user_id'] === null ? null : (int)$process['backup_buddy_user_id']];
    }

    private function dateOffset($date, $offset)
    {
        return (new DateTimeImmutable($date))->modify(($offset < 0 ? '' : '+') . (int)$offset . ' days')->format('Y-m-d');
    }

    private function validDate($value)
    {
        $date = DateTime::createFromFormat('!Y-m-d', trim((string)$value));
        return $date && $date->format('Y-m-d') === trim((string)$value);
    }

    private function firstColumn(array $columns, array $candidates)
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }
        return '';
    }

    private function practicanteProjection()
    {
        $columns = db_table_columns($this->db, 'practicantes');
        $name = $this->firstColumn($columns, ['full_name', 'nombre', 'name']);
        if ($name === '') {
            $name = 'id';
        }
        $joins = '';
        $career = [];
        if (in_array('carrera', $columns, true)) {
            $career[] = "NULLIF(TRIM(p.carrera), '')";
        }
        if (in_array('position_id', $columns, true) && db_table_exists($this->db, 'cargos')) {
            $joins .= ' LEFT JOIN cargos c ON c.id = p.position_id';
            $career[] = "NULLIF(TRIM(c.name), '')";
        }
        if (in_array('department_id', $columns, true) && db_table_exists($this->db, 'departamentos')) {
            $joins .= ' LEFT JOIN departamentos d ON d.id = p.department_id';
            $career[] = "NULLIF(TRIM(d.name), '')";
        }
        $careerSql = $career ? 'COALESCE(' . implode(', ', $career) . ", '')" : "''";
        $rut = in_array('rut', $columns, true) ? 'p.rut' : "''";
        $emailColumn = $this->firstColumn($columns, ['email', 'correo']);
        $email = $emailColumn === '' ? "''" : 'p.`' . $emailColumn . '`';
        $status = in_array('status', $columns, true) ? 'p.status' : "'active'";
        $start = in_array('fecha_inicio', $columns, true) ? 'p.fecha_inicio' : 'NULL';
        $end = in_array('fecha_fin', $columns, true) ? 'p.fecha_fin' : 'NULL';
        return 'SELECT p.id, p.`' . $name . '` AS full_name, ' . $rut . ' AS rut, ' . $email . ' AS email, ' . $careerSql . ' AS career, ' . $status . ' AS status, ' . $start . ' AS current_start_date, ' . $end . ' AS current_end_date FROM practicantes p' . $joins;
    }
}