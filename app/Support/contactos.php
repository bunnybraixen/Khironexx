<?php

if (!function_exists('contactos_export_php_value')) {
    function contactos_export_php_value($var, $indent = 0)
    {
        $pad = str_repeat('    ', $indent);
        $nextPad = str_repeat('    ', $indent + 1);

        if (is_array($var)) {
            if (empty($var)) {
                return '[]';
            }
            $isIndexed = (array_keys($var) === range(0, count($var) - 1));
            $lines = [];
            foreach ($var as $key => $value) {
                $exportedVal = contactos_export_php_value($value, $indent + 1);
                if ($isIndexed) {
                    $lines[] = $nextPad . $exportedVal;
                } else {
                    $exportedKey = var_export($key, true);
                    $lines[] = $nextPad . $exportedKey . ' => ' . $exportedVal;
                }
            }
            return "[\n" . implode(",\n", $lines) . ",\n" . $pad . "]";
        }

        return var_export($var, true);
    }
}

if (!function_exists('contactos_save_config')) {
    function contactos_save_config($configPath, array $config)
    {
        $phpCode = "<?php\n\n// Edit this file to update the dashboard content and menu labels.\n\nreturn " . contactos_export_php_value($config, 0) . ";\n";
        $result = @file_put_contents($configPath, $phpCode, LOCK_EX);
        if ($result !== false) {
            @chmod($configPath, 0664);
            return true;
        }
        return false;
    }
}

if (!function_exists('contactos_normalize_list')) {
    function contactos_normalize_list($contactsRaw)
    {
        $contacts = [];
        if (is_array($contactsRaw)) {
            foreach ($contactsRaw as $item) {
                if (is_array($item)) {
                    $area = trim((string)($item['area'] ?? $item['equipo'] ?? ''));
                    $cargo = trim((string)($item['cargo'] ?? $item['rol'] ?? ''));
                    $nombre = trim((string)($item['nombre'] ?? $item['name'] ?? ''));
                    $apellido = trim((string)($item['apellido'] ?? $item['last_name'] ?? ''));
                    $contacto = trim((string)($item['contacto'] ?? $item['email'] ?? $item['correo'] ?? ''));
                    $status = trim((string)($item['status'] ?? ''));
                    if ($status === '') {
                        $status = 'Activo';
                    }

                    if ($area === '' && $cargo === '' && $nombre === '' && $apellido === '' && $contacto === '') {
                        continue;
                    }

                    $contacts[] = [
                        'area' => $area,
                        'cargo' => $cargo,
                        'nombre' => $nombre,
                        'apellido' => $apellido,
                        'contacto' => $contacto,
                        'status' => $status,
                    ];
                } else {
                    $label = trim((string)$item);
                    if ($label !== '') {
                        $contacts[] = [
                            'area' => '',
                            'cargo' => '',
                            'nombre' => $label,
                            'apellido' => '',
                            'contacto' => '',
                            'status' => 'Activo',
                        ];
                    }
                }
            }
        }
        return $contacts;
    }
}

