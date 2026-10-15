<?php

if (!function_exists('sedes_store_uploaded_images')) {
    function sedes_store_uploaded_images(array $fileBag, $subdir = 'sedes')
    {
        $items = [];
        if (empty($fileBag) || !isset($fileBag['name'])) {
            return $items;
        }

        $names = is_array($fileBag['name']) ? $fileBag['name'] : [$fileBag['name']];
        $tmpNames = is_array($fileBag['tmp_name'] ?? null) ? $fileBag['tmp_name'] : [($fileBag['tmp_name'] ?? '')];
        $errors = is_array($fileBag['error'] ?? null) ? $fileBag['error'] : [($fileBag['error'] ?? UPLOAD_ERR_NO_FILE)];
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        $root = dirname(__DIR__, 2);
        $relativeDir = 'uploads/' . trim((string)$subdir, '/');
        $targetDir = $root . '/' . $relativeDir;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
            return $items;
        }

        foreach ($names as $idx => $originalName) {
            $error = (int)($errors[$idx] ?? UPLOAD_ERR_NO_FILE);
            if ($error !== UPLOAD_ERR_OK) {
                continue;
            }
            $tmpFile = (string)($tmpNames[$idx] ?? '');
            if ($tmpFile === '') {
                continue;
            }
            $ext = strtolower(pathinfo((string)$originalName, PATHINFO_EXTENSION));
            if ($ext === '' || !in_array($ext, $allowed, true)) {
                continue;
            }
            $safeName = uniqid('sede_', true) . '.' . $ext;
            $targetPath = $targetDir . '/' . $safeName;
            if (!move_uploaded_file($tmpFile, $targetPath)) {
                continue;
            }
            $items[] = [
                'path' => $relativeDir . '/' . $safeName,
                'original_name' => (string)$originalName,
            ];
        }

        return $items;
    }
}

if (!function_exists('sedes_uploaded_file_count')) {
    function sedes_uploaded_file_count(array $fileBag)
    {
        if (empty($fileBag) || !isset($fileBag['name'])) {
            return 0;
        }
        if (!is_array($fileBag['name'])) {
            return trim((string)$fileBag['name']) === '' ? 0 : 1;
        }
        $count = 0;
        foreach ($fileBag['name'] as $name) {
            if (trim((string)$name) !== '') {
                $count++;
            }
        }
        return $count;
    }
}

