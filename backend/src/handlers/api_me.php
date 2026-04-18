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
require_once $backendRoot . '/src/DriverFleetRepository.php';
require_once $backendRoot . '/src/DriverAvailabilityRepository.php';
require_once $backendRoot . '/src/RateLimiter.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\DriverAvailabilityRepository;
use VprideBackend\DriverFleetRepository;
use VprideBackend\PlatformPromoSettingsRepository;
use VprideBackend\RateLimiter;
use VprideBackend\RiderAuthService;
use VprideBackend\RiderLoyaltyRepository;
use VprideBackend\RiderRewardGrantRepository;

Config::load($backendRoot . '/.env');

ApiMobileCors::sendPreflightIfOptions();
ApiMobileCors::headers();

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (! in_array($method, ['GET', 'PATCH', 'OPTIONS'], true)) {
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

if ($method === 'PATCH') {
    $max = (int) (getenv('API_RATE_LIMIT_ME_PATCH_PER_HOUR') ?: '60');
    if (! RateLimiter::allow('me_patch', (string) $row['rider_user_id'], max(1, $max), 3600)) {
        http_response_code(429);
        echo json_encode(['error' => 'rate_limited'], JSON_THROW_ON_ERROR);
        exit;
    }

    $raw = file_get_contents('php://input') ?: '';
    try {
        /** @var mixed $data */
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        error_log('[vpride] PATCH /api/v1/me invalid_json: ' . $e->getMessage());
        http_response_code(400);
        echo json_encode(['error' => 'invalid_json'], JSON_THROW_ON_ERROR);
        exit;
    }

    if (! is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_body'], JSON_THROW_ON_ERROR);
        exit;
    }

    $displayName = isset($data['displayName']) && is_string($data['displayName']) ? $data['displayName'] : null;
    if ($displayName === null) {
        http_response_code(400);
        echo json_encode([
            'error' => 'display_name_required',
            'message' => 'displayName is required.',
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    try {
        $svc->updateDisplayName($row['rider_user_id'], $displayName);
    } catch (RuntimeException $e) {
        $code = $e->getMessage();
        if ($code === 'display_name_required') {
            http_response_code(400);
            echo json_encode([
                'error' => $code,
                'message' => 'Please enter your name.',
            ], JSON_THROW_ON_ERROR);
            exit;
        }
        if ($code === 'display_name_too_long') {
            http_response_code(400);
            echo json_encode([
                'error' => $code,
                'message' => 'Name is too long (max 255 characters).',
            ], JSON_THROW_ON_ERROR);
            exit;
        }
        error_log('[vpride] PATCH /api/v1/me: ' . $code);
        http_response_code(500);
        echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
        exit;
    }

    $user = $svc->getUserPayloadForMe($row['rider_user_id']);
    if ($user === null) {
        http_response_code(500);
        echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
        exit;
    }

    echo json_encode(['user' => $user], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

// GET
try {
    $pdo = Database::pdo();
    $user = $svc->getUserPayloadForMe($row['rider_user_id']);
    if ($user === null) {
        http_response_code(500);
        echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
        exit;
    }

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

    $payload = ['user' => $user];
    $fleetRow = (new DriverFleetRepository($pdo))->findActiveFleetRowForRiderUser($row['rider_user_id']);
    if ($fleetRow !== null) {
        $av = (new DriverAvailabilityRepository($pdo))->getStatus($row['rider_user_id']);
        $payload['driver'] = [
            'fleetDriverId' => (int) $fleetRow['id'],
            'fullName' => (string) $fleetRow['full_name'],
            'availability' => $av,
        ];
    }
    if ($loyalty !== null) {
        $payload['loyalty'] = $loyalty;
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('[vpride] GET /api/v1/me: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
}
