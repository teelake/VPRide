<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/RiderAuthService.php';
require_once $backendRoot . '/src/PlatformPromoSettingsRepository.php';
require_once $backendRoot . '/src/RiderLoyaltyRepository.php';
require_once $backendRoot . '/src/RiderRewardGrantRepository.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\PlatformPromoSettingsRepository;
use VprideBackend\RiderAuthService;
use VprideBackend\RiderLoyaltyRepository;
use VprideBackend\RiderRewardGrantRepository;

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

$svc = new RiderAuthService(Database::pdo());
$row = $svc->resolveBearerToken($token);
if ($row === null) {
    http_response_code(401);
    echo json_encode(['error' => 'invalid_session'], JSON_THROW_ON_ERROR);
    exit;
}

try {
    $pdo = Database::pdo();
    $loyalty = null;
    if (PlatformPromoSettingsRepository::tableExists($pdo)) {
        $ps = (new PlatformPromoSettingsRepository($pdo))->getSettings();
        if ($ps['loyalty_enabled']) {
            $paid = (new RiderLoyaltyRepository($pdo))->getPaidTripsCount($row['rider_user_id']);
            $n = max(1, (int) $ps['loyalty_trips_per_reward']);
            $inCycle = $paid % $n;
            $loyalty = [
                'enabled' => true,
                'paidTrips' => $paid,
                'tripsPerReward' => $n,
                'progressInCycle' => $inCycle,
                'availableRewards' => (new RiderRewardGrantRepository($pdo))->countAvailableForRider($row['rider_user_id']),
            ];
        }
    }

    $payload = [
        'user' => [
            'id' => $row['rider_user_id'],
            'email' => $row['email'],
            'displayName' => $row['display_name'],
            'photoUrl' => $row['photo_url'],
        ],
    ];
    if ($loyalty !== null) {
        $payload['loyalty'] = $loyalty;
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('[vpride] GET /api/v1/me: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
}
