<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__);
require_once $backendRoot . '/src/Config.php';

use VprideBackend\Config;

Config::load($backendRoot . '/.env');

// Do not index admin, API, or any entry served from this app (tells crawlers; pairs with public robots.txt on the site).
header('X-Robots-Tag: noindex, nofollow, noarchive', true);

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

// Serve anything under /admin/assets/ from public/admin/assets/ (fixes favicon, PNG, JS when all traffic goes through this front controller).
if ($method === 'GET' && str_starts_with($path, '/admin/assets/')) {
    $rel = substr($path, strlen('/admin/assets/'));
    if ($rel === '' || str_contains($rel, '..') || str_contains($rel, "\0")) {
        http_response_code(404);
        exit;
    }
    $assetsRoot = $backendRoot . '/public/admin/assets';
    $file = $assetsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $realFile = is_readable($file) ? realpath($file) : false;
    $realRoot = realpath($assetsRoot);
    if ($realFile && $realRoot && is_file($realFile) && str_starts_with($realFile, $realRoot)) {
        $ext = strtolower((string) pathinfo($realFile, PATHINFO_EXTENSION));
        $mimes = [
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml; charset=utf-8',
            'ico' => 'image/x-icon',
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'ttf' => 'font/ttf',
        ];
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        header('Cache-Control: public, max-age=2592000');
        readfile($realFile);
        exit;
    }
    http_response_code(404);
    exit;
}

if ($path === '/api/v1/config/regions' && $method === 'GET') {
    require $backendRoot . '/src/handlers/api_regions.php';
    exit;
}

