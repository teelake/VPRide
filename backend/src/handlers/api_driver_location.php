<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/DriverApiContext.php';
require_once $backendRoot . '/src/DriverAvailabilityRepository.php';
require_once $backendRoot . '/src/RateLimiter.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\DriverApiContext;
use VprideBackend\DriverAvailabilityRepository;
use VprideBackend\RateLimiter;

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

if (! is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_body'], JSON_THROW_ON_ERROR);
    exit;
}

$lat = $data['latitude'] ?? null;
$lng = $data['longitude'] ?? null;
if (! is_numeric($lat) || ! is_numeric($lng)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_coordinates'], JSON_THROW_ON_ERROR);
    exit;
}

$latF = (float) $lat;
$lngF = (float) $lng;
if ($latF < -90.0 || $latF > 90.0 || $lngF < -180.0 || $lngF > 180.0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_coordinates'], JSON_THROW_ON_ERROR);
    exit;
}

try {
    (new DriverAvailabilityRepository($pdo))->upsertLastKnownLocation(
        $ctx['riderUserId'],
        $latF,
        $lngF,
    );
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('[vpride] driver/location: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
}
