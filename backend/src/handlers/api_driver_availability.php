<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/DriverApiContext.php';
require_once $backendRoot . '/src/DriverAvailabilityRepository.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\DriverApiContext;
use VprideBackend\DriverAvailabilityRepository;

Config::load($backendRoot . '/.env');

ApiMobileCors::sendPreflightIfOptions();
ApiMobileCors::headers();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed'], JSON_THROW_ON_ERROR);
    exit;
}

$pdo = Database::pdo();
$ctx = DriverApiContext::requireFleetDriver($pdo);

$raw = file_get_contents('php://input') ?: '';
try {
    /** @var mixed $data */
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_json'], JSON_THROW_ON_ERROR);
    exit;
}

$status = isset($data['status']) && is_string($data['status']) ? trim($data['status']) : '';
if (! in_array($status, ['offline', 'online', 'busy'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_status'], JSON_THROW_ON_ERROR);
    exit;
}

try {
    (new DriverAvailabilityRepository($pdo))->setStatus($ctx['riderUserId'], $status);
    echo json_encode(['availability' => $status], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    if ($e->getMessage() === 'invalid_availability') {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_status'], JSON_THROW_ON_ERROR);
        exit;
    }
    error_log('[vpride] driver/availability: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
}