if (!function_exists('sedes_delete_uploaded_file')) {
    function sedes_delete_uploaded_file($relativePath)
    {
        $relativePath = trim((string)$relativePath);
        if ($relativePath === '') {
            return;
        }
        $root = dirname(__DIR__, 2);
        $absolutePath = $root . '/' . ltrim($relativePath, '/');
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}

if (!function_exists('sedes_build_data')) {
    function sedes_build_data(mysqli $db, array $permissions)
    {
        $messages = [];
        $errors = [];
        $draft = [];
        $editDraft = [];
        $formType = $_POST['form_type'] ?? '';
        $canManage = (bool)($permissions['canManage'] ?? false);
        $canDelete = (bool)($permissions['canDelete'] ?? false);
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $missingTables = [];
        foreach (['sedes_capacitaciones', 'sedes_capacitaciones_fotos'] as $requiredTable) {
            if (!function_exists('db_table_exists') || !db_table_exists($db, $requiredTable)) {
                $missingTables[] = $requiredTable;
            }
        }
        if ($missingTables) {
            $errors[] = "Faltan tablas de sedes en la base de datos (" . implode(', ', $missingTables) . "). Ejecuta el instalador para completar el esquema.";
            return [
                'messages' => $messages,
                'errors' => $errors,
                'canManage' => $canManage,
                'canDelete' => $canDelete,
                'rows' => [],
                'viewId' => 0,
                'viewSede' => null,
                'viewFotos' => [],
                'filterName' => '',
                'filterRegion' => '',
                'filterExtranjero' => '',
                'draft' => [],
                'editDraft' => [],
            ];
        }

        $viewId = (int)($_GET['view'] ?? 0);
        if ($requestMethod === 'POST') {
            if (!$canManage) {
                $errors[] = "No tienes permisos para gestionar sedes.";
            } elseif ($formType === 'sede_create' || $formType === 'sede_update') {
                $isUpdate = $formType === 'sede_update';
                $sedeId = (int)($_POST['sede_id'] ?? 0);
                $encargado = trim((string)($_POST['encargado'] ?? ''));
                $contacto = trim((string)($_POST['contacto'] ?? ''));

                // CORRECCIÓN: Validamos el valor real del select en lugar de solo su existencia
                $extranjero = isset($_POST['extranjero']) && $_POST['extranjero'] === '1' ? 1 : 0;

                $region = trim((string)($_POST['region'] ?? ''));
                $comuna = trim((string)($_POST['comuna'] ?? ''));
                $comunaOtra = trim((string)($_POST['comuna_otra'] ?? ''));
                if ($comuna === 'otra' && $comunaOtra !== '') {
                    $comuna = $comunaOtra;
                }
                $ubicacionExtranjero = trim((string)($_POST['ubicacion_extranjero'] ?? ''));
                $direccion = trim((string)($_POST['direccion'] ?? ''));
                $capacidadPersonas = (int)($_POST['capacidad_personas'] ?? 0);
                if ($capacidadPersonas < 0) {
                    $capacidadPersonas = 0;
                }

                $draftData = [
                    'encargado' => $encargado,
                    'contacto' => $contacto,
                    'extranjero' => $extranjero ? '1' : '0', // Mantiene la consistencia del select al recargar
                    'region' => $region,
                    'comuna' => $comuna,
                    'comuna_otra' => $comunaOtra,
                    'ubicacion_extranjero' => $ubicacionExtranjero,
                    'direccion' => $direccion,
                    'capacidad_personas' => (string)$capacidadPersonas,
                ];
                if ($isUpdate) {
                    $editDraft = $draftData;
                    $viewId = $sedeId;
                } else {
                    $draft = $draftData;
                }

                if ($encargado === '') {
                    $errors[] = "El encargado es obligatorio.";
                }
                if ($extranjero) {
                    if ($ubicacionExtranjero === '') {
                        $errors[] = "Debes ingresar pais o ubicacion para una sede extranjera.";
                    }
                    $region = 'Extranjero';
                    $comuna = $ubicacionExtranjero;
                } else {
                    $ubicacionExtranjero = '';
                    if ($region === '') {
                        $errors[] = "Debes seleccionar una region.";
                    }
                    if ($comuna === '') {
                        $errors[] = "Debes seleccionar una comuna.";
                    }
                }

                if ($isUpdate && $sedeId <= 0) {
                    $errors[] = "Sede no valida para edicion.";
                }

                if (!$errors) {
                    if ($isUpdate) {
                        $stmt = $db->prepare("UPDATE sedes_capacitaciones
                            SET encargado = ?, contacto = ?, extranjero = ?, region = ?, comuna = ?, ubicacion_extranjero = ?, direccion = ?, capacidad_personas = ?, updated_at = NOW()
                            WHERE id = ? LIMIT 1");
                        $stmt->bind_param('ssissssii', $encargado, $contacto, $extranjero, $region, $comuna, $ubicacionExtranjero, $direccion, $capacidadPersonas, $sedeId);
                        if ($stmt->execute()) {
                            $messages[] = "Sede actualizada correctamente.";
                        } else {
                            $errors[] = "No se pudo actualizar la sede.";
                        }
                    } else {
                        $stmt = $db->prepare("INSERT INTO sedes_capacitaciones
                            (encargado, contacto, extranjero, region, comuna, ubicacion_extranjero, direccion, capacidad_personas, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                        $stmt->bind_param('ssissssi', $encargado, $contacto, $extranjero, $region, $comuna, $ubicacionExtranjero, $direccion, $capacidadPersonas);
                        if ($stmt->execute()) {
                            $sedeId = (int)$stmt->insert_id;
                            $viewId = $sedeId;
                            $messages[] = "Sede creada correctamente.";
                            $draft = [];
                        } else {
                            $errors[] = "No se pudo crear la sede.";
                        }
                    }
                }

                if (!$errors && $sedeId > 0) {
                    $uploadedItems = sedes_store_uploaded_images($_FILES['fotos'] ?? [], 'sedes');
                    $uploadedCount = sedes_uploaded_file_count($_FILES['fotos'] ?? []);
                    if ($uploadedCount > 0 && !$uploadedItems) {
                        $errors[] = "No se pudieron subir fotos validas (formatos permitidos: jpg, jpeg, png, webp, gif).";
                    } else {
                        foreach ($uploadedItems as $item) {
                            $photoStmt = $db->prepare("INSERT INTO sedes_capacitaciones_fotos (sede_id, ruta, original_name, created_at) VALUES (?, ?, ?, NOW())");
                            $photoStmt->bind_param('iss', $sedeId, $item['path'], $item['original_name']);
                            $photoStmt->execute();
                        }
                        if ($uploadedItems) {
                            $messages[] = "Se agregaron " . count($uploadedItems) . " foto(s) a la sede.";
                        }
                    }
                }
            } elseif ($formType === 'sede_foto_delete') {
                $sedeId = (int)($_POST['sede_id'] ?? 0);
                $photoId = (int)($_POST['photo_id'] ?? 0);
                $viewId = $sedeId;
                if ($photoId > 0 && $sedeId > 0) {
                    $photoStmt = $db->prepare("SELECT ruta FROM sedes_capacitaciones_fotos WHERE id = ? AND sede_id = ? LIMIT 1");
                    $photoStmt->bind_param('ii', $photoId, $sedeId);
                    $photoStmt->execute();
                    $photoRow = $photoStmt->get_result()->fetch_assoc();
                    if ($photoRow) {
                        $deleteStmt = $db->prepare("DELETE FROM sedes_capacitaciones_fotos WHERE id = ? LIMIT 1");
                        $deleteStmt->bind_param('i', $photoId);
                        if ($deleteStmt->execute()) {
                            sedes_delete_uploaded_file($photoRow['ruta'] ?? '');
                            $messages[] = "Foto eliminada.";
                        } else {
                            $errors[] = "No se pudo eliminar la foto.";
                        }
                    }
                }
            } elseif ($formType === 'sede_delete') {
                if (!$canDelete) {
                    $errors[] = "No tienes permisos para eliminar sedes.";
                } else {
                    $sedeId = (int)($_POST['sede_id'] ?? 0);
                    if ($sedeId > 0) {
                        $photoRows = [];
                        $photoRes = $db->query("SELECT ruta FROM sedes_capacitaciones_fotos WHERE sede_id = " . (int)$sedeId);
                        if ($photoRes) {
                            while ($row = $photoRes->fetch_assoc()) {
                                $photoRows[] = $row;
                            }
                        }
                        $deleteStmt = $db->prepare("DELETE FROM sedes_capacitaciones WHERE id = ? LIMIT 1");
                        $deleteStmt->bind_param('i', $sedeId);
                        if ($deleteStmt->execute()) {
                            foreach ($photoRows as $row) {
                                sedes_delete_uploaded_file($row['ruta'] ?? '');
                            }
                            $messages[] = "Sede eliminada.";
                            $viewId = 0;
                        } else {
                            $errors[] = "No se pudo eliminar la sede.";
                        }
                    }
                }
            }
        }

        $filterName = trim((string)($_GET['q'] ?? ''));
        $filterRegion = trim((string)($_GET['region'] ?? ''));
        $filterExtranjero = trim((string)($_GET['extranjero'] ?? ''));
        $filters = [];
        $params = [];
        $types = '';

        if ($filterName !== '') {
            $filters[] = "(s.encargado LIKE ? OR s.contacto LIKE ? OR s.direccion LIKE ?)";
            $params[] = '%' . $filterName . '%';
            $params[] = '%' . $filterName . '%';
            $params[] = '%' . $filterName . '%';
            $types .= 'sss';
        }
        if ($filterRegion !== '') {
            $filters[] = "s.region LIKE ?";
            $params[] = '%' . $filterRegion . '%';
            $types .= 's';
        }
        if ($filterExtranjero !== '' && in_array($filterExtranjero, ['si', 'no'], true)) {
            $filters[] = "s.extranjero = ?";
            $params[] = $filterExtranjero === 'si' ? 1 : 0;
            $types .= 'i';
        }
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        $sql = "SELECT s.*,
            (SELECT COUNT(*) FROM sedes_capacitaciones_fotos f WHERE f.sede_id = s.id) AS fotos_count
            FROM sedes_capacitaciones s";
        if ($filters) {
            $sql .= " WHERE " . implode(" AND ", $filters);
        }
        $sql .= " ORDER BY COALESCE(s.updated_at, s.created_at) DESC, s.id DESC LIMIT 300";

        $stmt = $db->prepare($sql);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $rows = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $viewSede = null;
        $viewFotos = [];
        if ($viewId > 0) {
            $detailStmt = $db->prepare("SELECT * FROM sedes_capacitaciones WHERE id = ? LIMIT 1");
            $detailStmt->bind_param('i', $viewId);
            $detailStmt->execute();
            $viewSede = $detailStmt->get_result()->fetch_assoc();

            if ($viewSede) {
                $photoStmt = $db->prepare("SELECT id, ruta, original_name, created_at
                    FROM sedes_capacitaciones_fotos
                    WHERE sede_id = ?
                    ORDER BY created_at DESC, id DESC");
                $photoStmt->bind_param('i', $viewId);
                $photoStmt->execute();
                $photoRes = $photoStmt->get_result();
                while ($row = $photoRes->fetch_assoc()) {
                    $viewFotos[] = $row;
                }
            }
        }
/////////////////////////////////////////////////////////////////////////////////////////////////////
        return [
            'messages' => $messages,
            'errors' => $errors,
            'canManage' => $canManage,
            'canDelete' => $canDelete,
            'rows' => $rows,
            'viewId' => $viewId,
            'viewSede' => $viewSede,
            'viewFotos' => $viewFotos,
            'filterName' => $filterName,
            'filterRegion' => $filterRegion,
            'filterExtranjero' => $filterExtranjero,
            'draft' => $draft,
            'editDraft' => $editDraft,
        ];
    }
}
