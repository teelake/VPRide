<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/RiderAuthService.php';
require_once $backendRoot . '/src/RideRepository.php';
require_once $backendRoot . '/src/AppSettingsRepository.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\AppSettingsRepository;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\RideRepository;
use VprideBackend\RiderAuthService;

Config::load($backendRoot . '/.env');

ApiMobileCors::sendPreflightIfOptions();
ApiMobileCors::headers();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed'], JSON_THROW_ON_ERROR);
    exit;
}

$token = RiderAuthService::readBearerFromRequest();
if ($token === null || $token === '') {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized'], JSON_THROW_ON_ERROR);
    exit;
}

$auth = new RiderAuthService(Database::pdo());
$user = $auth->resolveBearerToken($token);
if ($user === null) {
    http_response_code(401);
    echo json_encode(['error' => 'invalid_session'], JSON_THROW_ON_ERROR);
    exit;
}

$pub = (new AppSettingsRepository(Database::pdo()))->getPublicSettings();
$feat = $pub['features'];
if (! empty($feat['maintenanceMode'])) {
    http_response_code(503);
    echo json_encode([
        'error' => 'maintenance',
        'message' => $feat['maintenanceMessage'] !== '' ? $feat['maintenanceMessage'] : 'Service temporarily unavailable.',
    ], JSON_THROW_ON_ERROR);
    exit;
}
if ($feat['rideBookingEnabled'] === false) {
    http_response_code(403);
    echo json_encode(['error' => 'ride_booking_disabled'], JSON_THROW_ON_ERROR);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
try {
    /** @var mixed $data */
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_json'], JSON_THROW_ON_ERROR);
    exit;
}

if (! is_array($data) || ! isset($data['pickup']) || ! is_array($data['pickup'])) {
    http_response_code(400);
    echo json_encode(['error' => 'pickup_required'], JSON_THROW_ON_ERROR);
    exit;
}

$pick = $data['pickup'];
$lat = isset($pick['latitude']) ? (float) $pick['latitude'] : 0.0;
$lng = isset($pick['longitude']) ? (float) $pick['longitude'] : 0.0;
if ($lat === 0.0 && $lng === 0.0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_coordinates'], JSON_THROW_ON_ERROR);
    exit;
}
if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    http_response_code(400);
    echo json_encode(['error' => 'coordinates_out_of_range'], JSON_THROW_ON_ERROR);
    exit;
}

$addr = null;
if (isset($pick['address']) && is_string($pick['address'])) {
    $addr = mb_substr(trim($pick['address']), 0, 500);
    if ($addr === '') {
        $addr = null;
    }
}

try {
    $rides = new RideRepository(Database::pdo());
    $id = $rides->createRequested(
        $user['rider_user_id'],
        $lat,
        $lng,
        $addr,
    );
    echo json_encode([
        'id' => $id,
        'status' => 'requested',
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable) {
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
}
