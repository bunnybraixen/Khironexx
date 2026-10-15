<?php

if (!function_exists('db_manager_format_bytes')) {
    function db_manager_format_bytes($bytes, $precision = 2)
    {
        $bytes = (float)$bytes;
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = floor(log($bytes) / log(1024));
        $pow = min((int)$pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

if (!function_exists('db_manager_get_tables')) {
    function db_manager_get_tables(mysqli $db)
    {
        $tables = [];
        try {
            $result = $db->query("SHOW TABLE STATUS");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $tableName = (string)($row['Name'] ?? '');
                    if ($tableName === '') {
                        continue;
                    }
                    $dataLength = (int)($row['Data_length'] ?? 0) + (int)($row['Index_length'] ?? 0);
                    $tables[] = [
                        'name' => $tableName,
                        'rows' => (int)($row['Rows'] ?? 0),
                        'engine' => (string)($row['Engine'] ?? 'InnoDB'),
                        'collation' => (string)($row['Collation'] ?? 'utf8mb4_general_ci'),
                        'size' => db_manager_format_bytes($dataLength),
                        'comment' => (string)($row['Comment'] ?? ''),
                    ];
                }
            }
        } catch (Throwable $e) {
            $tables = [];
        }
        return $tables;
    }
}

if (!function_exists('db_manager_get_table_columns')) {
    function db_manager_get_table_columns(mysqli $db, $table)
    {
        $cols = [];
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        if ($safeTable === '') {
            return $cols;
        }

        try {
            $result = $db->query("SHOW FULL COLUMNS FROM `{$safeTable}`");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $cols[$row['Field']] = [
                        'field' => (string)$row['Field'],
                        'type' => (string)$row['Type'],
                        'collation' => (string)($row['Collation'] ?? ''),
                        'null' => (strtoupper((string)$row['Null']) === 'YES'),
                        'key' => (string)($row['Key'] ?? ''),
                        'default' => $row['Default'],
                        'extra' => (string)($row['Extra'] ?? ''),
                        'comment' => (string)($row['Comment'] ?? ''),
                    ];
                }
            }
        } catch (Throwable $e) {
            $cols = [];
        }
        return $cols;
    }
}

if (!function_exists('db_manager_get_primary_keys')) {
    function db_manager_get_primary_keys(array $columns)
    {
        $pks = [];
        foreach ($columns as $col) {
            if (($col['key'] ?? '') === 'PRI') {
                $pks[] = $col['field'];
            }
        }
        if (!$pks) {
            foreach ($columns as $col) {
                if (($col['key'] ?? '') === 'UNI') {
                    $pks[] = $col['field'];
                }
            }
        }
        if (!$pks && $columns) {
            $first = reset($columns);
            $pks[] = $first['field'];
        }
        return $pks;
    }
}

if (!function_exists('db_manager_browse_table')) {
    function db_manager_browse_table(mysqli $db, $table, array $params, array $columns)
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        if ($safeTable === '') {
            return [
                'rows' => [],
                'totalCount' => 0,
                'page' => 1,
                'perPage' => 25,
                'totalPages' => 1,
                'search' => '',
                'sort' => '',
                'order' => 'ASC',
            ];
        }

        $page = max(1, (int)($params['page'] ?? 1));
        $rawPerPage = (int)($params['per_page'] ?? 25);
        $perPage = in_array($rawPerPage, [10, 25, 50, 100, 250], true) ? $rawPerPage : 25;
        if ($perPage <= 0) {
            $perPage = 25;
        }
        $search = trim((string)($params['search'] ?? ''));
        $sort = trim((string)($params['sort'] ?? ''));
        $order = strtoupper(trim((string)($params['order'] ?? 'ASC'))) === 'DESC' ? 'DESC' : 'ASC';

        if ($sort === '' || !isset($columns[$sort])) {
            $pks = db_manager_get_primary_keys($columns);
            $sort = $pks ? $pks[0] : (array_keys($columns)[0] ?? '');
            $order = 'DESC';
        }

        $whereClauses = [];
        if ($search !== '') {
            $escapedSearch = $db->real_escape_string($search);
            $searchParts = [];
            foreach ($columns as $col) {
                $colName = $col['field'];
                $searchParts[] = "CAST(`{$colName}` AS CHAR) LIKE '%{$escapedSearch}%'";
            }
            if ($searchParts) {
                $whereClauses[] = '(' . implode(' OR ', $searchParts) . ')';
            }
        }

        $whereSql = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        $totalCount = 0;
        try {
            $countRes = $db->query("SELECT COUNT(*) AS total FROM `{$safeTable}` {$whereSql}");
            if ($countRes) {
                $countRow = $countRes->fetch_assoc();
                $totalCount = (int)($countRow['total'] ?? 0);
            }
        } catch (Throwable $e) {
            $totalCount = 0;
        }

        $totalPages = max(1, (int)ceil($totalCount / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $orderSql = '';
        if ($sort !== '' && isset($columns[$sort])) {
            $safeSortCol = preg_replace('/[^a-zA-Z0-9_]/', '', $sort);
            $orderSql = "ORDER BY `{$safeSortCol}` {$order}";
        }

        $rows = [];
        try {
            $query = "SELECT * FROM `{$safeTable}` {$whereSql} {$orderSql} LIMIT {$offset}, {$perPage}";
            $res = $db->query($query);
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $rows[] = $r;
                }
            }
        } catch (Throwable $e) {
            $rows = [];
        }

        return [
            'rows' => $rows,
            'totalCount' => $totalCount,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'search' => $search,
            'sort' => $sort,
            'order' => $order,
        ];
    }
}