if ($path === '/api/v1/config/public' && in_array($method, ['GET', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_config_public.php';
    exit;
}

if ($path === '/api/v1/log/client' && in_array($method, ['POST', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_log_client.php';
    exit;
}

if ($path === '/api/v1/auth/google' && in_array($method, ['POST', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_auth_google.php';
    exit;
}

if ($path === '/api/v1/auth/register' && in_array($method, ['POST', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_auth_register.php';
    exit;
}

if ($path === '/api/v1/auth/login' && in_array($method, ['POST', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_auth_login.php';
    exit;
}

if ($path === '/api/v1/auth/logout' && in_array($method, ['POST', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_auth_logout.php';
    exit;
}

if ($path === '/api/v1/auth/forgot-password' && in_array($method, ['POST', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_auth_forgot_password.php';
    exit;
}

if ($path === '/api/v1/auth/reset-password' && in_array($method, ['POST', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_auth_reset_password.php';
    exit;
}

if ($path === '/api/v1/auth/change-password' && in_array($method, ['POST', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_auth_change_password.php';
    exit;
}

if ($path === '/rider/reset-password' && in_array($method, ['GET', 'POST'], true)) {
    require $backendRoot . '/public/rider/reset_password.php';
    exit;
}

if ($path === '/api/v1/me' && in_array($method, ['GET', 'PATCH', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_me.php';
    exit;
}

if ($path === '/api/v1/me/photo' && in_array($method, ['POST', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_me_photo.php';
    exit;
}

if ($path === '/api/v1/rides/current' && in_array($method, ['GET', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_rides_current.php';
    exit;
}

if ($path === '/api/v1/rides/mine' && in_array($method, ['GET', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_rides_mine.php';
    exit;
}

if ($path === '/api/v1/rides/estimate' && in_array($method, ['POST', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_rides_estimate.php';
    exit;
}

if ($path === '/api/v1/driver/availability' && in_array($method, ['POST', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_driver_availability.php';
    exit;
}

if ($path === '/api/v1/driver/location' && in_array($method, ['POST', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_driver_location.php';
    exit;
}

if ($path === '/api/v1/driver/rides/incoming' && in_array($method, ['GET', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_driver_rides_incoming.php';
    exit;
}

if ($path === '/api/v1/driver/rides/active' && in_array($method, ['GET', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_driver_rides_active.php';
    exit;
}

if ($path === '/api/v1/driver/rides/history' && in_array($method, ['GET', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_driver_rides_history.php';
    exit;
}

if ($path === '/api/v1/driver/earnings/summary' && in_array($method, ['GET', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_driver_earnings.php';
    exit;
}

if (preg_match('#^/api/v1/driver/rides/(\\d+)/accept$#', $path, $vpridePathMatch) && in_array($method, ['POST', 'OPTIONS'], true)) {
    $GLOBALS['vpride_driver_ride_id'] = (int) $vpridePathMatch[1];
    require $backendRoot . '/src/handlers/api_driver_ride_accept.php';
    exit;
}

if (preg_match('#^/api/v1/driver/rides/(\\d+)/reject$#', $path, $vpridePathMatch) && in_array($method, ['POST', 'OPTIONS'], true)) {
    $GLOBALS['vpride_driver_ride_id'] = (int) $vpridePathMatch[1];
    require $backendRoot . '/src/handlers/api_driver_ride_reject.php';
    exit;
}

if (preg_match('#^/api/v1/driver/rides/(\\d+)/start$#', $path, $vpridePathMatch) && in_array($method, ['POST', 'OPTIONS'], true)) {
    $GLOBALS['vpride_driver_ride_id'] = (int) $vpridePathMatch[1];
    require $backendRoot . '/src/handlers/api_driver_ride_start.php';
    exit;
}

if (preg_match('#^/api/v1/driver/rides/(\\d+)/complete$#', $path, $vpridePathMatch) && in_array($method, ['POST', 'OPTIONS'], true)) {
    $GLOBALS['vpride_driver_ride_id'] = (int) $vpridePathMatch[1];
    require $backendRoot . '/src/handlers/api_driver_ride_complete.php';
    exit;
}

if (preg_match('#^/api/v1/driver/rides/(\\d+)/confirm-payment$#', $path, $vpridePathMatch) && in_array($method, ['POST', 'OPTIONS'], true)) {
    $GLOBALS['vpride_driver_ride_id'] = (int) $vpridePathMatch[1];
    require $backendRoot . '/src/handlers/api_driver_ride_payment_confirm.php';
    exit;
}

if (preg_match('#^/api/v1/rides/(\\d+)/payment-proof$#', $path, $vpridePathMatch) && in_array($method, ['POST', 'OPTIONS'], true)) {
    $GLOBALS['vpride_ride_path_id'] = (int) $vpridePathMatch[1];
    require $backendRoot . '/src/handlers/api_ride_payment_proof.php';
    exit;
}

if (preg_match('#^/api/v1/rides/(\\d+)/payment-offline$#', $path, $vpridePathMatch) && in_array($method, ['POST', 'OPTIONS'], true)) {
    $GLOBALS['vpride_ride_path_id'] = (int) $vpridePathMatch[1];
    require $backendRoot . '/src/handlers/api_ride_payment_offline.php';
    exit;
}

if (preg_match('#^/api/v1/rides/(\\d+)/rating$#', $path, $vpridePathMatch) && in_array($method, ['POST', 'OPTIONS'], true)) {
    $GLOBALS['vpride_ride_path_id'] = (int) $vpridePathMatch[1];
    require $backendRoot . '/src/handlers/api_ride_rating.php';
    exit;
}

if (preg_match('#^/api/v1/rides/(\\d+)/cancel$#', $path, $vpridePathMatch) && in_array($method, ['POST', 'OPTIONS'], true)) {
    $GLOBALS['vpride_ride_path_id'] = (int) $vpridePathMatch[1];
    require $backendRoot . '/src/handlers/api_ride_cancel.php';
    exit;
}

if (preg_match('#^/api/v1/rides/(\\d+)$#', $path, $vpridePathMatch) && in_array($method, ['GET', 'OPTIONS'], true)) {
    $GLOBALS['vpride_ride_path_id'] = (int) $vpridePathMatch[1];
    require $backendRoot . '/src/handlers/api_ride_detail.php';
    exit;
}

if ($path === '/api/v1/sos' && in_array($method, ['POST', 'OPTIONS'], true)) {
    require $backendRoot . '/src/handlers/api_sos.php';
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

if ($path === '/admin/search' && $method === 'GET') {
    require $backendRoot . '/public/admin/search.php';
    exit;
}

if ($path === '/admin/regions' && in_array($method, ['GET', 'POST'], true)) {
    require $backendRoot . '/public/admin/regions.php';
    exit;
}

if (preg_match('#^/admin/rides/(\\d+)/dispatch$#', $path, $m) && in_array($method, ['GET', 'POST'], true)) {
    $_ROUTE_RIDE_DISPATCH_ID = (int) $m[1];
    require $backendRoot . '/public/admin/ride_dispatch.php';
    exit;
}

if ($path === '/admin/rides/create' && in_array($method, ['GET', 'POST'], true)) {
    require $backendRoot . '/public/admin/ride_create.php';
    exit;
}

if ($path === '/admin/rides' && in_array($method, ['GET', 'POST'], true)) {
    require $backendRoot . '/public/admin/rides.php';
    exit;
}

if ($path === '/admin/sos' && in_array($method, ['GET', 'POST'], true)) {
    require $backendRoot . '/public/admin/sos.php';
    exit;
}

if ($path === '/admin/promotions' && in_array($method, ['GET', 'POST'], true)) {
    require $backendRoot . '/public/admin/promotions.php';
    exit;
}

if ($path === '/admin/riders' && $method === 'GET') {
    require $backendRoot . '/public/admin/riders.php';
    exit;
}

if ($path === '/admin/drivers/new' && in_array($method, ['GET', 'POST'], true)) {
    $_ROUTE_DRIVER_NEW = true;
    require $backendRoot . '/public/admin/driver_edit.php';
    exit;
}

if (preg_match('#^/admin/drivers/(\\d+)$#', $path, $m) && in_array($method, ['GET', 'POST'], true)) {
    $_ROUTE_DRIVER_ID = (int) $m[1];
    require $backendRoot . '/public/admin/driver_edit.php';
    exit;
}

if ($path === '/admin/drivers' && in_array($method, ['GET', 'POST'], true)) {
    require $backendRoot . '/public/admin/drivers.php';
    exit;
}

if ($path === '/admin/users' && $method === 'GET') {
    require $backendRoot . '/public/admin/users.php';
    exit;
}

if ($path === '/admin/schedule' && $method === 'GET') {
    require $backendRoot . '/public/admin/schedule.php';
    exit;
}

if ($path === '/admin/fleet/new' && in_array($method, ['GET', 'POST'], true)) {
    $_ROUTE_FLEET_VEHICLE_NEW = true;
    require $backendRoot . '/public/admin/fleet_vehicle_edit.php';
    exit;
}

if (preg_match('#^/admin/fleet/(\\d+)$#', $path, $m) && in_array($method, ['GET', 'POST'], true)) {
    $_ROUTE_FLEET_VEHICLE_ID = (int) $m[1];
    require $backendRoot . '/public/admin/fleet_vehicle_edit.php';
    exit;
}

if ($path === '/admin/fleet' && in_array($method, ['GET', 'POST'], true)) {
    require $backendRoot . '/public/admin/fleet.php';
    exit;
}

if ($path === '/admin/help' && $method === 'GET') {
    require $backendRoot . '/public/admin/help.php';
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
