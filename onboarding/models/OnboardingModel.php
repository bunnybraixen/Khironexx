<?php

class OnboardingModel
{
    private $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public function hasProcessStorage()
    {
        return function_exists('db_table_exists') && db_table_exists($this->db, 'onboarding_procesos');
    }

    public function fetchPracticantes()
    {
        if (!db_table_exists($this->db, 'practicantes')) {
            return [];
        }

        $columns = db_table_columns($this->db, 'practicantes');
        $nameColumn = $this->firstExistingColumn($columns, ['full_name', 'nombre', 'name']);
        if ($nameColumn === '') {
            return [];
        }

        $rows = [];
        try {
            $result = $this->db->query('SELECT id, `' . $nameColumn . '` AS display_name FROM practicantes ORDER BY `' . $nameColumn . '` ASC LIMIT 500');
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $rows[] = [
                        'id' => (int)($row['id'] ?? 0),
                        'name' => (string)($row['display_name'] ?? ''),
                    ];
                }
            }
        } catch (Throwable $e) {
            return [];
        }

        return $rows;
    }

    public function fetchUsuarios()
    {
        if (!db_table_exists($this->db, 'usuarios')) {
            return [];
        }

        $columns = db_table_columns($this->db, 'usuarios');
        $nameColumn = $this->firstExistingColumn($columns, ['full_name', 'nombre', 'name']);
        if ($nameColumn === '') {
            return [];
        }

        $activeFilter = in_array('status', $columns, true) ? " WHERE status = 'active'" : '';
        $rows = [];
        try {
            $result = $this->db->query('SELECT id, `' . $nameColumn . '` AS display_name FROM usuarios' . $activeFilter . ' ORDER BY `' . $nameColumn . '` ASC LIMIT 500');
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $rows[] = [
                        'id' => (int)($row['id'] ?? 0),
                        'name' => (string)($row['display_name'] ?? ''),
                    ];
                }
            }
        } catch (Throwable $e) {
            return [];
        }

        return $rows;
    }

    private function firstExistingColumn(array $columns, array $candidates)
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }
        return '';
    }
}