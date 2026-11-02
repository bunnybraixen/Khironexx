<?php

if (!function_exists('postulantes_fetch_select_options')) {
    function postulantes_fetch_select_options(mysqli $db, $column, array $defaults = [])
    {
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$column);
        if ($column === '') {
            return $defaults;
        }

        $options = [];
        $pushOption = function ($value) use (&$options) {
            $value = trim((string)$value);
            if ($value !== '' && !in_array($value, $options, true)) {
                $options[] = $value;
            }
        };

        foreach ($defaults as $value) {
            $pushOption($value);
        }

        foreach (['postulantes', 'practicantes'] as $table) {
            $tableColumns = db_table_columns($db, $table);
            if (!in_array($column, $tableColumns, true)) {
                continue;
            }
            $result = $db->query("SELECT DISTINCT " . $column . " AS value
                FROM " . $table . "
                WHERE " . $column . " IS NOT NULL AND TRIM(" . $column . ") <> ''
                ORDER BY " . $column . " ASC
                LIMIT 300");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $pushOption($row['value'] ?? '');
                }
            }
        }

        return $options;
    }
}

if (!function_exists('postulantes_build_data')) {
    function postulantes_build_data(mysqli $db, array $permissions)
    {
        $postulanteStatuses = [
            'registrado' => 'Postulante registrado',
            'psicolaboral' => 'Entrevista psicolaboral',
            'entrevista_area' => 'Entrevista de area',
            'seleccionado' => 'Postulante seleccionado',
            'no_seleccionado' => 'Postulante no seleccionado',
        ];
        $messages = [];
        $errors = [];
        $formType = $_POST['form_type'] ?? '';
        $canAdd = $permissions['canAdd'] ?? false;
        $canModify = $permissions['canModify'] ?? false;
        $canDelete = $permissions['canDelete'] ?? false;
        $userId = $permissions['userId'] ?? null;
        $requestedModule = trim((string)($_REQUEST['ui_module'] ?? ''));
        $activeModule = in_array($requestedModule, ['registrar', 'buscar'], true) ? $requestedModule : 'registrar';
        $registerDraft = [];
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $defaultCarreras = [
            'Administracion',
            'Comercial',
            'Contabilidad',
            'Derecho',
            'Diseno',
            'Informatica',
            'Ingenieria Civil',
            'Ingenieria Comercial',
            'Psicologia',
            'Recursos Humanos',
        ];
        $defaultUniversidades = [
            'Duoc UC',
            'INACAP',
            'Pontificia Universidad Catolica de Chile',
            'Universidad Andres Bello',
            'Universidad de Chile',
            'Universidad de Santiago de Chile',
            'Universidad Diego Portales',
            'Universidad Tecnica Federico Santa Maria',
        ];
        $carreras = postulantes_fetch_select_options($db, 'carrera', $defaultCarreras);
        $universidades = postulantes_fetch_select_options($db, 'universidad', $defaultUniversidades);
        $departments = [];
        if (function_exists('db_table_exists') && db_table_exists($db, 'departamentos')) {
            $departmentsResult = $db->query("SELECT id, name FROM departamentos WHERE name IS NOT NULL AND TRIM(name) <> '' ORDER BY name ASC");
            if ($departmentsResult) {
                while ($departmentRow = $departmentsResult->fetch_assoc()) {
                    $departments[] = [
                        'id' => (int)($departmentRow['id'] ?? 0),
                        'name' => trim((string)($departmentRow['name'] ?? '')),
                    ];
                }
            }
        }

        foreach (['q', 'universidad', 'carrera', 'area', 'status'] as $key) {
            if (isset($_GET[$key]) && trim((string)$_GET[$key]) !== '') {
                $activeModule = 'buscar';
                break;
            }
        }
        if ((int)($_GET['view'] ?? 0) > 0) {
            $activeModule = 'buscar';
        }

        if ($requestMethod === 'POST') {
            if ($formType === 'postulante_create') {
                $activeModule = 'registrar';
            } else {
                $activeModule = 'buscar';
            }

            if ($formType === 'postulante_create') {
                if (!$canAdd) {
                    $errors[] = "No tienes permisos para crear postulantes.";
                } else {
                $fullName = trim((string)($_POST['full_name'] ?? ''));
                $email = trim((string)($_POST['email'] ?? ''));
                $telefono = trim((string)($_POST['telefono'] ?? ''));
                $carreraRaw = trim((string)($_POST['carrera'] ?? ''));
                $carreraOtra = trim((string)($_POST['carrera_otra'] ?? ''));
                $carrera = $carreraRaw === 'otro' ? $carreraOtra : $carreraRaw;
                $universidadRaw = trim((string)($_POST['universidad'] ?? ''));
                $universidadOtra = trim((string)($_POST['universidad_otra'] ?? ''));
                $universidad = $universidadRaw === 'otro' ? $universidadOtra : $universidadRaw;
                $jefeCarrera = trim((string)($_POST['jefe_carrera'] ?? ''));
                $jefeCarreraEmail = trim((string)($_POST['jefe_carrera_email'] ?? ''));
                $horasExigidas = (int)($_POST['horas_exigidas'] ?? 0);
                $areaPostulacion = trim((string)($_POST['area_postulacion'] ?? ''));
                $validDepartment = false;
                foreach ($departments as $departmentOption) {
                    if ($departmentOption['name'] === $areaPostulacion) {
                        $validDepartment = true;
                        break;
                    }
                }
                if ($areaPostulacion !== '' && !$validDepartment) {
                    $errors[] = "Debes seleccionar un área válida desde Departamentos.";
                }
                $registerDraft = [
                    'full_name' => $fullName,
                    'email' => $email,
                    'telefono' => $telefono,
                    'carrera' => $carreraRaw,
                    'carrera_otra' => $carreraRaw === 'otro' ? $carrera : '',
                    'universidad' => $universidadRaw,
                    'universidad_otra' => $universidadRaw === 'otro' ? $universidad : '',
                    'jefe_carrera' => $jefeCarrera,
                    'jefe_carrera_email' => $jefeCarreraEmail,
                    'horas_exigidas' => (string)$horasExigidas,
                    'area_postulacion' => $areaPostulacion,
                ];
                if ($fullName === '') {
                    $errors[] = "El nombre completo es obligatorio.";
                }
                if ($carreraRaw === 'otro' && $carrera === '') {
                    $errors[] = "Debes indicar la carrera.";
                }
                if ($universidadRaw === 'otro' && $universidad === '') {
                    $errors[] = "Debes indicar la universidad.";
                }
                if (!$errors && $email !== '') {
                    $check = $db->prepare("SELECT id FROM postulantes WHERE email = ? LIMIT 1");
                    $check->bind_param('s', $email);
                    $check->execute();
                    if ($check->get_result()->fetch_assoc()) {
                        $errors[] = "Ya existe un postulante con ese correo.";
                    }
                }
                if (!$errors) {
                    $stmt = $db->prepare("INSERT INTO postulantes (full_name, email, telefono, carrera, universidad, jefe_carrera, jefe_carrera_email, horas_exigidas, area_postulacion, status, status_updated_at, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'registrado', NOW(), NOW())");
                    $stmt->bind_param('sssssssis', $fullName, $email, $telefono, $carrera, $universidad, $jefeCarrera, $jefeCarreraEmail, $horasExigidas, $areaPostulacion);
                    if ($stmt->execute()) {
                        $postulanteId = $stmt->insert_id;
                        $hist = $db->prepare("INSERT INTO postulante_historial (postulante_id, estado, nota, created_at) VALUES (?, 'registrado', 'Registro inicial', NOW())");
                        $hist->bind_param('i', $postulanteId);
                        $hist->execute();
                        $messages[] = "Postulante registrado correctamente.";
                        $registerDraft = [];
                    }
                }
                }
            } elseif ($formType === 'postulante_status') {
                $postulanteId = (int)($_POST['postulante_id'] ?? 0);
                $status = (string)($_POST['status'] ?? 'registrado');
                $nota = trim((string)($_POST['nota'] ?? ''));
                if (!isset($postulanteStatuses[$status])) {
                    $status = 'registrado';
                }
                if ($postulanteId > 0 && $canModify) {
                    $stmt = $db->prepare("UPDATE postulantes SET status = ?, status_updated_at = NOW() WHERE id = ?");
                    $stmt->bind_param('si', $status, $postulanteId);
                    $stmt->execute();
                    $hist = $db->prepare("INSERT INTO postulante_historial (postulante_id, estado, nota, created_at) VALUES (?, ?, ?, NOW())");
                    $hist->bind_param('iss', $postulanteId, $status, $nota);
                    $hist->execute();
                    if ($status === 'seleccionado') {
                        $check = $db->prepare("SELECT id FROM practicantes WHERE postulante_id = ? LIMIT 1");
                        $check->bind_param('i', $postulanteId);
                        $check->execute();
                        $exists = $check->get_result()->fetch_assoc();
                        if (!$exists) {
                            $infoStmt = $db->prepare("SELECT * FROM postulantes WHERE id = ? LIMIT 1");
                            $infoStmt->bind_param('i', $postulanteId);
                            $infoStmt->execute();
                            $postulanteInfo = $infoStmt->get_result()->fetch_assoc();
                            if ($postulanteInfo) {
                                $departmentId = null;
                                if (!empty($postulanteInfo['area_postulacion'])) {
                                    $deptStmt = $db->prepare("SELECT id FROM departamentos WHERE LOWER(name) = LOWER(?) LIMIT 1");
                                    $deptStmt->bind_param('s', $postulanteInfo['area_postulacion']);
                                    $deptStmt->execute();
                                    $deptRow = $deptStmt->get_result()->fetch_assoc();
                                    if ($deptRow) {
                                        $departmentId = (int)$deptRow['id'];
                                    }
                                }
                                $horasExigidas = (int)($postulanteInfo['horas_exigidas'] ?? 0);
                                if ($departmentId > 0) {
                                    $insert = $db->prepare("INSERT INTO practicantes (postulante_id, full_name, email, telefono, department_id, status, created_at, horas_exigidas, carrera, universidad)
                                        VALUES (?, ?, ?, ?, ?, 'active', NOW(), ?, ?, ?)");
                                    $insert->bind_param('isssiiss', $postulanteId, $postulanteInfo['full_name'], $postulanteInfo['email'], $postulanteInfo['telefono'], $departmentId, $horasExigidas, $postulanteInfo['carrera'], $postulanteInfo['universidad']);
                                    $insert->execute();
                                } else {
                                    $insert = $db->prepare("INSERT INTO practicantes (postulante_id, full_name, email, telefono, status, created_at, horas_exigidas, carrera, universidad)
                                        VALUES (?, ?, ?, ?, 'active', NOW(), ?, ?, ?)");
                                    $insert->bind_param('isssiss', $postulanteId, $postulanteInfo['full_name'], $postulanteInfo['email'], $postulanteInfo['telefono'], $horasExigidas, $postulanteInfo['carrera'], $postulanteInfo['universidad']);
                                    $insert->execute();
                                }
                            }
                        }
                    }
                    $messages[] = "Estado actualizado.";
                } elseif ($postulanteId > 0 && $canAdd) {
                    $detail = "Solicitud cambio estado postulante #$postulanteId a '$status'. Nota: $nota";
                    if (function_exists('create_change_request')) {
                        create_change_request($db, $userId, 'postulantes', 'cambio_estado', $detail);
                        $messages[] = "Solicitud enviada al Encargado de RRHH.";
                    } else {
                        $errors[] = "No se pudo registrar la solicitud.";
                    }
                } else {
                    $errors[] = "No tienes permisos para cambiar el estado.";
                }
            } elseif ($formType === 'postulante_entrevista') {
                $postulanteId = (int)($_POST['postulante_id'] ?? 0);
                $tipo = trim((string)($_POST['tipo'] ?? ''));
                $fecha = trim((string)($_POST['fecha'] ?? ''));
                $observaciones = trim((string)($_POST['observaciones'] ?? ''));
                if ($postulanteId > 0 && $tipo !== '' && $canAdd) {
                    $stmt = $db->prepare("INSERT INTO postulante_entrevistas (postulante_id, tipo, fecha, observaciones, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->bind_param('isss', $postulanteId, $tipo, $fecha, $observaciones);
                    $stmt->execute();
                    $messages[] = "Entrevista registrada.";
                } elseif (!$canAdd) {
                    $errors[] = "No tienes permisos para registrar entrevistas.";
                }
            } elseif ($formType === 'postulante_documento') {
                $postulanteId = (int)($_POST['postulante_id'] ?? 0);
                $titulo = trim((string)($_POST['titulo'] ?? ''));
                $tipo = trim((string)($_POST['tipo'] ?? ''));
                $ruta = trim((string)($_POST['ruta'] ?? ''));
                if ($postulanteId > 0 && $titulo !== '' && $canAdd) {
                    $stmt = $db->prepare("INSERT INTO documentos (target_type, target_id, titulo, tipo, ruta, created_at) VALUES ('postulante', ?, ?, ?, ?, NOW())");
                    $stmt->bind_param('isss', $postulanteId, $titulo, $tipo, $ruta);
                    $stmt->execute();
                    $messages[] = "Documento asociado.";
                } elseif (!$canAdd) {
                    $errors[] = "No tienes permisos para agregar documentos.";
                }
            } elseif ($formType === 'postulante_observacion') {
                $postulanteId = (int)($_POST['postulante_id'] ?? 0);
                $detalle = trim((string)($_POST['detalle'] ?? ''));
                if ($postulanteId > 0 && $detalle !== '' && $canAdd) {
                    $stmt = $db->prepare("INSERT INTO observaciones (target_type, target_id, detalle, created_at) VALUES ('postulante', ?, ?, NOW())");
                    $stmt->bind_param('is', $postulanteId, $detalle);
                    $stmt->execute();
                    $messages[] = "Observacion registrada.";
                } elseif (!$canAdd) {
                    $errors[] = "No tienes permisos para agregar observaciones.";
                }
            } elseif ($formType === 'postulante_delete') {
                $postulanteId = (int)($_POST['postulante_id'] ?? 0);
                $confirmDelete = trim((string)($_POST['confirm_delete'] ?? ''));
                if ($postulanteId > 0 && $confirmDelete === 'si' && $canDelete) {
                    $deleteStmt = $db->prepare("DELETE FROM postulantes WHERE id = ?");
                    $deleteStmt->bind_param('i', $postulanteId);
                    if ($deleteStmt->execute()) {
                        $messages[] = "Postulante eliminado correctamente.";
                        $viewId = 0;
                    } else {
                        $errors[] = "Error al eliminar el postulante.";
                    }
                } elseif ($postulanteId > 0 && $confirmDelete === 'si' && $canAdd) {
                    $detail = "Solicitud de eliminacion postulante #$postulanteId";
                    if (function_exists('create_change_request')) {
                        create_change_request($db, $userId, 'postulantes', 'eliminacion', $detail);
                        $messages[] = "Solicitud enviada al Encargado de RRHH.";
                    } else {
                        $errors[] = "No se pudo registrar la solicitud.";
                    }
                }
            } elseif ($formType === 'postulante_update') {
                $postulanteId=(int)($_POST['postulante_id']??0);$fullName=trim((string)($_POST['full_name']??''));$email=trim((string)($_POST['email']??''));$telefono=trim((string)($_POST['telefono']??''));$carrera=trim((string)($_POST['carrera']??''));$universidad=trim((string)($_POST['universidad']??''));$area=trim((string)($_POST['area_postulacion']??''));$horas=max(0,(int)($_POST['horas_exigidas']??0));
                if($postulanteId<=0||$fullName===''){$errors[]="Datos del postulante incompletos.";}elseif(!$canModify){$errors[]="No tienes permisos para editar postulantes.";}else{$stmt=$db->prepare("UPDATE postulantes SET full_name=?,email=?,telefono=?,carrera=?,universidad=?,area_postulacion=?,horas_exigidas=?,status_updated_at=NOW() WHERE id=? LIMIT 1");$stmt->bind_param('ssssssii', $fullName, $email, $telefono, $carrera, $universidad, $area, $horas, $postulanteId);if($stmt->execute())$messages[]="Postulante actualizado correctamente.";else $errors[]="No se pudo actualizar el postulante.";}
            } elseif ($formType === 'postulante_accept') {
                $postulanteId=(int)($_POST['postulante_id']??0);$fechaInicio=trim((string)($_POST['fecha_inicio']??''));$fechaFin=trim((string)($_POST['fecha_fin']??''));$fechaCumpleanos=trim((string)($_POST['fecha_cumpleanos']??''));
                if($postulanteId<=0||$fechaInicio===''||$fechaFin===''||$fechaCumpleanos===''){$errors[]="Para aceptar debes indicar fecha de inicio, término y cumpleaños.";}elseif(!$canModify){$errors[]="No tienes permisos para aceptar postulantes.";}else{$s=$db->prepare("SELECT * FROM postulantes WHERE id=? LIMIT 1");$s->bind_param('i',$postulanteId);$s->execute();$info=$s->get_result()->fetch_assoc();if(!$info){$errors[]="Postulante no encontrado.";}else{$cols=db_table_columns($db,'practicantes');$departmentId=0;if(!empty($info['area_postulacion'])){$d=$db->prepare("SELECT id FROM departamentos WHERE LOWER(name)=LOWER(?) LIMIT 1");$d->bind_param('s',$info['area_postulacion']);$d->execute();$dr=$d->get_result()->fetch_assoc();if($dr)$departmentId=(int)$dr['id'];}$row=['postulante_id'=>$postulanteId,'full_name'=>$info['full_name'],'email'=>$info['email'],'telefono'=>$info['telefono'],'fecha_inicio'=>$fechaInicio,'fecha_fin'=>$fechaFin,'fecha_cumpleanos'=>$fechaCumpleanos,'status'=>'active','horas_exigidas'=>(int)($info['horas_exigidas']??0),'carrera'=>$info['carrera'],'universidad'=>$info['universidad']];if($departmentId>0)$row['department_id']=$departmentId;$exists=$db->prepare("SELECT id FROM practicantes WHERE postulante_id=? LIMIT 1");$exists->bind_param('i',$postulanteId);$exists->execute();$existing=$exists->get_result()->fetch_assoc();if($existing){$upd=$db->prepare("UPDATE practicantes SET fecha_inicio=?,fecha_fin=?,fecha_cumpleanos=?,status='active',department_id=? WHERE postulante_id=? LIMIT 1");$upd->bind_param('sssii',$fechaInicio,$fechaFin,$fechaCumpleanos,$departmentId,$postulanteId);$ok=$upd->execute();}else{$ok=function_exists('practicantes_insert_row')?practicantes_insert_row($db,$row,$cols):false;}if($ok){$u=$db->prepare("UPDATE postulantes SET status='seleccionado',status_updated_at=NOW() WHERE id=?");$u->bind_param('i',$postulanteId);$u->execute();$h=$db->prepare("INSERT INTO postulante_historial(postulante_id,estado,nota,created_at) VALUES(?,'seleccionado','Aceptado como practicante activo',NOW())");$h->bind_param('i',$postulanteId);$h->execute();$messages[]="Postulante aceptado y registrado como practicante activo.";}else{$errors[]="No se pudo crear el practicante.";}}}
            } elseif ($formType === 'postulante_entrevista') {
                $postulanteId = (int)($_POST['postulante_id'] ?? 0);
                $tipo = trim((string)($_POST['tipo'] ?? ''));
                $fecha = trim((string)($_POST['fecha'] ?? ''));
                $observaciones = trim((string)($_POST['observaciones'] ?? ''));
                if ($postulanteId > 0 && $tipo !== '' && $canAdd) {
                    $stmt = $db->prepare("INSERT INTO postulante_entrevistas (postulante_id, tipo, fecha, observaciones, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->bind_param('isss', $postulanteId, $tipo, $fecha, $observaciones);
                    $stmt->execute();
                    $messages[] = "Entrevista registrada.";
                } elseif (!$canAdd) {
                    $errors[] = "No tienes permisos para registrar entrevistas.";
                }
            } elseif ($formType === 'postulante_documento') {
                $postulanteId = (int)($_POST['postulante_id'] ?? 0);
                $titulo = trim((string)($_POST['titulo'] ?? ''));
                $tipo = trim((string)($_POST['tipo'] ?? ''));
                $ruta = trim((string)($_POST['ruta'] ?? ''));
                if ($postulanteId > 0 && $titulo !== '' && $canAdd) {
                    $stmt = $db->prepare("INSERT INTO documentos (target_type, target_id, titulo, tipo, ruta, created_at) VALUES ('postulante', ?, ?, ?, ?, NOW())");
                    $stmt->bind_param('isss', $postulanteId, $titulo, $tipo, $ruta);
                    $stmt->execute();
                    $messages[] = "Documento asociado.";
                } elseif (!$canAdd) {
                    $errors[] = "No tienes permisos para agregar documentos.";
                }
            } elseif ($formType === 'postulante_observacion') {
                $postulanteId = (int)($_POST['postulante_id'] ?? 0);
                $detalle = trim((string)($_POST['detalle'] ?? ''));
                if ($postulanteId > 0 && $detalle !== '' && $canAdd) {
                    $stmt = $db->prepare("INSERT INTO observaciones (target_type, target_id, detalle, created_at) VALUES ('postulante', ?, ?, NOW())");
                    $stmt->bind_param('is', $postulanteId, $detalle);
                    $stmt->execute();
                    $messages[] = "Observacion registrada.";
                } elseif (!$canAdd) {
                    $errors[] = "No tienes permisos para agregar observaciones.";
                }
            } elseif ($formType === 'postulante_delete') {
                $postulanteId = (int)($_POST['postulante_id'] ?? 0);
                $confirmDelete = trim((string)($_POST['confirm_delete'] ?? ''));
                if ($postulanteId > 0 && $confirmDelete === 'si' && $canDelete) {
                    $deleteStmt = $db->prepare("DELETE FROM postulantes WHERE id = ?");
                    $deleteStmt->bind_param('i', $postulanteId);
                    if ($deleteStmt->execute()) {
                        $messages[] = "Postulante eliminado correctamente.";
                        $viewId = 0;
                    } else {
                        $errors[] = "Error al eliminar el postulante.";
                    }
                } elseif ($postulanteId > 0 && $confirmDelete === 'si' && $canAdd) {
                    $detail = "Solicitud de eliminacion postulante #$postulanteId";
                    if (function_exists('create_change_request')) {
                        create_change_request($db, $userId, 'postulantes', 'eliminacion', $detail);
                        $messages[] = "Solicitud enviada al Encargado de RRHH.";
                    } else {
                        $errors[] = "No se pudo registrar la solicitud.";
                    }
                }
            }
        }

        $filterName = trim((string)($_GET['q'] ?? ''));
        $filterUniversidad = trim((string)($_GET['universidad'] ?? ''));
        $filterCarrera = trim((string)($_GET['carrera'] ?? ''));
        $filterArea = trim((string)($_GET['area'] ?? ''));
        $filterStatus = trim((string)($_GET['status'] ?? ''));
        $filters = [];
        $params = [];
        $types = '';
        if ($filterName !== '') {
            $filters[] = "p.full_name LIKE ?";
            $params[] = '%' . $filterName . '%';
            $types .= 's';
        }
        if ($filterUniversidad !== '') {
            $filters[] = "p.universidad LIKE ?";
            $params[] = '%' . $filterUniversidad . '%';
            $types .= 's';
        }
        if ($filterCarrera !== '') {
            $filters[] = "p.carrera LIKE ?";
            $params[] = '%' . $filterCarrera . '%';
            $types .= 's';
        }
        if ($filterArea !== '') {
            $filters[] = "p.area_postulacion LIKE ?";
            $params[] = '%' . $filterArea . '%';
            $types .= 's';
        }
        if ($filterStatus !== '' && isset($postulanteStatuses[$filterStatus])) {
            $filters[] = "p.status = ?";
            $params[] = $filterStatus;
            $types .= 's';
        }
        $sql = "SELECT p.* FROM postulantes p";
        if ($filters) {
            $sql .= " WHERE " . implode(" AND ", $filters);
        }
        $sql .= " ORDER BY COALESCE(p.status_updated_at, p.created_at) DESC, p.id DESC";
        $stmt = $db->prepare($sql);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $postulantes = $stmt->get_result();

        $viewId = (int)($_GET['view'] ?? 0);
        $viewPostulante = null;
        $historial = $entrevistas = $docs = $obs = [];
        if ($viewId > 0) {
            $detailStmt = $db->prepare("SELECT * FROM postulantes WHERE id = ? LIMIT 1");
            $detailStmt->bind_param('i', $viewId);
            $detailStmt->execute();
            $viewPostulante = $detailStmt->get_result()->fetch_assoc();
            if ($viewPostulante) {
                $historialRes = $db->query("SELECT estado, nota, created_at FROM postulante_historial WHERE postulante_id = " . (int)$viewId . " ORDER BY created_at DESC");
                if ($historialRes) {
                    while ($row = $historialRes->fetch_assoc()) {
                        $historial[] = $row;
                    }
                }
                $entrevistaRes = $db->query("SELECT tipo, fecha, observaciones, created_at FROM postulante_entrevistas WHERE postulante_id = " . (int)$viewId . " ORDER BY created_at DESC");
                if ($entrevistaRes) {
                    while ($row = $entrevistaRes->fetch_assoc()) {
                        $entrevistas[] = $row;
                    }
                }
                $docRes = $db->query("SELECT titulo, tipo, ruta, created_at FROM documentos WHERE target_type = 'postulante' AND target_id = " . (int)$viewId . " ORDER BY created_at DESC");
                if ($docRes) {
                    while ($row = $docRes->fetch_assoc()) {
                        $docs[] = $row;
                    }
                }
                $obsRes = $db->query("SELECT detalle, created_at FROM observaciones WHERE target_type = 'postulante' AND target_id = " . (int)$viewId . " ORDER BY created_at DESC");
                if ($obsRes) {
                    while ($row = $obsRes->fetch_assoc()) {
                        $obs[] = $row;
                    }
                }
            }
        }

        return [
            'postulanteStatuses' => $postulanteStatuses,
            'messages' => $messages,
            'errors' => $errors,
            'formType' => $formType,
            'filterName' => $filterName,
            'filterUniversidad' => $filterUniversidad,
            'filterCarrera' => $filterCarrera,
            'filterArea' => $filterArea,
            'filterStatus' => $filterStatus,
            'postulantes' => $postulantes,
            'viewId' => $viewId,
            'viewPostulante' => $viewPostulante,
            'historial' => $historial,
            'entrevistas' => $entrevistas,
            'docs' => $docs,
            'obs' => $obs,
            'canAdd' => $canAdd,
            'canModify' => $canModify,
            'canDelete' => $canDelete,
            'activeModule' => $activeModule,
            'registerDraft' => $registerDraft,
            'carreras' => $carreras,
            'universidades' => $universidades,
            'departments' => $departments,
        ];
    }
}
