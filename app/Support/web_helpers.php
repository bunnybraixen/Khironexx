<?php

if (!function_exists('renderHeader')) {
    function renderHeader($title, $user)
    {
        $name = $user['full_name'] ?? 'Invitado';
        echo "<header><div class=\"container\"><strong>Khironex</strong> - " . htmlspecialchars($title) . "<div class=\"nav\">";
        if ($user) {
            echo "<a href=\"" . htmlspecialchars(route_url()) . "\">Inicio</a>";
            echo "<a href=\"" . htmlspecialchars(route_url('practicantes')) . "\">Practicantes</a>";
            echo "<a href=\"" . htmlspecialchars(route_url('relatores')) . "\">Relatores</a>";
            echo "<a href=\"" . htmlspecialchars(route_url('capacitaciones')) . "\">Capacitaciones</a>";
            echo "<a href=\"" . htmlspecialchars(route_url('informes')) . "\">Informes</a>";
            echo "<a href=\"" . htmlspecialchars(route_url('usuarios')) . "\">Usuarios</a>";
            echo "<a href=\"" . htmlspecialchars(route_url('logout')) . "\">Salir (" . htmlspecialchars($name) . ")</a>";
        }
        echo "</div></div></header>";
    }
}

if (!function_exists('requireRole')) {
    function requireRole($roleKey, $userRoles)
    {
        return in_array($roleKey, $userRoles, true);
    }
}

if (!function_exists('fetchRoles')) {
    function fetchRoles($db, $userId)
    {
        $roles = [];
        $userId = (int)$userId;
        if (!$db instanceof mysqli || $userId <= 0) {
            return $roles;
        }

        $rolesSchemaAvailable = true;
        if (function_exists('db_table_exists')) {
            $rolesSchemaAvailable = db_table_exists($db, 'roles') && db_table_exists($db, 'usuarios_roles');
        }

        if ($rolesSchemaAvailable) {
            try {
                $stmt = $db->prepare("SELECT r.role_key
                    FROM roles r
                    INNER JOIN usuarios_roles ur ON ur.role_id = r.id
                    WHERE ur.user_id = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $userId);
                    if ($stmt->execute()) {
                        if (method_exists($stmt, 'get_result')) {
                            $result = $stmt->get_result();
                            if ($result) {
                                while ($row = $result->fetch_assoc()) {
                                    $roleKey = trim((string)($row['role_key'] ?? ''));
                                    if ($roleKey !== '') {
                                        $roles[] = $roleKey;
                                    }
                                }
                            }
                        } else {
                            $stmt->bind_result($roleKey);
                            while ($stmt->fetch()) {
                                $key = trim((string)$roleKey);
                                if ($key !== '') {
                                    $roles[] = $key;
                                }
                            }
                        }
                    }
                    $stmt->close();
                }
            } catch (Throwable $e) {
                $roles = [];
            }
        }

        if ($roles) {
            return array_values(array_unique($roles));
        }

        // Fallback para instalaciones antiguas sin tabla roles/usuarios_roles.
        try {
            if (function_exists('db_table_exists') && !db_table_exists($db, 'usuarios')) {
                return [];
            }

            $userCols = function_exists('db_table_columns') ? db_table_columns($db, 'usuarios') : [];
            $legacyRoleColumn = '';
            foreach (['role', 'rol', 'role_key', 'roles'] as $candidate) {
                if (in_array($candidate, $userCols, true)) {
                    $legacyRoleColumn = $candidate;
                    break;
                }
            }

            if ($legacyRoleColumn !== '') {
                $safeRoleColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $legacyRoleColumn);
                $stmt = $db->prepare("SELECT " . $safeRoleColumn . " FROM usuarios WHERE id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('i', $userId);
                    if ($stmt->execute()) {
                        $stmt->bind_result($legacyRoleValue);
                        if ($stmt->fetch()) {
                            $tokens = preg_split('/[\s,;|]+/', strtolower(trim((string)$legacyRoleValue)));
                            foreach ($tokens as $token) {
                                if ($token === '' || $token === null) {
                                    continue;
                                }
                                if ($token === 'admin') {
                                    $roles = array_merge($roles, ['admin', 'administracion', 'rrhh', 'read']);
                                } elseif ($token === 'rrhh') {
                                    $roles = array_merge($roles, ['rrhh', 'read']);
                                } elseif ($token === 'administracion') {
                                    $roles = array_merge($roles, ['administracion', 'read']);
                                } elseif ($token === 'read') {
                                    $roles[] = 'read';
                                } else {
                                    $roles[] = $token;
                                }
                            }
                        }
                    }
                    $stmt->close();
                }
            }

            if (!$roles) {
                $countStmt = $db->prepare("SELECT COUNT(*) FROM usuarios");
                if ($countStmt && $countStmt->execute()) {
                    $countStmt->bind_result($usersTotal);
                    if ($countStmt->fetch() && (int)$usersTotal === 1) {
                        $roles = ['admin', 'administracion', 'rrhh', 'read'];
                    }
                    $countStmt->close();
                }
            }
        } catch (Throwable $e) {
            return [];
        }

        return array_values(array_unique($roles));
    }
}

