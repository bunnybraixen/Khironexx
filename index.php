<?php

require_once __DIR__ . '/app/bootstrap.php';

$config = khironex_config();
$installed = !empty($config['installed']);

if ($installed) {
    redirect('dashboard/');
}

redirect('installer/');
