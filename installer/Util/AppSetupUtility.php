<?php

class AppSetupUtility
{
    public static function runSchema(mysqli $db)
    {
        $sql = [];

        // ======================
        // ROLES Y USUARIOS
        // ======================

        $sql[] = "CREATE TABLE IF NOT EXISTS roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role_key VARCHAR(32) NOT NULL UNIQUE,
            name VARCHAR(80) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $sql[] = "CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(120) NOT NULL,
            email VARCHAR(120) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $sql[] = "CREATE TABLE IF NOT EXISTS usuarios_roles (
            user_id INT NOT NULL,
            role_id INT NOT NULL,
            PRIMARY KEY (user_id, role_id),
            CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        // ======================
        // ESTRUCTURA ORGANIZACIONAL
        // ======================

        $sql[] = "CREATE TABLE IF NOT EXISTS departamentos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL UNIQUE,
            description VARCHAR(255) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $sql[] = "CREATE TABLE IF NOT EXISTS cargos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL UNIQUE,
            description VARCHAR(255) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        // ======================
        // SUPERVISORES
        // ======================

        $sql[] = "CREATE TABLE IF NOT EXISTS supervisores (
            id_supervisor INT AUTO_INCREMENT PRIMARY KEY,
            nombre_supervisor VARCHAR(120) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        // ======================
        // POSTULANTES
        // ======================

        $sql[] = "CREATE TABLE IF NOT EXISTS postulantes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(120) NOT NULL,
            email VARCHAR(120) DEFAULT NULL,
            telefono VARCHAR(30) DEFAULT NULL,
            carrera VARCHAR(120) DEFAULT NULL,
            universidad VARCHAR(120) DEFAULT NULL,
            jefe_carrera VARCHAR(120) DEFAULT NULL,
            jefe_carrera_email VARCHAR(120) DEFAULT NULL,
            horas_exigidas INT DEFAULT NULL,
            area_postulacion VARCHAR(120) DEFAULT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'registrado',
            status_updated_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $sql[] = "CREATE TABLE IF NOT EXISTS postulante_historial (
            id INT AUTO_INCREMENT PRIMARY KEY,
            postulante_id INT NOT NULL,
            estado VARCHAR(40) NOT NULL,
            nota VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_ph_postulante FOREIGN KEY (postulante_id) REFERENCES postulantes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $sql[] = "CREATE TABLE IF NOT EXISTS postulante_entrevistas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            postulante_id INT NOT NULL,
            tipo VARCHAR(40) NOT NULL,
            fecha DATE DEFAULT NULL,
            observaciones TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_pe_postulante FOREIGN KEY (postulante_id) REFERENCES postulantes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        // ======================
        // PRACTICANTES
        // ======================

        $sql[] = "CREATE TABLE IF NOT EXISTS practicantes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            postulante_id INT DEFAULT NULL,
            full_name VARCHAR(120) NOT NULL,
            email VARCHAR(120) DEFAULT NULL,
            correo_institucional VARCHAR(120) DEFAULT NULL,
            rut VARCHAR(30) DEFAULT NULL,
            telefono VARCHAR(30) DEFAULT NULL,
            carrera VARCHAR(120) DEFAULT NULL,
            universidad VARCHAR(120) DEFAULT NULL,
            fecha_cumpleanos DATE DEFAULT NULL,
            region VARCHAR(120) DEFAULT NULL,
            comuna VARCHAR(120) DEFAULT NULL,
            extranjero TINYINT(1) NOT NULL DEFAULT 0,
            ubicacion_extranjero VARCHAR(160) DEFAULT NULL,
            horas_exigidas INT DEFAULT NULL,
            department_id INT DEFAULT NULL,
            position_id INT DEFAULT NULL,
            fecha_inicio DATE DEFAULT NULL,
            fecha_fin DATE DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_pr_departamento FOREIGN KEY (department_id) REFERENCES departamentos(id) ON DELETE SET NULL,
            CONSTRAINT fk_pr_cargo FOREIGN KEY (position_id) REFERENCES cargos(id) ON DELETE SET NULL,
            CONSTRAINT fk_pr_postulante FOREIGN KEY (postulante_id) REFERENCES postulantes(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";


        $sql[] = "CREATE TABLE IF NOT EXISTS practicante_horas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            practicante_id INT NOT NULL,
            fecha DATE DEFAULT NULL,
            horas INT NOT NULL DEFAULT 0,
            detalle VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_ph_practicante FOREIGN KEY (practicante_id) REFERENCES practicantes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $sql[] = "CREATE TABLE IF NOT EXISTS sedes_capacitaciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            encargado VARCHAR(140) NOT NULL,
            contacto VARCHAR(180) DEFAULT NULL,
            extranjero TINYINT(1) NOT NULL DEFAULT 0,
            region VARCHAR(120) DEFAULT NULL,
            comuna VARCHAR(120) DEFAULT NULL,
            ubicacion_extranjero VARCHAR(160) DEFAULT NULL,
            direccion VARCHAR(255) DEFAULT NULL,
            capacidad_personas INT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $sql[] = "CREATE TABLE IF NOT EXISTS sedes_capacitaciones_fotos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sede_id INT NOT NULL,
            ruta VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_sede_foto_sede FOREIGN KEY (sede_id) REFERENCES sedes_capacitaciones(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        // ======================
        // RELATORES Y CREDENCIALES
        // ======================

        $sql[] = "CREATE TABLE IF NOT EXISTS relatores (
            id_relator INT AUTO_INCREMENT PRIMARY KEY,
            nombre_relator VARCHAR(120) NOT NULL,
            universidad VARCHAR(120) DEFAULT NULL,
            anio_egreso YEAR DEFAULT NULL,
            region VARCHAR(120) DEFAULT NULL,
            comuna VARCHAR(120) DEFAULT NULL,
            sence BOOLEAN DEFAULT FALSE,
            calificacion DECIMAL(3,1) DEFAULT NULL,
            sistema_reuf VARCHAR(10) DEFAULT NULL,
            capacitaciones_propias INT DEFAULT 0,
            capacitaciones_general INT DEFAULT 0,
            observaciones TEXT DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";


        $sql[] = "CREATE TABLE IF NOT EXISTS credenciales (
            id_credenciales INT AUTO_INCREMENT PRIMARY KEY,
            nombre_credencial VARCHAR(160) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $sql[] = "CREATE TABLE IF NOT EXISTS credenciales_relatores (
            relator_id INT NOT NULL,
            credencial_id INT NOT NULL,
            PRIMARY KEY (relator_id, credencial_id),
            CONSTRAINT fk_cr_relator FOREIGN KEY (relator_id) REFERENCES relatores(id_relator) ON DELETE CASCADE,
            CONSTRAINT fk_cr_credencial FOREIGN KEY (credencial_id) REFERENCES credenciales(id_credenciales) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        // ======================
        // CURSOS Y CAPACITACIONES
        // ======================

        $sql[] = "CREATE TABLE IF NOT EXISTS cursos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre_curso VARCHAR(160) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $sql[] = "CREATE TABLE IF NOT EXISTS capacitaciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(160) NOT NULL,
            description TEXT DEFAULT NULL,
            trainer_id INT DEFAULT NULL,
            start_date DATE DEFAULT NULL,
            end_date DATE DEFAULT NULL,
            lugar VARCHAR(120) DEFAULT NULL,
            horas INT DEFAULT NULL,
            estado VARCHAR(40) DEFAULT 'pendiente',
            report_path VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_cap_trainer FOREIGN KEY (trainer_id) REFERENCES relatores(id_relator) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";


        $sql[] = "CREATE TABLE IF NOT EXISTS capacitaciones_participantes (
            training_id INT NOT NULL,
            practicante_id INT NOT NULL,
            PRIMARY KEY (training_id, practicante_id),
            CONSTRAINT fk_cp_capacitacion FOREIGN KEY (training_id) REFERENCES capacitaciones(id) ON DELETE CASCADE,
            CONSTRAINT fk_cp_practicante FOREIGN KEY (practicante_id) REFERENCES practicantes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        // ======================
        // INFORMES
        // ======================

        $sql[] = "CREATE TABLE IF NOT EXISTS informes (
            id_informes INT AUTO_INCREMENT PRIMARY KEY,
            supervisor_principal INT DEFAULT NULL,
            fecha_curso DATE DEFAULT NULL,
            empresa VARCHAR(160) DEFAULT NULL,
            profesor INT NOT NULL,
            clase VARCHAR(160) DEFAULT NULL,
            documento LONGBLOB DEFAULT NULL,
            CONSTRAINT fk_inf_supervisor FOREIGN KEY (supervisor_principal) REFERENCES supervisores(id_supervisor) ON DELETE SET NULL,
            CONSTRAINT fk_inf_profesor FOREIGN KEY (profesor) REFERENCES relatores(id_relator) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        // ======================
        // DOCUMENTOS Y OBSERVACIONES
        // ======================

        $sql[] = "CREATE TABLE IF NOT EXISTS documentos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            target_type VARCHAR(20) NOT NULL,
            target_id INT NOT NULL,
            titulo VARCHAR(160) NOT NULL,
            tipo VARCHAR(60) DEFAULT NULL,
            ruta VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $sql[] = "CREATE TABLE IF NOT EXISTS observaciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            target_type VARCHAR(20) NOT NULL,
            target_id INT NOT NULL,
            detalle TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $sql[] = "CREATE TABLE IF NOT EXISTS archivos_compartidos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            titulo VARCHAR(160) NOT NULL,
            descripcion TEXT DEFAULT NULL,
            categoria VARCHAR(120) DEFAULT NULL,
            ruta VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) DEFAULT NULL,
            uploaded_by INT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_archivos_user FOREIGN KEY (uploaded_by) REFERENCES usuarios(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $sql[] = "CREATE TABLE IF NOT EXISTS solicitudes_cambio (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            modulo VARCHAR(40) NOT NULL,
            accion VARCHAR(60) NOT NULL,
            detalle TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pendiente',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            resolved_at DATETIME DEFAULT NULL,
            CONSTRAINT fk_solicitudes_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $sql[] = "CREATE TABLE IF NOT EXISTS tickets_soporte (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            asunto VARCHAR(160) NOT NULL,
            modulo VARCHAR(60) NOT NULL,
            prioridad VARCHAR(20) NOT NULL DEFAULT 'media',
            descripcion TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'abierto',
            respuesta TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            resolved_at DATETIME DEFAULT NULL,
            INDEX idx_tickets_status_created (status, created_at),
            INDEX idx_tickets_user_created (user_id, created_at),
            CONSTRAINT fk_tickets_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $sql[] = "CREATE TABLE IF NOT EXISTS tickets_mensajes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NOT NULL,
            user_id INT NOT NULL,
            mensaje TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ticket_messages_ticket_created (ticket_id, created_at),
            CONSTRAINT fk_ticket_messages_ticket FOREIGN KEY (ticket_id) REFERENCES tickets_soporte(id) ON DELETE CASCADE,
            CONSTRAINT fk_ticket_messages_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        // ======================
        // EJECUCIÃ“N
        // ======================

        foreach ($sql as $statement) {
            try {
                $ok = $db->query($statement);
            } catch (Throwable $e) {
                return $e->getMessage();
            }
            if (!$ok) {
                return $db->error;
            }
        }

        $columnMigrations = [
            ['practicantes', 'region', 'VARCHAR(120) DEFAULT NULL'],
            ['practicantes', 'comuna', 'VARCHAR(120) DEFAULT NULL'],
            ['practicantes', 'extranjero', 'TINYINT(1) NOT NULL DEFAULT 0'],
            ['practicantes', 'ubicacion_extranjero', 'VARCHAR(160) DEFAULT NULL'],
            ['practicantes', 'fecha_cumpleanos', 'DATE DEFAULT NULL'],
            ['relatores', 'email', 'VARCHAR(160) DEFAULT NULL'],
            ['relatores', 'telefono', 'VARCHAR(30) DEFAULT NULL'],
            ['relatores', 'especialidades', 'TEXT DEFAULT NULL'],
            ['relatores', 'cursos', 'TEXT DEFAULT NULL'],
            ['relatores', 'academico', 'TEXT DEFAULT NULL'],
            ['relatores', 'carrera', 'VARCHAR(160) DEFAULT NULL'],
            ['relatores', 'experiencia_cc', 'TINYINT(1) NOT NULL DEFAULT 0'],
            ['relatores', 'calificacion', 'DECIMAL(3,1) DEFAULT NULL'],
            ['relatores', 'sistema_reuf', 'VARCHAR(10) DEFAULT NULL'],
            ['relatores', 'cv_path', 'VARCHAR(255) DEFAULT NULL'],
            ['capacitaciones', 'cliente', 'VARCHAR(160) DEFAULT NULL'],
            ['capacitaciones', 'monitor', 'VARCHAR(160) DEFAULT NULL'],
            ['capacitaciones', 'execution_date', 'DATE DEFAULT NULL'],
        ];
        foreach ($columnMigrations as [$tableName, $columnName, $definition]) {
            $migrationErr = self::ensureColumn($db, $tableName, $columnName, $definition);
            if ($migrationErr !== null) {
                return "Error alterando {$tableName}.{$columnName}: " . $migrationErr;
            }
        }

        // Insert default roles
        if (!$db->query("INSERT IGNORE INTO roles (role_key, name) VALUES
            ('admin', 'Administracion'),
            ('administracion', 'Administracion operativa'),
            ('rrhh', 'RRHH'),
            ('read', 'Lectura')
        ")) {
            return $db->error;
        }

        return null;
    }

    public static function createOrUpdateAdmin(mysqli $db, $fullName, $email, $password, &$messages = [], &$errors = [])
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $userId = null;
        $existingId = null;

        $select = $db->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
        if (!$select) {
            $errors[] = "No se pudo preparar la busqueda de administrador: " . $db->error;
            return null;
        }
        if (!$select->bind_param('s', $email)) {
            $errors[] = "No se pudo enlazar email de administrador: " . $select->error;
            return null;
        }
        if (!$select->execute()) {
            $errors[] = "No se pudo buscar el administrador: " . $select->error;
            return null;
        }
        if (!$select->bind_result($existingId)) {
            $errors[] = "No se pudo leer el administrador existente: " . $select->error;
            return null;
        }
        $hasExisting = $select->fetch();
        $select->close();

        if ($hasExisting) {
            $userId = (int)$existingId;
            $update = $db->prepare("UPDATE usuarios SET full_name = ?, password_hash = ?, status = 'active' WHERE id = ?");
            if (!$update) {
                $errors[] = "No se pudo preparar la actualizacion del administrador: " . $db->error;
                return null;
            }
            $update->bind_param('ssi', $fullName, $hash, $userId);
            if (!$update->execute()) {
                $errors[] = "No se pudo actualizar el administrador: " . $update->error;
                return null;
            }
            $messages[] = "El usuario administrador ya existia y fue actualizado.";
        } else {
            $stmt = $db->prepare("INSERT INTO usuarios (full_name, email, password_hash, status, created_at) VALUES (?, ?, ?, 'active', NOW())");
            if (!$stmt) {
                $errors[] = "No se pudo preparar la creacion del administrador: " . $db->error;
                return null;
            }
            $stmt->bind_param('sss', $fullName, $email, $hash);
            if (!$stmt->execute()) {
                $errors[] = "No se pudo crear el usuario administrador: " . $stmt->error;
                return null;
            }
            $userId = $stmt->insert_id;
        }

        if ($userId) {
            if (!$db->query("INSERT IGNORE INTO usuarios_roles (user_id, role_id)
                SELECT " . (int)$userId . ", id FROM roles WHERE role_key = 'admin'")) {
                $errors[] = "No se pudo asignar el rol admin: " . $db->error;
                return null;
            }
        }

        return $userId;
    }

    private static function ensureColumn(mysqli $db, $tableName, $columnName, $definition)
    {
        if (self::columnExists($db, $tableName, $columnName)) {
            return null;
        }

        $safeTable = str_replace('`', '``', (string)$tableName);
        $safeColumn = str_replace('`', '``', (string)$columnName);
        $sql = "ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$definition}";

        if (!$db->query($sql)) {
            return $db->error;
        }

        return null;
    }

    private static function columnExists(mysqli $db, $tableName, $columnName)
    {
        $safeTable = str_replace('`', '``', (string)$tableName);
        $safeColumn = $db->real_escape_string((string)$columnName);
        $result = $db->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");

        return $result && $result->num_rows > 0;
    }

    public static function writeConfig($configPath, array $config)
    {
        $configPhp = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        $written = file_put_contents($configPath, $configPhp, LOCK_EX) !== false;
        if ($written) {
            @chmod($configPath, 0640);
        }
        return $written;
    }
}