if (!function_exists('fetchOptions')) {
    function fetchOptions($db, $table, $labelColumn = 'name', $idColumn = 'id')
    {
        $options = [];
        $tableName = $db->real_escape_string($table);
        $labelColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $labelColumn);
        $idColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $idColumn);
        try {
            $result = $db->query("SELECT " . $idColumn . " AS id, " . $labelColumn . " AS label FROM " . $tableName . " ORDER BY " . $labelColumn . " ASC");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $options[(int)$row['id']] = $row['label'];
                }
            }
        } catch (Throwable $e) {
            return $options;
        }
        return $options;
    }
}

if (!function_exists('fetchCount')) {
    function fetchCount($db, $table)
    {
        $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        try {
            $result = $db->query("SELECT COUNT(*) AS total FROM " . $tableName);
            if ($result) {
                $row = $result->fetch_assoc();
                return (int)($row['total'] ?? 0);
            }
        } catch (Throwable $e) {
            return 0;
        }
        return 0;
    }
}

if (!function_exists('db_table_columns')) {
    function db_table_columns(mysqli $db, $table)
    {
        $cols = [];
        try {
            $result = $db->query("SHOW COLUMNS FROM " . $db->real_escape_string($table));
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $cols[] = $row['Field'];
                }
            }
        } catch (Throwable $e) {
            return $cols;
        }
        return $cols;
    }
}

if (!function_exists('db_table_exists')) {
    function db_table_exists(mysqli $db, $table)
    {
        $table = trim((string)$table);
        if ($table === '') {
            return false;
        }
        try {
            $result = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
            if (!$result) {
                return false;
            }
            return (bool)$result->fetch_row();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('relator_id_column')) {
    function relator_id_column(mysqli $db)
    {
        $cols = db_table_columns($db, 'relatores');
        foreach (['id_relator', 'id'] as $candidate) {
            if (in_array($candidate, $cols, true)) {
                return $candidate;
            }
        }
        return 'id_relator';
    }
}

if (!function_exists('relator_name_column')) {
    function relator_name_column(mysqli $db)
    {
        $cols = db_table_columns($db, 'relatores');
        foreach (['nombre_relator', 'full_name', 'name'] as $candidate) {
            if (in_array($candidate, $cols, true)) {
                return $candidate;
            }
        }
        return 'nombre_relator';
    }
}

if (!function_exists('store_uploaded_file')) {
    function store_uploaded_file(array $file, $subdir)
    {
        if (empty($file) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        // if storing for practicantes photos, restrict to common image types
        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];
        if (isset($subdir) && $subdir === 'practicantes') {
            $allowed = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
        }
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, $allowed, true)) {
            return null;
        }
        // store files under the project root by default (outside web/)
        $root = dirname(__DIR__, 2);
        $subdir = trim((string)$subdir, '/');
        $relativeDir = 'uploads/' . $subdir;
        $targetDir = $root . '/' . $relativeDir;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
            return null;
        }
        $safeName = uniqid('doc_', true) . '.' . $ext;
        $target = $targetDir . '/' . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return null;
        }
        return $relativeDir . '/' . $safeName;
    }
}
