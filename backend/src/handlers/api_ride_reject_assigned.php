<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/RiderAuthService.php';
require_once $backendRoot . '/src/RideRepository.php';
require_once $backendRoot . '/src/AppSettingsRepository.php';
require_once $backendRoot . '/src/DispatchService.php';
require_once $backendRoot . '/src/RateLimiter.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\AppSettingsRepository;
use VprideBackend\Database;
use VprideBackend\DispatchService;
use VprideBackend\RateLimiter;
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

$rideId = (int) ($GLOBALS['vpride_ride_path_id'] ?? 0);
if ($rideId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_ride'], JSON_THROW_ON_ERROR);
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

$max = (int) (getenv('API_RATE_LIMIT_RIDE_ACTION_PER_MIN') ?: '30');
if (! RateLimiter::allow('rider_reject_driver', (string) $user['rider_user_id'], max(1, $max), 60)) {
    http_response_code(429);
    echo json_encode(['error' => 'rate_limited'], JSON_THROW_ON_ERROR);
    exit;
}

$pdo = Database::pdo();
$settings = (new AppSettingsRepository($pdo))->getDispatchSettings();
$maxR = (int) ($settings['maxRiderDriverRejects'] ?? 0);

$rides = new RideRepository($pdo);
$code = $rides->riderRejectAssignedDriver($rideId, (int) $user['rider_user_id'], $maxR);
if ($code === 'not_found' || $code === 'not_rider') {
    http_response_code(404);
    echo json_encode(['error' => 'not_found'], JSON_THROW_ON_ERROR);
    exit;
}
if ($code === 'bad_state') {
    http_response_code(409);
    echo json_encode(['error' => 'cannot_reject'], JSON_THROW_ON_ERROR);
    exit;
}
if ($code === 'limit') {
    http_response_code(409);
    echo json_encode(['error' => 'reject_limit_reached'], JSON_THROW_ON_ERROR);
    exit;
}

try {
    (new DispatchService($pdo))->tryAutoAssign($rideId);
} catch (Throwable $e) {
    error_log('[vpride] rider reject driver auto-dispatch: ' . $e->getMessage());
}

echo json_encode(['ok' => true, 'rideId' => $rideId], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
