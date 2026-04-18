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
require_once $backendRoot . '/src/FixedPricingService.php';
require_once $backendRoot . '/src/PromotionRepository.php';
require_once $backendRoot . '/src/RiderRewardGrantRepository.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\AppSettingsRepository;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\FarePromoService;
use VprideBackend\FixedPricingService;
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
$plat = (float) ($pick['latitude'] ?? 0);
$plng = (float) ($pick['longitude'] ?? 0);
if (($plat === 0.0 && $plng === 0.0) || $plat < -90 || $plat > 90 || $plng < -180 || $plng > 180) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_coordinates'], JSON_THROW_ON_ERROR);
    exit;
}

$pickAddr = null;
if (isset($pick['address']) && is_string($pick['address'])) {
    $pickAddr = mb_substr(trim($pick['address']), 0, 500);
    if ($pickAddr === '') {
        $pickAddr = null;
    }
}

$dest = isset($data['destination']) && is_array($data['destination']) ? $data['destination'] : null;
$dlat = $dest !== null ? (float) ($dest['latitude'] ?? 0) : null;
$dlng = $dest !== null ? (float) ($dest['longitude'] ?? 0) : null;
$dropAddr = null;
if ($dest !== null && isset($dest['address']) && is_string($dest['address'])) {
    $dropAddr = mb_substr(trim($dest['address']), 0, 500);
    if ($dropAddr === '') {
        $dropAddr = null;
    }
}

$distanceKm = null;
if ($dlat !== null && $dlng !== null && ! ($dlat === 0.0 && $dlng === 0.0)) {
    if ($dlat < -90 || $dlat > 90 || $dlng < -180 || $dlng > 180) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_destination'], JSON_THROW_ON_ERROR);
        exit;
    }
    $distanceKm = round(FixedPricingService::haversineKm($plat, $plng, $dlat, $dlng), 5);
}

$roundTrip = ! empty($data['roundTrip']);
if ($roundTrip && $distanceKm === null) {
    http_response_code(400);
    echo json_encode(['error' => 'destination_required_for_round_trip'], JSON_THROW_ON_ERROR);
    exit;
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
$settings = PlatformPromoSettingsRepository::tableExists($pdo)
    ? (new PlatformPromoSettingsRepository($pdo))->getSettings()
    : null;

$scheduledUtc = null;
if (isset($data['scheduledPickupAt']) && is_string($data['scheduledPickupAt'])) {
    $rawS = trim($data['scheduledPickupAt']);
    if ($rawS !== '') {
        if ($settings === null) {
            http_response_code(503);
            echo json_encode(['error' => 'platform_settings_missing'], JSON_THROW_ON_ERROR);
            exit;
        }
        try {
            $dt = new DateTimeImmutable($rawS);
        } catch (Throwable) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid_scheduled_pickup'], JSON_THROW_ON_ERROR);
            exit;
        }
        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if ($dt < $nowUtc->modify('+5 minutes')) {
            http_response_code(400);
            echo json_encode(['error' => 'scheduling_in_past'], JSON_THROW_ON_ERROR);
            exit;
        }
        $maxDays = (int) ($settings['advance_booking_max_days'] ?? 30);
        $maxUtc = $nowUtc->modify('+' . max(1, min(365, $maxDays)) . ' days');
        if ($dt > $maxUtc) {
            http_response_code(400);
            echo json_encode(['error' => 'scheduling_too_far'], JSON_THROW_ON_ERROR);
            exit;
        }
        $scheduledUtc = $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}

if ($scheduledUtc !== null && $rides->countFutureScheduledBookingsForRider($user['rider_user_id']) > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'scheduled_booking_limit'], JSON_THROW_ON_ERROR);
    exit;
}

$baseFare = null;
if ($distanceKm !== null && $settings !== null) {
    $baseFare = FixedPricingService::fareBeforePromosFromDistance($settings, $distanceKm);
}

