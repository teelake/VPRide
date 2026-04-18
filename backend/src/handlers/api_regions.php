<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/RegionRepository.php';
require_once $backendRoot . '/src/HttpCacheJson.php';

use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\HttpCacheJson;
use VprideBackend\RegionRepository;

Config::load($backendRoot . '/.env');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $repo = new RegionRepository(Database::pdo());
    $payload = $repo->getActivePayload();
    if ($payload === null) {
        http_response_code(404);
        echo json_encode(['error' => 'No active region configuration']);
        exit;
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    HttpCacheJson::emit($json, 120, 600);
} catch (Throwable $e) {
    error_log('[vpride] GET /api/v1/config/regions: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
