<?php

/** Manages onboarding tasks through the shared Khironex tareas table. */
class OnboardingTasksModel
{
    private $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public function storageStatus()
    {
        $required = ['onboarding_processes', 'onboarding_phases', 'onboarding_task_templates', 'onboarding_phase_tasks', 'onboarding_history', 'tareas', 'practicantes', 'usuarios'];
        $missing = [];
        foreach ($required as $table) {
            if (!db_table_exists($this->db, $table)) $missing[] = $table;
        }
        return ['ready' => empty($missing), 'missing' => $missing, 'evidence' => db_table_exists($this->db, 'onboarding_evidence')];
    }

    public function fetchOptions()
    {
        $options = ['processes' => [], 'phases' => [], 'users' => [], 'practicantes' => []];
        if (!$this->storageStatus()['ready']) return $options;
        $person = $this->practitionerExpression('p');
        try {
            $result = $this->db->query('SELECT op.id, op.status, ' . $person . ' AS practitioner_name FROM onboarding_processes op JOIN practicantes p ON p.id = op.practicante_id ORDER BY op.start_date DESC, op.id DESC');
            while ($result && ($row = $result->fetch_assoc())) {
                $options['processes'][] = ['id' => (int)$row['id'], 'name' => $row['practitioner_name'] . ' · Proceso #' . (int)$row['id']];
            }
            $result = $this->db->query('SELECT ph.id, ph.title, ph.phase_order, op.id AS process_id, ' . $person . ' AS practitioner_name FROM onboarding_phases ph JOIN onboarding_processes op ON op.id = ph.process_id JOIN practicantes p ON p.id = op.practicante_id ORDER BY op.id DESC, ph.phase_order ASC');
            while ($result && ($row = $result->fetch_assoc())) {
                $options['phases'][] = ['id' => (int)$row['id'], 'process_id' => (int)$row['process_id'], 'name' => $row['practitioner_name'] . ' · Proceso #' . (int)$row['process_id'] . ' · Fase ' . (int)$row['phase_order'] . ': ' . $row['title']];
            }
            $result = $this->db->query("SELECT id, full_name FROM usuarios WHERE status = 'active' ORDER BY full_name ASC LIMIT 500");
            while ($result && ($row = $result->fetch_assoc())) $options['users'][] = ['id' => (int)$row['id'], 'name' => (string)$row['full_name']];
            $result = $this->db->query('SELECT id, ' . $person . ' AS display_name FROM practicantes p ORDER BY display_name ASC LIMIT 500');
            while ($result && ($row = $result->fetch_assoc())) $options['practicantes'][] = ['id' => (int)$row['id'], 'name' => (string)$row['display_name']];
        } catch (Throwable $e) {
            return ['processes' => [], 'phases' => [], 'users' => [], 'practicantes' => []];
        }
        return $options;
    }

