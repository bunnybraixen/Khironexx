<?php

class AdminUserAPI
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
        $fullName = trim((string)($data['admin_name'] ?? ''));
        $email = trim((string)($data['admin_email'] ?? ''));
        $password = (string)($data['admin_password'] ?? '');
        $password2 = (string)($data['admin_password2'] ?? '');

        if ($fullName === '' || $email === '' || $password === '') {
            return ['status' => 'error', 'message' => 'Completa todos los campos.'];
        }
        if ($password !== $password2) {
            return ['status' => 'error', 'message' => 'Las contrasenas no coinciden.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'error', 'message' => 'Email invalido.'];
        }
        if (!isset($_SESSION['db'])) {
            return ['status' => 'error', 'message' => 'Falta configuracion de base de datos.'];
        }

        $dbConf = $_SESSION['db'];
        [$db, $err] = InstallerConnection::connect($dbConf['host'], $dbConf['port'], $dbConf['user'], $dbConf['pass'], $dbConf['name']);
        if ($err) {
            return ['status' => 'error', 'message' => 'Conexion fallida: ' . $err];
        }

        try {
            $schemaErr = AppSetupUtility::runSchema($db);
            if ($schemaErr) {
                return ['status' => 'error', 'message' => 'Error creando tablas: ' . $schemaErr];
            }

            $messages = [];
            $errors = [];
            AppSetupUtility::createOrUpdateAdmin($db, $fullName, $email, $password, $messages, $errors);
            if ($errors) {
                return ['status' => 'error', 'message' => implode(' ', $errors)];
            }
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => 'Error al crear admin: ' . $e->getMessage()];
        }

        return ['status' => 'ok', 'message' => $messages ? implode(' ', $messages) : 'Administrador creado.'];
    }
}
