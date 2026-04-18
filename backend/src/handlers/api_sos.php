<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/RiderAuthService.php';
require_once $backendRoot . '/src/RideRepository.php';
require_once $backendRoot . '/src/AppSettingsRepository.php';
require_once $backendRoot . '/src/SosIncidentRepository.php';
require_once $backendRoot . '/src/SosNotifier.php';
require_once $backendRoot . '/src/RateLimiter.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\AppSettingsRepository;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\RateLimiter;
use VprideBackend\RideRepository;
use VprideBackend\RiderAuthService;
use VprideBackend\SosIncidentRepository;
use VprideBackend\SosNotifier;

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

$pdo = Database::pdo();
$auth = new RiderAuthService($pdo);
$user = $auth->resolveBearerToken($token);
if ($user === null) {
    http_response_code(401);
    echo json_encode(['error' => 'invalid_session'], JSON_THROW_ON_ERROR);
    exit;
}

$pub = (new AppSettingsRepository($pdo))->getPublicSettings();
if (empty($pub['features']['sosEnabled'])) {
    http_response_code(403);
    echo json_encode(['error' => 'sos_disabled'], JSON_THROW_ON_ERROR);
    exit;
}

if (! RateLimiter::allow('sos', (string) $user['rider_user_id'], 8, 3600)) {
    http_response_code(429);
    echo json_encode(['error' => 'rate_limited'], JSON_THROW_ON_ERROR);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
try {
    /** @var mixed $data */
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_json'], JSON_THROW_ON_ERROR);
    exit;
}

if (! is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_body'], JSON_THROW_ON_ERROR);
    exit;
}

$rideId = isset($data['rideId']) ? (int) $data['rideId'] : 0;
if ($rideId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'ride_id_required'], JSON_THROW_ON_ERROR);
    exit;
}

if (! SosIncidentRepository::tableExists($pdo)) {
    http_response_code(503);
    echo json_encode(['error' => 'sos_not_configured'], JSON_THROW_ON_ERROR);
    exit;
}

$rides = new RideRepository($pdo);
$ride = $rides->findByIdForRiderUser($rideId, $user['rider_user_id']);
if ($ride === null) {
    http_response_code(404);
    echo json_encode(['error' => 'ride_not_found'], JSON_THROW_ON_ERROR);
    exit;
}

$st = (string) $ride['status'];
if (! in_array($st, ['requested', 'accepted', 'in_progress'], true)) {
    http_response_code(409);
    echo json_encode(['error' => 'ride_not_active'], JSON_THROW_ON_ERROR);
    exit;
}

$riderId = (int) $ride['rider_user_id'];
$driverUid = isset($ride['driver_rider_user_id']) && $ride['driver_rider_user_id'] !== null
    ? (int) $ride['driver_rider_user_id']
    : null;
$uid = $user['rider_user_id'];
$role = $uid === $riderId ? 'rider' : ($driverUid !== null && $uid === $driverUid ? 'driver' : null);
if ($role === null) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden'], JSON_THROW_ON_ERROR);
    exit;
}

$lat = isset($data['latitude']) ? (float) $data['latitude'] : 0.0;
$lng = isset($data['longitude']) ? (float) $data['longitude'] : 0.0;
if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_coordinates'], JSON_THROW_ON_ERROR);
    exit;
}

$acc = null;
if (isset($data['accuracyM']) && is_numeric($data['accuracyM'])) {
    $acc = (float) $data['accuracyM'];
}
$msg = null;
if (isset($data['message']) && is_string($data['message'])) {
    $msg = trim($data['message']);
    if ($msg === '') {
        $msg = null;
    }
}
$clientReq = null;
if (isset($data['clientRequestId']) && is_string($data['clientRequestId'])) {
    $u = trim($data['clientRequestId']);
    if (preg_match('/^[0-9a-fA-F-]{36}$/', $u)) {
        $clientReq = strtolower($u);
    }
}

try {
    $repo = new SosIncidentRepository($pdo);
    if ($clientReq !== null) {
        $existing = $repo->findIdByClientRequestId($clientReq);
        if ($existing !== null) {
            echo json_encode(['id' => $existing, 'duplicate' => true], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            exit;
        }
    }
    $id = $repo->insert($rideId, $uid, $role, $lat, $lng, $acc, $msg, $clientReq);
    $incident = ['id' => $id, 'latitude' => $lat, 'longitude' => $lng, 'reporter_role' => $role, 'reporter_rider_user_id' => $uid, 'message' => $msg];
    SosNotifier::emailOps($pdo, $incident, $ride, $user['email']);
    echo json_encode(['id' => $id], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('[vpride] POST /api/v1/sos: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
}
