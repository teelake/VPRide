<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/RiderAuthService.php';
require_once $backendRoot . '/src/RideRepository.php';
require_once $backendRoot . '/src/AppSettingsRepository.php';
require_once $backendRoot . '/src/RiderRideViewPresenter.php';
require_once $backendRoot . '/src/RateLimiter.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\AppSettingsRepository;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\RideRepository;
use VprideBackend\RateLimiter;
use VprideBackend\RiderAuthService;
use VprideBackend\RiderRideViewPresenter;

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

$pdo = Database::pdo();
$auth = new RiderAuthService($pdo);
$user = $auth->resolveBearerToken($token);
if ($user === null) {
    http_response_code(401);
    echo json_encode(['error' => 'invalid_session'], JSON_THROW_ON_ERROR);
    exit;
}

$maxCancel = (int) (getenv('API_RATE_LIMIT_RIDE_CANCEL_PER_HOUR') ?: '45');
if (! RateLimiter::allow('ride_cancel', (string) $user['rider_user_id'], max(1, $maxCancel), 3600)) {
    http_response_code(429);
    echo json_encode(['error' => 'rate_limited'], JSON_THROW_ON_ERROR);
    exit;
}

try {
    $fee = (new AppSettingsRepository($pdo))->getOperations()['riderCancellationFeeAmount'];
    $rides = new RideRepository($pdo);
    $out = $rides->riderCancelRide($rideId, $user['rider_user_id'], (float) $fee);
    if ($out === 'not_found') {
        http_response_code(404);
        echo json_encode(
            [
                'error' => 'not_found',
                'message' => 'Ride not found.',
            ],
            JSON_THROW_ON_ERROR,
        );
        exit;
    }
    if ($out === 'not_rider') {
        http_response_code(403);
        echo json_encode(
            [
                'error' => 'forbidden',
                'message' => 'You cannot cancel this ride.',
            ],
            JSON_THROW_ON_ERROR,
        );
        exit;
    }
    if ($out === 'cannot_cancel') {
        http_response_code(409);
        echo json_encode(
            [
                'error' => 'cannot_cancel',
                'message' => 'This ride can no longer be cancelled. It may have already finished or been cancelled.',
            ],
            JSON_THROW_ON_ERROR,
        );
        exit;
    }
    $ride = $rides->findByIdForRiderUser($rideId, $user['rider_user_id']);
    if ($ride === null) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found'], JSON_THROW_ON_ERROR);
        exit;
    }
    echo json_encode([
        'ride' => RiderRideViewPresenter::toRiderArray($pdo, $ride),
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('[vpride] POST /api/v1/rides/{id}/cancel: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
}
