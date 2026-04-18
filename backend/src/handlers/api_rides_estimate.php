<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/RiderAuthService.php';
require_once $backendRoot . '/src/AppSettingsRepository.php';
require_once $backendRoot . '/src/PlatformPromoSettingsRepository.php';
require_once $backendRoot . '/src/FarePromoService.php';
require_once $backendRoot . '/src/FixedPricingService.php';
require_once $backendRoot . '/src/RateLimiter.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\AppSettingsRepository;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\FarePromoService;
use VprideBackend\FixedPricingService;
use VprideBackend\PlatformPromoSettingsRepository;
use VprideBackend\RateLimiter;
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

$maxEst = (int) (getenv('API_RATE_LIMIT_RIDE_ESTIMATE_PER_HOUR') ?: '200');
if (! RateLimiter::allow('ride_estimate', (string) $user['rider_user_id'], max(1, $maxEst), 3600)) {
    http_response_code(429);
    echo json_encode(['error' => 'rate_limited'], JSON_THROW_ON_ERROR);
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

$dest = isset($data['destination']) && is_array($data['destination']) ? $data['destination'] : null;
$dlat = $dest !== null ? (float) ($dest['latitude'] ?? 0) : null;
$dlng = $dest !== null ? (float) ($dest['longitude'] ?? 0) : null;
$distanceKm = null;
if ($dlat !== null && $dlng !== null && ! ($dlat === 0.0 && $dlng === 0.0)) {
    if ($dlat < -90 || $dlat > 90 || $dlng < -180 || $dlng > 180) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_destination'], JSON_THROW_ON_ERROR);
        exit;
    }
    $distanceKm = round(FixedPricingService::haversineKm($plat, $plng, $dlat, $dlng), 3);
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
$settings = PlatformPromoSettingsRepository::tableExists($pdo)
    ? (new PlatformPromoSettingsRepository($pdo))->getSettings()
    : null;

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

$decimals = (int) ($pricing['decimal_places'] ?? 2);
$leg = [
    'estimatedFare' => round((float) $pricing['estimated_fare'], $decimals),
    'promoDiscount' => round((float) $pricing['promo_discount'], $decimals),
    'finalFare' => round((float) $pricing['final_fare'], $decimals),
    'currency' => (string) ($pricing['currency'] ?? 'NGN'),
    'appliedPromotionId' => $pricing['applied_promotion_id'],
    'promoCodeApplied' => $pricing['promo_code_used'],
];

$returnLeg = null;
if ($roundTrip && $distanceKm !== null && PlatformPromoSettingsRepository::tableExists($pdo)) {
    $baseRet = FixedPricingService::fareBeforePromosFromDistance($settings, $distanceKm);
    $pRet = (new FarePromoService($pdo))->computeForNewRide(
        $user['rider_user_id'],
        null,
        $baseRet,
        true,
    );
    $returnLeg = [
        'estimatedFare' => round((float) $pRet['estimated_fare'], $decimals),
        'promoDiscount' => round((float) $pRet['promo_discount'], $decimals),
        'finalFare' => round((float) $pRet['final_fare'], $decimals),
        'currency' => (string) ($pRet['currency'] ?? 'NGN'),
        'appliedPromotionId' => $pRet['applied_promotion_id'],
        'promoCodeApplied' => $pRet['promo_code_used'],
    ];
}

$totalFinal = (float) $leg['finalFare'];
if ($returnLeg !== null) {
    $totalFinal += (float) $returnLeg['finalFare'];
}

echo json_encode([
    'distanceKm' => $distanceKm,
    'roundTrip' => $roundTrip,
    'pricing' => $leg,
    'returnPricing' => $returnLeg,
    'totalFinalFare' => round($totalFinal, $decimals),
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
