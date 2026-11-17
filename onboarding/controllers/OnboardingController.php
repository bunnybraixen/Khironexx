<?php

class OnboardingController
{
    private $model;
    private $tasksModel;

    public function __construct(OnboardingModel $model, OnboardingTasksModel $tasksModel)
    {
        $this->model = $model;
        $this->tasksModel = $tasksModel;
    }

    public function resolvePage($page)
    {
        $allowedPages = ['dashboard', 'procesos', 'detalle', 'tareas', 'biblioteca', 'plan-30-60-90'];
        return in_array($page, $allowedPages, true) ? $page : 'dashboard';
    }

    public function handlePost($page, array $post, array $user, $canManage)
    {
        if (isset($post['task_action'])) {
            if (!$canManage) {
                $_SESSION['onboarding_flash'] = ['type' => 'error', 'message' => 'No tienes permisos para administrar tareas de onboarding.'];
                return ['redirect' => 'index.php?page=tareas'];
            }
            $token = (string)($post['csrf_token'] ?? '');
            if ($token === '' || !hash_equals($this->model->csrfToken(), $token)) {
                $result = ['error' => 'La sesión del formulario venció. Recarga la página e inténtalo nuevamente.'];
            } else {
                $result = $this->tasksModel->handleTaskAction($post, $user);
            }
            $_SESSION['onboarding_flash'] = [
                'type' => !empty($result['success']) ? 'success' : 'error',
                'message' => $result['success'] ?? ($result['error'] ?? 'No se pudo guardar la tarea.'),
            ];
            $redirect = 'index.php?page=tareas';
            $taskId = (int)($result['task_id'] ?? $post['task_id'] ?? 0);
            if ($taskId > 0) $redirect .= '&task_id=' . $taskId . '#detalle-tarea';
            return ['redirect' => $redirect];
        }

        $action = (string)($post['onboarding_action'] ?? '');
        if ($action === '') {
            return null;
        }
        if (!$canManage) {
            return ['error' => 'No tienes permisos para administrar procesos de onboarding.'];
        }
        $token = (string)($post['csrf_token'] ?? '');
        if ($token === '' || !hash_equals($this->model->csrfToken(), $token)) {
            return ['error' => 'La sesión del formulario venció. Recarga la página e inténtalo nuevamente.'];
        }

        $processId = (int)($post['process_id'] ?? 0);
        if ($action === 'create') {
            $result = $this->model->createProcess($post, (int)($user['id'] ?? 0));
        } elseif ($action === 'update') {
            $result = $this->model->updateProcess($processId, $post, (int)($user['id'] ?? 0));
        } elseif ($action === 'cancel') {
            $result = $this->model->cancelProcess($processId, $post['cancellation_reason'] ?? '', (int)($user['id'] ?? 0));
        } elseif ($action === 'complete') {
            $result = $this->model->completeProcess($processId, (int)($user['id'] ?? 0));
        } else {
            $result = ['error' => 'Acción de onboarding desconocida.'];
        }

        if (!empty($result['success'])) {
            $_SESSION['onboarding_flash'] = ['type' => 'success', 'message' => $result['success']];
            if ($action === 'create') {
                return ['redirect' => 'index.php?page=detalle&id=' . (int)$result['id']];
            }
            if ($processId > 0) {
                return ['redirect' => 'index.php?page=detalle&id=' . $processId];
            }
            return ['redirect' => 'index.php?page=procesos'];
        }

        $_SESSION['onboarding_flash'] = ['type' => 'error', 'message' => $result['error'] ?? 'No se pudo completar la acción.'];
        if ($processId > 0 && $page === 'detalle') {
            return ['redirect' => 'index.php?page=detalle&id=' . $processId];
        }
        return ['redirect' => 'index.php?page=procesos'];
    }

