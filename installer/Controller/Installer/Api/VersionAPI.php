<?php

class VersionAPI
{
    public function handle()
    {
        return [
            'status' => 'ok',
            'app' => 'Khironex',
            'edition' => 'starter-5.x-adapted',
            'roles' => ['admin', 'rrhh', 'read'],
            'modules' => [
                'Administracion',
                'Practicantes',
                'Relatores',
                'Capacitaciones',
                'Reportes',
            ],
        ];
    }
}
