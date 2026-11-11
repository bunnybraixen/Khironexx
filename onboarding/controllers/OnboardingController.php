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

    public function buildPageData($page)
    {
        return [
            'storageReady' => $this->model->hasProcessStorage(),
            'practicantes' => in_array($page, ['procesos', 'detalle'], true) ? $this->model->fetchPracticantes() : [],
            'usuarios' => $page === 'procesos' ? $this->model->fetchUsuarios() : [],
            'processId' => max(0, (int)($_GET['id'] ?? 0)),
            'selectedPracticanteId' => max(0, (int)($_GET['practicante_id'] ?? 0)),
            'summary' => [
                'processes' => 0,
                'inProgress' => 0,
                'completed' => 0,
                'pendingTasks' => 0,
            ],
        ];
    }
}