    public function fetchTasks(array $filters)
    {
        if (!$this->storageStatus()['ready']) return [];
        $person = $this->practitionerExpression('p');
        $where = [];
        foreach (['process_id' => 'op.id', 'practitioner_id' => 'op.practicante_id', 'phase_id' => 'ph.id', 'responsible_id' => 't.asignado_a'] as $key => $column) {
            $value = max(0, (int)($filters[$key] ?? 0));
            if ($value > 0) $where[] = $column . ' = ' . $value;
        }
        $statusMap = ['pending' => 'pendiente', 'in_progress' => 'en_progreso', 'completed' => 'completada', 'blocked' => 'bloqueada'];
        $priorityMap = ['low' => 'baja', 'medium' => 'media', 'high' => 'alta', 'urgent' => 'urgente'];
        if (isset($statusMap[$filters['status'] ?? ''])) $where[] = "t.status = '" . $statusMap[$filters['status']] . "'";
        if (isset($priorityMap[$filters['priority'] ?? ''])) $where[] = "t.prioridad = '" . $priorityMap[$filters['priority']] . "'";
        $from = $this->validDate($filters['date_from'] ?? '');
        $to = $this->validDate($filters['date_to'] ?? '');
        if ($from !== '') $where[] = "t.fecha_limite >= '" . $this->db->real_escape_string($from) . "'";
        if ($to !== '') $where[] = "t.fecha_limite <= '" . $this->db->real_escape_string($to) . "'";
        $search = trim((string)($filters['q'] ?? ''));
        if ($search !== '') {
            $needle = $this->db->real_escape_string($search);
            $where[] = '(t.titulo LIKE \'%' . $needle . '%\' OR t.descripcion LIKE \'%' . $needle . '%\' OR ' . $person . ' LIKE \'%' . $needle . '%\' OR ph.title LIKE \'%' . $needle . '%\')';
        }
        $sql = 'SELECT t.id, t.titulo AS title, t.descripcion AS description, t.fecha_limite AS due_date, t.status AS raw_status, t.prioridad AS raw_priority, t.asignado_a AS assigned_user_id, t.created_at, t.completed_at, '
            . 'l.is_required, l.template_task_id, ph.id AS phase_id, ph.title AS phase_name, ph.phase_order AS phase_number, op.id AS process_id, op.status AS process_status, '
            . $person . ' AS practitioner_name, u.full_name AS responsible_name, tt.title AS template_title '
            . 'FROM tareas t JOIN onboarding_phase_tasks l ON l.task_id = t.id JOIN onboarding_phases ph ON ph.id = l.phase_id '
            . 'JOIN onboarding_processes op ON op.id = ph.process_id JOIN practicantes p ON p.id = op.practicante_id '
            . 'JOIN usuarios u ON u.id = t.asignado_a LEFT JOIN onboarding_task_templates tt ON tt.id = l.template_task_id '
            . (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where))
            . " ORDER BY CASE WHEN t.status <> 'completada' AND t.fecha_limite < CURDATE() THEN 0 ELSE 1 END, FIELD(t.status, 'bloqueada', 'en_progreso', 'pendiente', 'completada'), t.fecha_limite IS NULL, t.fecha_limite ASC, t.id DESC LIMIT 500";
        $rows = [];
        try {
            $result = $this->db->query($sql);
            while ($result && ($row = $result->fetch_assoc())) $rows[] = $this->normalizeTask($row);
        } catch (Throwable $e) {
            return [];
        }
        return $rows;
    }

    public function fetchTask($taskId)
    {
        $taskId = (int)$taskId;
        if ($taskId <= 0 || !$this->storageStatus()['ready']) return null;
        $person = $this->practitionerExpression('p');
        $sql = 'SELECT t.id, t.titulo AS title, t.descripcion AS description, t.fecha_limite AS due_date, t.status AS raw_status, t.prioridad AS raw_priority, t.asignado_a AS assigned_user_id, t.created_at, t.completed_at, '
            . 'l.is_required, l.template_task_id, ph.id AS phase_id, ph.title AS phase_name, ph.phase_order AS phase_number, op.id AS process_id, op.practicante_id AS practitioner_id, '
            . $person . ' AS practitioner_name, u.full_name AS responsible_name, tt.title AS template_title '
            . 'FROM tareas t JOIN onboarding_phase_tasks l ON l.task_id = t.id JOIN onboarding_phases ph ON ph.id = l.phase_id '
            . 'JOIN onboarding_processes op ON op.id = ph.process_id JOIN practicantes p ON p.id = op.practicante_id '
            . 'JOIN usuarios u ON u.id = t.asignado_a LEFT JOIN onboarding_task_templates tt ON tt.id = l.template_task_id WHERE t.id = ? LIMIT 1';
        try {
            $stmt = $this->db->prepare($sql);
            if (!$stmt) return null;
            $stmt->bind_param('i', $taskId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();
            return $row ? $this->normalizeTask($row) : null;
        } catch (Throwable $e) { return null; }
    }

    public function fetchEvidence($taskId)
    {
        if (!db_table_exists($this->db, 'onboarding_evidence')) return [];
        $rows = [];
        try {
            $stmt = $this->db->prepare('SELECT id, file_path, original_filename, description, uploaded_at FROM onboarding_evidence WHERE task_id = ? ORDER BY uploaded_at DESC, id DESC');
            if (!$stmt) return [];
            $taskId = (int)$taskId;
            $stmt->bind_param('i', $taskId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($result && ($row = $result->fetch_assoc())) $rows[] = $row;
            $stmt->close();
        } catch (Throwable $e) { return []; }
        return $rows;
    }

    public function fetchHistory(array $task)
    {
        $rows = [];
        try {
            $processId = (int)$task['process_id'];
            $stmt = $this->db->prepare('SELECT h.action, h.summary, h.after_data, h.created_at, u.full_name AS user_name FROM onboarding_history h LEFT JOIN usuarios u ON u.id = h.user_id WHERE h.process_id = ? ORDER BY h.created_at DESC, h.id DESC LIMIT 100');
            if (!$stmt) return [];
            $stmt->bind_param('i', $processId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($result && ($row = $result->fetch_assoc())) {
                $after = json_decode((string)($row['after_data'] ?? ''), true);
                if (is_array($after) && (int)($after['task_id'] ?? 0) === (int)$task['id']) $rows[] = $row;
            }
            $stmt->close();
        } catch (Throwable $e) { return []; }
        return $rows;
    }

    public function handleTaskAction(array $post, array $user)
    {
        if (!$this->storageStatus()['ready']) return ['error' => 'Falta aplicar la migración de Onboarding y habilitar la tabla compartida de tareas.'];
        if (($post['task_action'] ?? '') === 'create') return $this->createTask($post, $user);
        if (($post['task_action'] ?? '') === 'update') return $this->updateTask($post, $user);
        return ['error' => 'La acción solicitada no es válida.'];
    }

    private function createTask(array $post, array $user)
    {
        $phaseId = max(0, (int)($post['phase_id'] ?? 0));
        $title = trim((string)($post['title'] ?? ''));
        $description = trim((string)($post['description'] ?? ''));
        $assignee = max(0, (int)($post['assigned_user_id'] ?? 0));
        $dueInput = trim((string)($post['due_date'] ?? ''));
        $dueDate = $this->validDate($dueInput);
        $priority = $this->toDbPriority((string)($post['priority'] ?? 'medium'));
        $required = !empty($post['is_required']) ? 1 : 0;
        if ($phaseId <= 0 || !$this->phaseExists($phaseId)) return ['error' => 'Selecciona una fase válida.'];
        if ($title === '' || mb_strlen($title) > 160) return ['error' => 'El título es obligatorio y admite hasta 160 caracteres.'];
        if (mb_strlen($description) > 5000) return ['error' => 'La descripción admite hasta 5.000 caracteres.'];
        if ($dueInput !== '' && $dueDate === '') return ['error' => 'La fecha límite no es válida.'];
        if ($priority === '') return ['error' => 'Selecciona una prioridad válida.'];
        if (!$this->validUser($assignee) || !$this->validUser((int)($user['id'] ?? 0))) return ['error' => 'Selecciona un responsable activo y comprueba tu sesión.'];
        $phase = $this->phaseContext($phaseId);
        if (!$phase) return ['error' => 'La fase ya no está disponible.'];
        $dbPriority = $priority;
        $dueDate = $dueDate !== '' ? $dueDate : null;
        $creatorId = (int)$user['id'];
        $status = 'pendiente';
        $this->db->begin_transaction();
        try {
            $stmt = $this->db->prepare('INSERT INTO tareas (titulo, descripcion, prioridad, fecha_limite, status, creado_por, asignado_a, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
            if (!$stmt) throw new RuntimeException('prepare_task');
            $stmt->bind_param('sssssii', $title, $description, $dbPriority, $dueDate, $status, $creatorId, $assignee);
            if (!$stmt->execute()) throw new RuntimeException('insert_task');
            $taskId = (int)$this->db->insert_id;
            $stmt->close();
            $stmt = $this->db->prepare('INSERT INTO onboarding_phase_tasks (phase_id, task_id, is_required, template_task_id) VALUES (?, ?, ?, NULL)');
            if (!$stmt) throw new RuntimeException('prepare_link');
            $stmt->bind_param('iii', $phaseId, $taskId, $required);
            if (!$stmt->execute()) throw new RuntimeException('insert_link');
            $stmt->close();
            $this->recordHistory((int)$phase['process_id'], $taskId, $creatorId, 'task_created', 'Se creó la tarea «' . $title . '».', null, ['task_id' => $taskId, 'title' => $title, 'status' => $status]);
            $this->db->commit();
            return ['success' => 'Tarea creada correctamente.', 'task_id' => $taskId];
        } catch (Throwable $e) {
            $this->db->rollback();
            return ['error' => 'No se pudo crear la tarea. Revisa el proceso, la fase y las tablas de tareas.'];
        }
    }

    private function updateTask(array $post, array $user)
    {
        $taskId = max(0, (int)($post['task_id'] ?? 0));
        $task = $this->fetchTask($taskId);
        if (!$task) return ['error' => 'No se encontró esa tarea de onboarding.'];
        $title = trim((string)($post['title'] ?? ''));
        $description = trim((string)($post['description'] ?? ''));
        $assignee = max(0, (int)($post['assigned_user_id'] ?? 0));
        $dueInput = trim((string)($post['due_date'] ?? ''));
        $dueDate = $this->validDate($dueInput);
        $priority = $this->toDbPriority((string)($post['priority'] ?? ''));
        $status = $this->toDbStatus((string)($post['status'] ?? ''));
        if ($title === '' || mb_strlen($title) > 160) return ['error' => 'El título es obligatorio y admite hasta 160 caracteres.'];
        if (mb_strlen($description) > 5000) return ['error' => 'La descripción admite hasta 5.000 caracteres.'];
        if ($dueInput !== '' && $dueDate === '') return ['error' => 'La fecha límite no es válida.'];
        if ($priority === '' || $status === '') return ['error' => 'Selecciona un estado y prioridad válidos.'];
        if (!$this->validUser($assignee)) return ['error' => 'El responsable debe ser un usuario activo existente.'];
        $dueDate = $dueDate !== '' ? $dueDate : null;
        $completedAt = $status === 'completada' ? ($task['completed_at'] ?: date('Y-m-d H:i:s')) : null;
        try {
            $stmt = $this->db->prepare('UPDATE tareas SET titulo = ?, descripcion = ?, asignado_a = ?, fecha_limite = ?, prioridad = ?, status = ?, completed_at = ? WHERE id = ?');
            if (!$stmt) return ['error' => 'No se pudo preparar la actualización.'];
            $stmt->bind_param('ssissssi', $title, $description, $assignee, $dueDate, $priority, $status, $completedAt, $taskId);
            if (!$stmt->execute()) { $stmt->close(); return ['error' => 'No se pudieron guardar los cambios.']; }
            $stmt->close();
            $after = ['task_id' => $taskId, 'title' => $title, 'status' => $status, 'priority' => $priority, 'assigned_user_id' => $assignee, 'due_date' => $dueDate];
            $before = ['task_id' => $taskId, 'title' => $task['title'], 'status' => $task['raw_status'], 'priority' => $task['raw_priority'], 'assigned_user_id' => $task['assigned_user_id'], 'due_date' => $task['due_date']];
            if ($before !== $after || (string)$task['description'] !== $description) {
                $after['description'] = $description;
                $this->recordHistory((int)$task['process_id'], $taskId, (int)($user['id'] ?? 0), 'task_updated', 'Se actualizaron los datos de la tarea «' . $title . '».', $before, $after);
            }
            return ['success' => 'Cambios guardados correctamente.', 'task_id' => $taskId];
        } catch (Throwable $e) {
            return ['error' => 'No se pudieron guardar los cambios de la tarea.'];
        }
    }

    private function normalizeTask(array $row)
    {
        $statusMap = ['pendiente' => 'pending', 'en_progreso' => 'in_progress', 'completada' => 'completed', 'bloqueada' => 'blocked'];
        $priorityMap = ['baja' => 'low', 'media' => 'medium', 'alta' => 'high', 'urgente' => 'urgent'];
        $row['raw_status'] = (string)$row['raw_status'];
        $row['raw_priority'] = (string)$row['raw_priority'];
        $row['status'] = $statusMap[$row['raw_status']] ?? 'pending';
        $row['priority'] = $priorityMap[$row['raw_priority']] ?? 'medium';
        $row['assigned_user_id'] = (int)$row['assigned_user_id'];
        $row['is_required'] = (int)$row['is_required'];
        $row['phase_id'] = (int)$row['phase_id'];
        $row['phase_number'] = (int)$row['phase_number'];
        $row['process_id'] = (int)$row['process_id'];
        $row['is_overdue'] = $row['raw_status'] !== 'completada' && !empty($row['due_date']) && $row['due_date'] < date('Y-m-d');
        $row['comments'] = '';
        return $row;
    }

    private function recordHistory($processId, $taskId, $userId, $action, $summary, $before, $after)
    {
        $beforeJson = $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $afterJson = json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = $this->db->prepare('INSERT INTO onboarding_history (process_id, user_id, action, summary, before_data, after_data) VALUES (?, ?, ?, ?, ?, ?)');
        if (!$stmt) return;
        $userId = max(0, (int)$userId);
        $stmt->bind_param('iissss', $processId, $userId, $action, $summary, $beforeJson, $afterJson);
        $stmt->execute();
        $stmt->close();
    }

    private function phaseExists($phaseId)
    {
        $stmt = $this->db->prepare('SELECT id FROM onboarding_phases WHERE id = ? LIMIT 1');
        if (!$stmt) return false;
        $stmt->bind_param('i', $phaseId);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $exists;
    }

    private function phaseContext($phaseId)
    {
        $stmt = $this->db->prepare('SELECT ph.process_id, ph.phase_order, ph.title, op.practicante_id FROM onboarding_phases ph JOIN onboarding_processes op ON op.id = ph.process_id WHERE ph.id = ? LIMIT 1');
        if (!$stmt) return null;
        $stmt->bind_param('i', $phaseId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    private function validUser($userId)
    {
        if ($userId <= 0) return false;
        $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE id = ? AND status = 'active' LIMIT 1");
        if (!$stmt) return false;
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $exists;
    }

    private function practitionerExpression($alias)
    {
        $columns = db_table_columns($this->db, 'practicantes');
        foreach (['full_name', 'nombre', 'name'] as $column) {
            if (in_array($column, $columns, true)) return $alias . '.`' . $column . '`';
        }
        if (in_array('nombres', $columns, true) && in_array('apellidos', $columns, true)) return "CONCAT(" . $alias . '.`nombres`, \' \', ' . $alias . '.`apellidos`)';
        return $alias . '.id';
    }

    private function toDbStatus($status)
    {
        return ['pending' => 'pendiente', 'in_progress' => 'en_progreso', 'completed' => 'completada', 'blocked' => 'bloqueada'][$status] ?? '';
    }

    private function toDbPriority($priority)
    {
        return ['low' => 'baja', 'medium' => 'media', 'high' => 'alta', 'urgent' => 'urgente'][$priority] ?? '';
    }

    private function validDate($date)
    {
        $date = trim((string)$date);
        $parsed = DateTime::createFromFormat('!Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date ? $date : '';
    }
}
