<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Auth.php';

use VprideBackend\Auth;
use VprideBackend\Config;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();

$q = trim((string) ($_GET['q'] ?? ''));
$qs = $q !== '' ? ('?q=' . rawurlencode($q)) : '';

if (Auth::can('riders.view')) {
    header('Location: ' . Config::url('/admin/riders' . $qs));
    exit;
}
if (Auth::can('rides.view')) {
    header('Location: ' . Config::url('/admin/rides'));
    exit;
}

header('Location: ' . Config::url('/admin/dashboard'));
exit;
