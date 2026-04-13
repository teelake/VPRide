<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Auth.php';

use VprideBackend\Auth;
use VprideBackend\Config;

Config::load($backendRoot . '/.env');
Auth::startSession();

if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
    http_response_code(400);
    echo 'Bad request';
    exit;
}

Auth::logout();
header('Location: /admin/login');
exit;
