<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__);
require_once $backendRoot . '/src/Config.php';

use VprideBackend\Config;

Config::load($backendRoot . '/.env');

// Send PHP errors to a file (default: backend/storage/logs/php-error.log).
// Disable: PHP_ERROR_LOG_DISABLE=1 in .env. Override path: PHP_ERROR_LOG_FILE=/full/path/to.log
if (trim((string) getenv('PHP_ERROR_LOG_DISABLE')) !== '1') {
    $customLog = trim((string) getenv('PHP_ERROR_LOG_FILE'));
    if ($customLog !== '') {
        $errorLogFile = $customLog;
    } else {
        $logDir = $backendRoot . '/storage/logs';
        if (! is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $errorLogFile = $logDir . '/php-error.log';
    }
    ini_set('log_errors', '1');
    ini_set('error_log', $errorLogFile);
}

$vendorAutoload = $backendRoot . '/vendor/autoload.php';
if (is_readable($vendorAutoload)) {
    require_once $vendorAutoload;
}

/**
 * Build the logical path for routing (/admin/login, /api/v1/...).
 * Handles subfolders, RewriteBase, and some hosts that put /index.php in REQUEST_URI.
 */
$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = $rawPath;

// …/public/index.php or …/public/index.php/admin/foo
if (str_contains($path, '/index.php')) {
    $path = preg_replace('#^.*/index\.php#', '', $path, 1) ?? '';
    if ($path === '' || $path === false) {
        $path = '/';
    }
    if ($path !== '/' && ! str_starts_with($path, '/')) {
        $path = '/' . $path;
    }
}

$bases = [];
$configured = Config::basePath();
if ($configured !== '') {
    $bases[] = $configured;
}
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$scriptDir = str_replace('\\', '/', $scriptDir);
$scriptDir = rtrim($scriptDir, '/');
if ($scriptDir !== '' && $scriptDir !== '/') {
    $bases[] = $scriptDir;
}

$bases = array_values(array_unique($bases));
foreach ($bases as $base) {
    $b = str_starts_with($base, '/') ? $base : '/' . $base;
    if ($path === $b || str_starts_with($path, $b . '/')) {
        $path = substr($path, strlen($b)) ?: '/';
        break;
    }
}

$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Preload RBAC so Auth::can() always resolves (guards against partial deploys / opcache oddities).
if (str_starts_with($path, '/admin')) {
    require_once $backendRoot . '/src/RbacRepository.php';
    require_once $backendRoot . '/src/RbacRuntime.php';
}

if ($path === '/' && $method === 'GET') {
    header('Location: ' . Config::url('/admin/login'));
    exit;
}

if ($path === '/admin/assets/admin.css') {
    $cssFile = $backendRoot . '/public/admin/assets/admin.css';
    if (is_readable($cssFile)) {
        header('Content-Type: text/css; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        readfile($cssFile);
        exit;
    }
}

if ($path === '/api/v1/config/regions' && $method === 'GET') {
    require $backendRoot . '/src/handlers/api_regions.php';
    exit;
}

if ($path === '/api/v1/config/public' && in_array($method, ['GET', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_config_public.php';
    exit;
}

if ($path === '/api/v1/auth/google' && in_array($method, ['POST', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_auth_google.php';
    exit;
}

if ($path === '/api/v1/auth/logout' && in_array($method, ['POST', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_auth_logout.php';
    exit;
}

if ($path === '/api/v1/me' && in_array($method, ['GET', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_me.php';
    exit;
}

if ($path === '/api/v1/rides' && in_array($method, ['POST', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_rides.php';
    exit;
}

if ($path === '/admin/login' && in_array($method, ['GET', 'POST'], true)) {
    require $backendRoot . '/public/admin/login.php';
    exit;
}

if ($path === '/admin/forgot-password' && in_array($method, ['GET', 'POST'], true)) {
    require $backendRoot . '/public/admin/forgot_password.php';
    exit;
}

if ($path === '/admin/reset-password' && in_array($method, ['GET', 'POST'], true)) {
    require $backendRoot . '/public/admin/reset_password.php';
    exit;
}

if ($path === '/admin/account' && in_array($method, ['GET', 'POST'], true)) {
    require $backendRoot . '/public/admin/account.php';
    exit;
}

if ($path === '/admin/logout' && $method === 'POST') {
    require $backendRoot . '/public/admin/logout.php';
    exit;
}

if ($path === '/admin' || $path === '/admin/dashboard') {
    require $backendRoot . '/public/admin/dashboard.php';
    exit;
}

if ($path === '/admin/regions' && in_array($method, ['GET', 'POST'], true)) {
    require $backendRoot . '/public/admin/regions.php';
    exit;
}

if ($path === '/admin/rides' && $method === 'GET') {
    require $backendRoot . '/public/admin/rides.php';
    exit;
}

if ($path === '/admin/riders' && $method === 'GET') {
    require $backendRoot . '/public/admin/riders.php';
    exit;
}

if ($path === '/admin/team' && $method === 'GET') {
    require $backendRoot . '/public/admin/team.php';
    exit;
}

if ($path === '/admin/team/new' && in_array($method, ['GET', 'POST'], true)) {
    require $backendRoot . '/public/admin/team_admin_new.php';
    exit;
}

if ($path === '/admin/settings' && in_array($method, ['GET', 'POST'], true)) {
    require $backendRoot . '/public/admin/settings.php';
    exit;
}

if ($path === '/admin/reports' && $method === 'GET') {
    header('Location: ' . Config::url('/admin/reports/rides'));
    exit;
}

if ($path === '/admin/reports/rides' && $method === 'GET') {
    require $backendRoot . '/public/admin/reports_rides.php';
    exit;
}

if ($path === '/admin/reports/riders' && $method === 'GET') {
    require $backendRoot . '/public/admin/reports_riders.php';
    exit;
}

if ($path === '/admin/rbac' && in_array($method, ['GET', 'POST'], true)) {
    require $backendRoot . '/public/admin/rbac.php';
    exit;
}

if ($path === '/admin/rbac/permissions' && in_array($method, ['GET', 'POST'], true)) {
    require $backendRoot . '/public/admin/rbac_permissions.php';
    exit;
}

if ($path === '/admin/rbac/role/new' && in_array($method, ['GET', 'POST'], true)) {
    $_ROUTE_RBAC_NEW = true;
    require $backendRoot . '/public/admin/rbac_role_edit.php';
    exit;
}

if (preg_match('#^/admin/rbac/role/(\\d+)$#', $path, $m) && in_array($method, ['GET', 'POST'], true)) {
    $_ROUTE_RBAC_ROLE_ID = (int) $m[1];
    require $backendRoot . '/public/admin/rbac_role_edit.php';
    exit;
}

if (preg_match('#^/admin/region/(\\d+)$#', $path, $m) && in_array($method, ['GET', 'POST'], true)) {
    $_ROUTE_REGION_ID = (int) $m[1];
    require $backendRoot . '/public/admin/region_edit.php';
    exit;
}

if ($path === '/admin/region/new' && in_array($method, ['GET', 'POST'], true)) {
    $_ROUTE_REGION_NEW = true;
    require $backendRoot . '/public/admin/region_edit.php';
    exit;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Not found';
