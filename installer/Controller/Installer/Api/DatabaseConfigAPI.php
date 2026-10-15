<?php

class DatabaseConfigAPI
{
    private function input()
    {
        $data = $_POST;
        if (!$data) {
            $raw = file_get_contents('php://input');
            if ($raw) {
                $json = json_decode($raw, true);
                if (is_array($json)) {
                    $data = $json;
                }
            }
        }
        return $data;
    }

    public function handle()
    {
        $data = $this->input();
        $host = trim((string)($data['db_host'] ?? 'localhost'));
        $port = trim((string)($data['db_port'] ?? '3306'));
        $name = trim((string)($data['db_name'] ?? 'khironex'));
        $user = trim((string)($data['db_user'] ?? 'root'));
        $pass = (string)($data['db_pass'] ?? '');
        $createDb = ($data['db_create'] ?? 'no') === 'yes';

        [$db, $err] = InstallerConnection::connect($host, $port, $user, $pass, null);
        if ($err || !$db) {
            return ['status' => 'error', 'message' => 'No se pudo conectar: ' . $err];
        }

        if ($createDb) {
            $safe = $db->real_escape_string($name);
            if (!$db->query("CREATE DATABASE IF NOT EXISTS `" . $safe . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
                return ['status' => 'error', 'message' => 'No se pudo crear la base de datos: ' . $db->error];
            }
        }

        $safe = $db->real_escape_string($name);
        $exists = $db->query("SHOW DATABASES LIKE '" . $safe . "'");
        if (!$exists || $exists->num_rows === 0) {
            return ['status' => 'error', 'message' => 'La base de datos no existe.'];
        }

        $_SESSION['db'] = [
            'host' => $host,
            'port' => $port,
            'name' => $name,
            'user' => $user,
            'pass' => $pass,
        ];

        return ['status' => 'ok', 'message' => 'Conexion guardada.'];
    }
}