if (!function_exists('db_manager_get_row_by_pk')) {
    function db_manager_get_row_by_pk(mysqli $db, $table, array $pkValues, array $columns)
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        if ($safeTable === '' || empty($pkValues)) {
            return null;
        }

        $whereParts = [];
        foreach ($pkValues as $col => $val) {
            if (!isset($columns[$col])) {
                continue;
            }
            $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
            $escapedVal = $db->real_escape_string((string)$val);
            $whereParts[] = "`{$safeCol}` = '{$escapedVal}'";
        }

        if (!$whereParts) {
            return null;
        }

        try {
            $query = "SELECT * FROM `{$safeTable}` WHERE " . implode(' AND ', $whereParts) . " LIMIT 1";
            $res = $db->query($query);
            if ($res && $res->num_rows > 0) {
                return $res->fetch_assoc();
            }
        } catch (Throwable $e) {
            return null;
        }
        return null;
    }
}

if (!function_exists('db_manager_insert_row')) {
    function db_manager_insert_row(mysqli $db, $table, array $postData, array $columns)
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        if ($safeTable === '') {
            return ['success' => false, 'error' => 'Nombre de tabla inválido.'];
        }

        $fields = [];
        $values = [];

        foreach ($columns as $colName => $col) {
            $isAutoInc = (stripos($col['extra'], 'auto_increment') !== false);
            $isNullChecked = !empty($postData['null_flags'][$colName]);
            $hasVal = array_key_exists($colName, $postData);

            if ($isAutoInc && (!$hasVal || trim((string)$postData[$colName]) === '')) {
                continue;
            }

            $rawVal = $hasVal ? (string)$postData[$colName] : '';

            // Hashing especial para password_hash si no viene ya hasheado
            if ($colName === 'password_hash' && $rawVal !== '') {
                if (!preg_match('/^\$2[ayb]\$.{56}$/', $rawVal)) {
                    $rawVal = password_hash($rawVal, PASSWORD_DEFAULT);
                }
            }

            $fields[] = "`" . preg_replace('/[^a-zA-Z0-9_]/', '', $colName) . "`";

            if ($isNullChecked && $col['null']) {
                $values[] = "NULL";
            } elseif ($rawVal === '' && $col['null']) {
                $values[] = "NULL";
            } else {
                $values[] = "'" . $db->real_escape_string($rawVal) . "'";
            }
        }

        if (!$fields) {
            return ['success' => false, 'error' => 'No hay campos para insertar.'];
        }

        $sql = "INSERT INTO `{$safeTable}` (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
        try {
            if ($db->query($sql)) {
                return ['success' => true, 'insertId' => $db->insert_id];
            } else {
                return ['success' => false, 'error' => $db->error ?: 'Error en la consulta SQL.'];
            }
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('db_manager_update_row')) {
    function db_manager_update_row(mysqli $db, $table, array $pkValues, array $postData, array $columns)
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        if ($safeTable === '' || empty($pkValues)) {
            return ['success' => false, 'error' => 'Faltan parámetros de clave primaria.'];
        }

        $setParts = [];

        foreach ($columns as $colName => $col) {
            $isAutoInc = (stripos($col['extra'], 'auto_increment') !== false);
            if ($isAutoInc) {
                continue;
            }

            $isNullChecked = !empty($postData['null_flags'][$colName]);
            $hasVal = array_key_exists($colName, $postData);
            if (!$hasVal && !$isNullChecked) {
                continue;
            }

            $rawVal = $hasVal ? (string)$postData[$colName] : '';

            // Si es password_hash y viene vacío, no sobreescribir la contraseña
            if ($colName === 'password_hash' && $rawVal === '') {
                continue;
            }

            if ($colName === 'password_hash' && $rawVal !== '') {
                if (!preg_match('/^\$2[ayb]\$.{56}$/', $rawVal)) {
                    $rawVal = password_hash($rawVal, PASSWORD_DEFAULT);
                }
            }

            $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $colName);

            if ($isNullChecked && $col['null']) {
                $setParts[] = "`{$safeCol}` = NULL";
            } elseif ($rawVal === '' && $col['null']) {
                $setParts[] = "`{$safeCol}` = NULL";
            } else {
                $escapedVal = $db->real_escape_string($rawVal);
                $setParts[] = "`{$safeCol}` = '{$escapedVal}'";
            }
        }

        if (!$setParts) {
            return ['success' => false, 'error' => 'No hay campos modificados para actualizar.'];
        }

        $whereParts = [];
        foreach ($pkValues as $col => $val) {
            $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
            $escapedVal = $db->real_escape_string((string)$val);
            $whereParts[] = "`{$safeCol}` = '{$escapedVal}'";
        }

        $sql = "UPDATE `{$safeTable}` SET " . implode(', ', $setParts) . " WHERE " . implode(' AND ', $whereParts) . " LIMIT 1";

        try {
            if ($db->query($sql)) {
                return ['success' => true];
            } else {
                return ['success' => false, 'error' => $db->error ?: 'Error al actualizar registro.'];
            }
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('db_manager_delete_row')) {
    function db_manager_delete_row(mysqli $db, $table, array $pkValues, array $columns)
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        if ($safeTable === '' || empty($pkValues)) {
            return ['success' => false, 'error' => 'Parámetros de clave primaria inválidos.'];
        }

        $whereParts = [];
        foreach ($pkValues as $col => $val) {
            $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
            $escapedVal = $db->real_escape_string((string)$val);
            $whereParts[] = "`{$safeCol}` = '{$escapedVal}'";
        }

        $sql = "DELETE FROM `{$safeTable}` WHERE " . implode(' AND ', $whereParts) . " LIMIT 1";

        try {
            if ($db->query($sql)) {
                return ['success' => true];
            } else {
                return ['success' => false, 'error' => $db->error ?: 'Error al eliminar registro.'];
            }
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('db_manager_truncate_table')) {
    function db_manager_truncate_table(mysqli $db, $table)
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        if ($safeTable === '') {
            return ['success' => false, 'error' => 'Nombre de tabla inválido.'];
        }

        try {
            $res = $db->query("TRUNCATE TABLE `{$safeTable}`");
            if ($res) {
                return ['success' => true];
            }
        } catch (Throwable $e) {
            // Intentar con DELETE si las llaves foráneas bloquean TRUNCATE
            try {
                $resDel = $db->query("DELETE FROM `{$safeTable}`");
                if ($resDel) {
                    return ['success' => true];
                }
            } catch (Throwable $e2) {
                return ['success' => false, 'error' => $e2->getMessage()];
            }
        }

        return ['success' => false, 'error' => $db->error ?: 'No se pudo vaciar la tabla.'];
    }
}

if (!function_exists('db_manager_build_data')) {
    function db_manager_build_data(mysqli $db, array $permissions)
    {
        $messages = [];
        $errors = [];
        $canManage = (bool)($permissions['canManage'] ?? false);
        $canDelete = (bool)($permissions['canDelete'] ?? false);

        if (!$canManage) {
            $errors[] = "No tienes permisos de administrador para gestionar la base de datos.";
            return [
                'messages' => $messages,
                'errors' => $errors,
                'tables' => [],
                'activeTable' => '',
                'columns' => [],
                'primaryKeys' => [],
                'browseData' => [],
                'editRow' => null,
                'action' => 'browse',
                'canManage' => false,
                'canDelete' => false,
            ];
        }

        $tables = db_manager_get_tables($db);
        $availableTableNames = array_column($tables, 'name');

        $activeTable = trim((string)($_GET['table'] ?? ''));
        if ($activeTable === '' || !in_array($activeTable, $availableTableNames, true)) {
            $activeTable = $availableTableNames[0] ?? '';
        }

        $columns = [];
        $primaryKeys = [];
        if ($activeTable !== '') {
            $columns = db_manager_get_table_columns($db, $activeTable);
            $primaryKeys = db_manager_get_primary_keys($columns);
        }

        $action = trim((string)($_GET['action'] ?? 'browse'));
        $editRow = null;

        // Procesar solicitudes POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formAction = trim((string)($_POST['form_action'] ?? ''));

            if ($formAction === 'insert' && $activeTable !== '') {
                $insertRes = db_manager_insert_row($db, $activeTable, $_POST, $columns);
                if ($insertRes['success']) {
                    $messages[] = "Registro insertado exitosamente en `{$activeTable}`.";
                    $action = 'browse';
                } else {
                    $errors[] = "Error al insertar registro: " . $insertRes['error'];
                    $action = 'create';
                }
            } elseif ($formAction === 'update' && $activeTable !== '') {
                $pkValues = is_array($_POST['pk_values'] ?? null) ? $_POST['pk_values'] : [];
                $updateRes = db_manager_update_row($db, $activeTable, $pkValues, $_POST, $columns);
                if ($updateRes['success']) {
                    $messages[] = "Registro actualizado exitosamente en `{$activeTable}`.";
                    $action = 'browse';
                } else {
                    $errors[] = "Error al actualizar registro: " . $updateRes['error'];
                    $action = 'edit';
                    $editRow = db_manager_get_row_by_pk($db, $activeTable, $pkValues, $columns);
                }
            } elseif ($formAction === 'delete' && $activeTable !== '') {
                if (!$canDelete) {
                    $errors[] = "No tienes permisos para eliminar registros.";
                } else {
                    $pkValues = is_array($_POST['pk_values'] ?? null) ? $_POST['pk_values'] : [];
                    $delRes = db_manager_delete_row($db, $activeTable, $pkValues, $columns);
                    if ($delRes['success']) {
                        $messages[] = "Registro eliminado correctamente de `{$activeTable}`.";
                    } else {
                        $errors[] = "Error al eliminar registro: " . $delRes['error'];
                    }
                }
                $action = 'browse';
            } elseif ($formAction === 'truncate' && $activeTable !== '') {
                if (!$canDelete) {
                    $errors[] = "No tienes permisos para vaciar la tabla.";
                } else {
                    $truncRes = db_manager_truncate_table($db, $activeTable);
                    if ($truncRes['success']) {
                        $messages[] = "Todos los registros de la tabla `{$activeTable}` han sido vaciados correctamente.";
                    } else {
                        $errors[] = "Error al vaciar la tabla: " . $truncRes['error'];
                    }
                }
                $action = 'browse';
            }
        }

        // Si la acción es editar por GET, cargar el registro
        if ($action === 'edit' && $activeTable !== '' && $editRow === null) {
            $pkValues = is_array($_GET['pk_values'] ?? null) ? $_GET['pk_values'] : [];
            if ($pkValues) {
                $editRow = db_manager_get_row_by_pk($db, $activeTable, $pkValues, $columns);
            }
            if (!$editRow) {
                $errors[] = "No se encontró el registro solicitado para edición.";
                $action = 'browse';
            }
        }

        // Cargar registros para browse
        $browseData = [];
        if ($activeTable !== '' && $columns) {
            $browseData = db_manager_browse_table($db, $activeTable, $_GET, $columns);
        }

        return [
            'messages' => $messages,
            'errors' => $errors,
            'tables' => $tables,
            'activeTable' => $activeTable,
            'columns' => $columns,
            'primaryKeys' => $primaryKeys,
            'browseData' => $browseData,
            'editRow' => $editRow,
            'action' => $action,
            'canManage' => $canManage,
            'canDelete' => $canDelete,
        ];
    }
}