    public function buildPageData($page, array $query = [])
    {
        $storageReady = $this->model->isStorageReady();
        $data = [
            'storageReady' => $storageReady,
            'practicantes' => [],
            'usuarios' => [],
            'processes' => [],
            'process' => null,
            'phases' => [],
            'history' => [],
            'taskSummary' => ['pending' => 0, 'completed' => 0, 'blocked' => 0, 'overdue' => 0, 'total' => 0],
            'filters' => [
                'q' => trim((string)($query['q'] ?? '')),
                'status' => (string)($query['status'] ?? ''),
                'responsible' => max(0, (int)($query['responsible'] ?? 0)),
                'start_from' => (string)($query['start_from'] ?? ''),
                'start_to' => (string)($query['start_to'] ?? ''),
                'end_from' => (string)($query['end_from'] ?? ''),
                'end_to' => (string)($query['end_to'] ?? ''),
            ],
            'processId' => max(0, (int)($query['id'] ?? 0)),
            'editProcess' => null,
            'csrfToken' => $this->model->csrfToken(),
            'taskCsrfToken' => $this->model->csrfToken(),
            'taskStorage' => $this->tasksModel->storageStatus(),
            'taskFilters' => [],
            'taskOptions' => ['processes' => [], 'phases' => [], 'users' => [], 'practicantes' => []],
            'taskUsers' => [],
            'tasks' => [],
            'selectedTask' => null,
            'taskEvidence' => [],
            'taskHistory' => [],
            'taskCounts' => ['pending' => 0, 'in_progress' => 0, 'blocked' => 0, 'completed' => 0, 'overdue' => 0],
            'summary' => ['processes' => 0, 'inProgress' => 0, 'completed' => 0, 'pendingTasks' => 0],
            'flash' => $_SESSION['onboarding_flash'] ?? null,
        ];
        unset($_SESSION['onboarding_flash']);
        if (!$storageReady) {
            return $data;
        }

        $data['summary'] = $this->model->fetchSummary();
        if ($page === 'procesos') {
            $data['practicantes'] = $this->model->fetchPracticantes();
            $data['usuarios'] = $this->model->fetchUsuarios();
            $data['processes'] = $this->model->fetchProcesses($data['filters']);
            $editId = max(0, (int)($query['edit'] ?? 0));
            $data['editProcess'] = $editId > 0 ? $this->model->fetchProcess($editId) : null;
        } elseif ($page === 'detalle') {
            $data['process'] = $this->model->fetchProcess($data['processId']);
            if ($data['process']) {
                $data['phases'] = $this->model->fetchPhases($data['processId']);
                $data['history'] = $this->model->fetchHistory($data['processId']);
                $data['taskSummary'] = $this->model->fetchTaskSummary($data['processId']);
            }
        } elseif ($page === 'tareas') {
            $data['taskFilters'] = [
                'q' => trim((string)($query['q'] ?? '')),
                'practitioner_id' => max(0, (int)($query['practitioner_id'] ?? 0)),
                'process_id' => max(0, (int)($query['process_id'] ?? 0)),
                'phase_id' => max(0, (int)($query['phase_id'] ?? 0)),
                'responsible_id' => max(0, (int)($query['responsible_id'] ?? 0)),
                'status' => (string)($query['status'] ?? ''),
                'priority' => (string)($query['priority'] ?? ''),
                'date_from' => (string)($query['date_from'] ?? ''),
                'date_to' => (string)($query['date_to'] ?? ''),
            ];
            $data['taskOptions'] = $this->tasksModel->fetchOptions();
            $data['taskUsers'] = $data['taskOptions']['users'];
            $data['practicantes'] = $data['taskOptions']['practicantes'];
            $data['tasks'] = $data['taskStorage']['ready'] ? $this->tasksModel->fetchTasks($data['taskFilters']) : [];
            $taskId = max(0, (int)($query['task_id'] ?? 0));
            $data['selectedTask'] = $taskId > 0 ? $this->tasksModel->fetchTask($taskId) : null;
            if ($data['selectedTask']) {
                $data['taskEvidence'] = $this->tasksModel->fetchEvidence($taskId);
                $data['taskHistory'] = $this->tasksModel->fetchHistory($data['selectedTask']);
            }
            foreach ($data['tasks'] as $task) {
                if (isset($data['taskCounts'][$task['status']])) $data['taskCounts'][$task['status']]++;
                if (!empty($task['is_overdue'])) $data['taskCounts']['overdue']++;
            }
        }
        return $data;
    }
}