$pricing = [
    'estimated_fare' => 1500.0,
    'promo_discount' => 0.0,
    'final_fare' => 1500.0,
    'currency' => 'NGN',
    'decimal_places' => 2,
    'applied_promotion_id' => null,
    'promo_code_used' => null,
    'reward_grant_id' => null,
];
if (PlatformPromoSettingsRepository::tableExists($pdo)) {
    $pricing = (new FarePromoService($pdo))->computeForNewRide(
        $user['rider_user_id'],
        $promoCode,
        $baseFare,
    );
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

$pricingReturn = null;
if ($roundTrip && $distanceKm !== null && PlatformPromoSettingsRepository::tableExists($pdo) && $settings !== null) {
    $baseRet = FixedPricingService::fareBeforePromosFromDistance($settings, $distanceKm);
    $pricingReturn = (new FarePromoService($pdo))->computeForNewRide(
        $user['rider_user_id'],
        null,
        $baseRet,
        true,
    );
}

$pricingPayload = static function (array $p): array {
    return [
        'estimated_fare' => $p['estimated_fare'],
        'promo_discount' => $p['promo_discount'],
        'final_fare' => $p['final_fare'],
        'currency' => $p['currency'],
        'applied_promotion_id' => $p['applied_promotion_id'] ?? null,
        'promo_code_used' => $p['promo_code_used'] ?? null,
        'reward_grant_id' => $p['reward_grant_id'] ?? null,
    ];
};

$metaBase = [
    'dropoff_lat' => $dlat,
    'dropoff_lng' => $dlng,
    'dropoff_address' => $dropAddr,
    'scheduled_pickup_at' => $scheduledUtc,
    'distance_km' => $distanceKm,
];

$encodePricing = static function (array $pricing, int $decimals): array {
    return [
        'estimatedFare' => round((float) $pricing['estimated_fare'], $decimals),
        'promoDiscount' => round((float) $pricing['promo_discount'], $decimals),
        'finalFare' => round((float) $pricing['final_fare'], $decimals),
        'currency' => (string) ($pricing['currency'] ?? 'NGN'),
        'appliedPromotionId' => $pricing['applied_promotion_id'],
        'promoCodeApplied' => $pricing['promo_code_used'],
    ];
};

try {
    $pdo->beginTransaction();
    try {
        $decimals = (int) ($pricing['decimal_places'] ?? 2);
        $bookings = [];

        $maybeRedeem = static function (int $rideId, array $pr) use ($pdo, $user): void {
            $appliedPid = $pr['applied_promotion_id'] ?? null;
            if ($appliedPid !== null && PromotionRepository::tableExists($pdo)) {
                (new PromotionRepository($pdo))->recordRedemption(
                    (int) $appliedPid,
                    $user['rider_user_id'],
                    $rideId,
                    (float) ($pr['promo_discount'] ?? 0),
                );
            }
        };

        if (! $roundTrip || $pricingReturn === null) {
            $id = $rides->createRequested(
                $user['rider_user_id'],
                $plat,
                $plng,
                $pickAddr,
                $pricingPayload($pricing),
                array_merge($metaBase, ['trip_leg' => 'single']),
            );
            $maybeRedeem($id, $pricing);
            $gid = $grantId;
            if ($gid !== null && RiderRewardGrantRepository::tableExists($pdo)) {
                (new RiderRewardGrantRepository($pdo))->markApplied((int) $gid, $id);
                $chk = $pdo->prepare(
                    'SELECT id FROM rider_reward_grant WHERE id = ? AND status = \'applied\' AND applied_ride_id = ? LIMIT 1',
                );
                $chk->execute([(int) $gid, $id]);
                if ($chk->fetchColumn() === false) {
                    throw new \RuntimeException('reward_grant_not_applied');
                }
            }
            $bookings[] = [
                'id' => $id,
                'leg' => 'single',
                'status' => 'requested',
                'pricing' => $encodePricing($pricing, $decimals),
            ];
        } else {
            $idOut = $rides->createRequested(
                $user['rider_user_id'],
                $plat,
                $plng,
                $pickAddr,
                $pricingPayload($pricing),
                array_merge($metaBase, ['trip_leg' => 'outbound']),
            );
            $maybeRedeem($idOut, $pricing);
            if ($grantId !== null && RiderRewardGrantRepository::tableExists($pdo)) {
                (new RiderRewardGrantRepository($pdo))->markApplied((int) $grantId, $idOut);
                $chk = $pdo->prepare(
                    'SELECT id FROM rider_reward_grant WHERE id = ? AND status = \'applied\' AND applied_ride_id = ? LIMIT 1',
                );
                $chk->execute([(int) $grantId, $idOut]);
                if ($chk->fetchColumn() === false) {
                    throw new \RuntimeException('reward_grant_not_applied');
                }
            }

            $idRet = $rides->createRequested(
                $user['rider_user_id'],
                (float) $dlat,
                (float) $dlng,
                $dropAddr,
                $pricingPayload($pricingReturn),
                [
                    'dropoff_lat' => $plat,
                    'dropoff_lng' => $plng,
                    'dropoff_address' => $pickAddr,
                    'scheduled_pickup_at' => null,
                    'distance_km' => $distanceKm,
                    'trip_leg' => 'return',
                    'companion_ride_id' => $idOut,
                ],
            );
            $maybeRedeem($idRet, $pricingReturn);
            $rides->setCompanionRideIds($idOut, $idRet);

            $bookings[] = [
                'id' => $idOut,
                'leg' => 'outbound',
                'status' => 'requested',
                'pricing' => $encodePricing($pricing, $decimals),
            ];
            $bookings[] = [
                'id' => $idRet,
                'leg' => 'return',
                'status' => 'requested',
                'pricing' => $encodePricing($pricingReturn, $decimals),
            ];
        }

        $pdo->commit();

        $totalFinal = 0.0;
        foreach ($bookings as $b) {
            $totalFinal += (float) $b['pricing']['finalFare'];
        }

        $first = $bookings[0];
        echo json_encode([
            'id' => $first['id'],
            'status' => 'requested',
            'pricing' => $first['pricing'],
            'bookings' => $bookings,
            'totalFinalFare' => round($totalFinal, $decimals),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (Throwable $e) {
    error_log('[vpride] POST /api/v1/rides: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
}
