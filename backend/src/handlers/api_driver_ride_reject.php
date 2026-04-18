<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/DriverApiContext.php';
require_once $backendRoot . '/src/DispatchService.php';
require_once $backendRoot . '/src/RideRepository.php';
require_once $backendRoot . '/src/RateLimiter.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\DispatchService;
use VprideBackend\DriverApiContext;
use VprideBackend\RateLimiter;
use VprideBackend\RideRepository;

Config::load($backendRoot . '/.env');

ApiMobileCors::sendPreflightIfOptions();
ApiMobileCors::headers();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed'], JSON_THROW_ON_ERROR);
    exit;
}

$rideId = (int) ($GLOBALS['vpride_driver_ride_id'] ?? 0);
if ($rideId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_ride'], JSON_THROW_ON_ERROR);
    exit;
}

$pdo = Database::pdo();
$ctx = DriverApiContext::requireFleetDriver($pdo);
$maxMut = (int) (getenv('API_RATE_LIMIT_DRIVER_RIDE_ACTIONS_PER_HOUR') ?: '400');
if (! RateLimiter::allow('driver_ride_mut', (string) $ctx['riderUserId'], max(1, $maxMut), 3600)) {
    http_response_code(429);
    echo json_encode(['error' => 'rate_limited'], JSON_THROW_ON_ERROR);
    exit;
}
$rides = new RideRepository($pdo);
$row = $rides->findById($rideId);
if ($row === null
    || (int) ($row['driver_rider_user_id'] ?? 0) !== $ctx['riderUserId']
    || ($row['status'] ?? '') !== 'requested') {
    http_response_code(409);
    echo json_encode(['error' => 'cannot_reject'], JSON_THROW_ON_ERROR);
    exit;
}

$rides->recordDriverRefusal($rideId, $ctx['riderUserId']);
$rides->clearDriverOnRequestedRide($rideId);
try {
    (new DispatchService($pdo))->tryAutoAssign($rideId);
} catch (Throwable $e) {
    error_log('[vpride] driver reject reassign: ' . $e->getMessage());
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
