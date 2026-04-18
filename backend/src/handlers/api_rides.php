<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/RiderAuthService.php';
require_once $backendRoot . '/src/RideRepository.php';
require_once $backendRoot . '/src/AppSettingsRepository.php';
require_once $backendRoot . '/src/PlatformPromoSettingsRepository.php';
require_once $backendRoot . '/src/FarePromoService.php';
require_once $backendRoot . '/src/PromotionRepository.php';
require_once $backendRoot . '/src/RiderRewardGrantRepository.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\AppSettingsRepository;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\FarePromoService;
use VprideBackend\PlatformPromoSettingsRepository;
use VprideBackend\PromotionRepository;
use VprideBackend\RideRepository;
use VprideBackend\RiderAuthService;
use VprideBackend\RiderRewardGrantRepository;

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
} catch (Throwable $e) {
    error_log('[vpride] POST /api/v1/rides invalid_json: ' . $e->getMessage());
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

$promoCode = null;
if (! empty($feat['promoCodeEntryEnabled']) && isset($data['promoCode']) && is_string($data['promoCode'])) {
    $promoCode = trim($data['promoCode']);
    if ($promoCode === '') {
        $promoCode = null;
    }
}

$pdo = Database::pdo();
$rides = new RideRepository($pdo);

$pricing = [
    'estimated_fare' => 0.0,
    'promo_discount' => 0.0,
    'final_fare' => 0.0,
    'currency' => 'NGN',
    'decimal_places' => 2,
    'applied_promotion_id' => null,
    'promo_code_used' => null,
    'reward_grant_id' => null,
];
if (PlatformPromoSettingsRepository::tableExists($pdo)) {
    $pricing = (new FarePromoService($pdo))->computeForNewRide($user['rider_user_id'], $promoCode);
}

$grantId = $pricing['reward_grant_id'] ?? null;
if ($grantId !== null && RiderRewardGrantRepository::tableExists($pdo)) {
    $ok = (new RiderRewardGrantRepository($pdo))->assertGrantAvailableForRider((int) $grantId, $user['rider_user_id']);
    if (! $ok) {
        http_response_code(409);
        echo json_encode(['error' => 'reward_unavailable'], JSON_THROW_ON_ERROR);
        exit;
    }
}

$pricingPayload = [
    'estimated_fare' => $pricing['estimated_fare'],
    'promo_discount' => $pricing['promo_discount'],
    'final_fare' => $pricing['final_fare'],
    'currency' => $pricing['currency'],
    'applied_promotion_id' => $pricing['applied_promotion_id'] ?? null,
    'promo_code_used' => $pricing['promo_code_used'] ?? null,
    'reward_grant_id' => $grantId,
];

try {
    $pdo->beginTransaction();
    try {
        $id = $rides->createRequested(
            $user['rider_user_id'],
            $lat,
            $lng,
            $addr,
            $pricingPayload,
        );
        $appliedPid = $pricing['applied_promotion_id'] ?? null;
        if ($appliedPid !== null && PromotionRepository::tableExists($pdo)) {
            (new PromotionRepository($pdo))->recordRedemption(
                (int) $appliedPid,
                $user['rider_user_id'],
                $id,
                (float) ($pricing['promo_discount'] ?? 0),
            );
        }
        if ($grantId !== null && RiderRewardGrantRepository::tableExists($pdo)) {
            (new RiderRewardGrantRepository($pdo))->markApplied((int) $grantId, $id);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $decimals = (int) ($pricing['decimal_places'] ?? 2);
    echo json_encode([
        'id' => $id,
        'status' => 'requested',
        'pricing' => [
            'estimatedFare' => round((float) $pricing['estimated_fare'], $decimals),
            'promoDiscount' => round((float) $pricing['promo_discount'], $decimals),
            'finalFare' => round((float) $pricing['final_fare'], $decimals),
            'currency' => (string) ($pricing['currency'] ?? 'NGN'),
            'appliedPromotionId' => $pricing['applied_promotion_id'],
            'promoCodeApplied' => $pricing['promo_code_used'],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('[vpride] POST /api/v1/rides: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
}
