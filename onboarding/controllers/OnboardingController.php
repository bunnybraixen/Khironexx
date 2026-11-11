<?php

class OnboardingController
{
    private $model;

    public function __construct(OnboardingModel $model)
    {
        $this->model = $model;
    }

    public function resolvePage($page)
    {
        $allowedPages = ['dashboard', 'procesos', 'detalle', 'tareas', 'biblioteca', 'plan-30-60-90'];
        return in_array($page, $allowedPages, true) ? $page : 'dashboard';
    }

    public function handlePost($page, array $post, array $user, $canManage)
    {
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
        }
        return $data;
    }
}