if (!function_exists('contactos_build_data')) {
    function contactos_build_data($configPath, array $permissions)
    {
        $messages = [];
        $errors = [];
        $canManage = (bool)($permissions['canManage'] ?? false);
        $canDelete = (bool)($permissions['canDelete'] ?? false);
        $draft = [];
        $editIndex = null;
        $editContact = null;

        $dashboardConfig = [];
        if (function_exists('load_dashboard_config')) {
            $dashboardConfig = load_dashboard_config($configPath);
        } elseif (is_file($configPath)) {
            try {
                $loaded = require $configPath;
                if (is_array($loaded)) {
                    $dashboardConfig = $loaded;
                }
            } catch (Throwable $e) {
                $dashboardConfig = [];
            }
        }

        $contacts = contactos_normalize_list($dashboardConfig['contacts'] ?? []);

        // Handling POST actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formAction = trim((string)($_POST['form_action'] ?? ''));

            if ($formAction === 'create') {
                if (!$canManage) {
                    $errors[] = "No tienes permisos para agregar contactos.";
                } else {
                    $nombre = trim((string)($_POST['nombre'] ?? ''));
                    $apellido = trim((string)($_POST['apellido'] ?? ''));
                    $area = trim((string)($_POST['area'] ?? ''));
                    $cargo = trim((string)($_POST['cargo'] ?? ''));
                    $contactoVal = trim((string)($_POST['contacto'] ?? ''));
                    $status = trim((string)($_POST['status'] ?? 'Activo'));

                    $draft = [
                        'nombre' => $nombre,
                        'apellido' => $apellido,
                        'area' => $area,
                        'cargo' => $cargo,
                        'contacto' => $contactoVal,
                        'status' => $status,
                    ];

                    if ($nombre === '') {
                        $errors[] = "El campo 'Nombre' es obligatorio.";
                    } elseif ($contactoVal === '') {
                        $errors[] = "El campo 'Contacto / Email' es obligatorio.";
                    } else {
                        $newContact = [
                            'area' => $area,
                            'cargo' => $cargo,
                            'nombre' => $nombre,
                            'apellido' => $apellido,
                            'contacto' => $contactoVal,
                            'status' => in_array(strtolower($status), ['inactivo', 'inactive'], true) ? 'Inactivo' : 'Activo',
                        ];

                        $contacts[] = $newContact;
                        $dashboardConfig['contacts'] = $contacts;

                        if (contactos_save_config($configPath, $dashboardConfig)) {
                            $messages[] = "Contacto '{$nombre} " . ($apellido ? $apellido . ' ' : '') . "' agregado correctamente.";
                            $draft = [];
                        } else {
                            $errors[] = "No se pudo guardar el archivo de configuración. Verifica los permisos de escritura en {$configPath}.";
                            array_pop($contacts);
                        }
                    }
                }
            } elseif ($formAction === 'update') {
                if (!$canManage) {
                    $errors[] = "No tienes permisos para modificar contactos.";
                } else {
                    $index = isset($_POST['contact_index']) ? (int)$_POST['contact_index'] : -1;
                    if ($index < 0 || !isset($contacts[$index])) {
                        $errors[] = "El contacto especificado para actualizar no existe.";
                    } else {
                        $nombre = trim((string)($_POST['nombre'] ?? ''));
                        $apellido = trim((string)($_POST['apellido'] ?? ''));
                        $area = trim((string)($_POST['area'] ?? ''));
                        $cargo = trim((string)($_POST['cargo'] ?? ''));
                        $contactoVal = trim((string)($_POST['contacto'] ?? ''));
                        $status = trim((string)($_POST['status'] ?? 'Activo'));

                        if ($nombre === '') {
                            $errors[] = "El campo 'Nombre' es obligatorio.";
                            $editIndex = $index;
                            $editContact = [
                                'area' => $area,
                                'cargo' => $cargo,
                                'nombre' => $nombre,
                                'apellido' => $apellido,
                                'contacto' => $contactoVal,
                                'status' => $status,
                            ];
                        } elseif ($contactoVal === '') {
                            $errors[] = "El campo 'Contacto / Email' es obligatorio.";
                            $editIndex = $index;
                            $editContact = [
                                'area' => $area,
                                'cargo' => $cargo,
                                'nombre' => $nombre,
                                'apellido' => $apellido,
                                'contacto' => $contactoVal,
                                'status' => $status,
                            ];
                        } else {
                            $contacts[$index] = [
                                'area' => $area,
                                'cargo' => $cargo,
                                'nombre' => $nombre,
                                'apellido' => $apellido,
                                'contacto' => $contactoVal,
                                'status' => in_array(strtolower($status), ['inactivo', 'inactive'], true) ? 'Inactivo' : 'Activo',
                            ];

                            $dashboardConfig['contacts'] = $contacts;

                            if (contactos_save_config($configPath, $dashboardConfig)) {
                                $messages[] = "Contacto actualizado correctamente.";
                            } else {
                                $errors[] = "No se pudo actualizar el archivo de configuración. Verifica los permisos de escritura.";
                            }
                        }
                    }
                }
            } elseif ($formAction === 'delete') {
                if (!$canDelete) {
                    $errors[] = "No tienes permisos para eliminar contactos.";
                } else {
                    $index = isset($_POST['contact_index']) ? (int)$_POST['contact_index'] : -1;
                    if ($index < 0 || !isset($contacts[$index])) {
                        $errors[] = "El contacto seleccionado para eliminar no existe.";
                    } else {
                        $deletedName = $contacts[$index]['nombre'] . ' ' . $contacts[$index]['apellido'];
                        array_splice($contacts, $index, 1);
                        $dashboardConfig['contacts'] = array_values($contacts);

                        if (contactos_save_config($configPath, $dashboardConfig)) {
                            $messages[] = "Contacto '" . trim($deletedName) . "' eliminado correctamente.";
                        } else {
                            $errors[] = "No se pudo eliminar el contacto del archivo de configuración.";
                        }
                    }
                }
            } elseif ($formAction === 'reorder') {
                if (!$canManage) {
                    $errors[] = "No tienes permisos para reordenar contactos.";
                } else {
                    $index = isset($_POST['contact_index']) ? (int)$_POST['contact_index'] : -1;
                    $direction = trim((string)($_POST['direction'] ?? ''));

                    if ($index >= 0 && isset($contacts[$index])) {
                        $targetIndex = $direction === 'up' ? $index - 1 : ($direction === 'down' ? $index + 1 : -1);
                        if ($targetIndex >= 0 && $targetIndex < count($contacts)) {
                            $temp = $contacts[$index];
                            $contacts[$index] = $contacts[$targetIndex];
                            $contacts[$targetIndex] = $temp;

                            $dashboardConfig['contacts'] = array_values($contacts);
                            if (contactos_save_config($configPath, $dashboardConfig)) {
                                $messages[] = "Orden de contactos actualizado.";
                            }
                        }
                    }
                }
            }
        }

        // Checking GET for edit state
        if ($editIndex === null && isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['index'])) {
            $reqIndex = (int)$_GET['index'];
            if (isset($contacts[$reqIndex])) {
                $editIndex = $reqIndex;
                $editContact = $contacts[$reqIndex];
            }
        }

        return [
            'messages' => $messages,
            'errors' => $errors,
            'contacts' => $contacts,
            'canManage' => $canManage,
            'canDelete' => $canDelete,
            'editIndex' => $editIndex,
            'editContact' => $editContact,
            'draft' => $draft,
        ];
    }
}

