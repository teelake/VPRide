<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__);
require_once $backendRoot . '/src/Config.php';

use VprideBackend\Config;

Config::load($backendRoot . '/.env');

$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = $rawPath;
$basePath = \VprideBackend\Config::basePath();
if ($basePath !== '') {
    if ($path === $basePath || str_starts_with($path, $basePath . '/')) {
        $path = substr($path, strlen($basePath)) ?: '/';
    }
}
$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

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

if ($path === '/admin/login' && in_array($method, ['GET', 'POST'], true)) {
    require $backendRoot . '/public/admin/login.php';
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
