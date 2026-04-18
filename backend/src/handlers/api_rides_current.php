<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/RiderAuthService.php';
require_once $backendRoot . '/src/RideRepository.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\RideRepository;
use VprideBackend\RiderAuthService;

Config::load($backendRoot . '/.env');

ApiMobileCors::sendPreflightIfOptions();
ApiMobileCors::headers();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
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

try {
    $ride = (new RideRepository(Database::pdo()))->findActiveRideForRiderUser($user['rider_user_id']);
    if ($ride === null) {
        echo json_encode(['ride' => null], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }
    $decimals = 2;
    echo json_encode([
        'ride' => [
            'id' => (int) $ride['id'],
            'status' => (string) $ride['status'],
            'riderUserId' => (int) $ride['rider_user_id'],
            'driverUserId' => isset($ride['driver_rider_user_id']) && $ride['driver_rider_user_id'] !== null
                ? (int) $ride['driver_rider_user_id']
                : null,
            'pickup' => [
                'latitude' => isset($ride['pickup_lat']) ? (float) $ride['pickup_lat'] : null,
                'longitude' => isset($ride['pickup_lng']) ? (float) $ride['pickup_lng'] : null,
                'address' => $ride['pickup_address'] !== null ? (string) $ride['pickup_address'] : null,
            ],
            'pricing' => [
                'estimatedFare' => isset($ride['estimated_fare_amount']) && $ride['estimated_fare_amount'] !== null
                    ? round((float) $ride['estimated_fare_amount'], $decimals)
                    : null,
                'promoDiscount' => isset($ride['promo_discount_amount'])
                    ? round((float) $ride['promo_discount_amount'], $decimals)
                    : 0.0,
                'finalFare' => isset($ride['final_fare_amount']) && $ride['final_fare_amount'] !== null
                    ? round((float) $ride['final_fare_amount'], $decimals)
                    : null,
                'currency' => isset($ride['fare_currency']) ? (string) $ride['fare_currency'] : 'NGN',
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('[vpride] GET /api/v1/rides/current: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
}
