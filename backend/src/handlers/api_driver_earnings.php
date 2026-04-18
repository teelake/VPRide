<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/DriverApiContext.php';
require_once $backendRoot . '/src/RideRepository.php';
require_once $backendRoot . '/src/PlatformPromoSettingsRepository.php';
require_once $backendRoot . '/src/DriverEarningsPolicy.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\DriverApiContext;
use VprideBackend\DriverEarningsPolicy;
use VprideBackend\PlatformPromoSettingsRepository;
use VprideBackend\RideRepository;

Config::load($backendRoot . '/.env');

ApiMobileCors::sendPreflightIfOptions();
ApiMobileCors::headers();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed'], JSON_THROW_ON_ERROR);
    exit;
}

$pdo = Database::pdo();
$ctx = DriverApiContext::requireFleetDriver($pdo);
$rides = new RideRepository($pdo);
$riderUserId = $ctx['riderUserId'];
$gross = $rides->sumCompletedGrossFareForDriver($riderUserId);
$globalPct = DriverEarningsPolicy::globalPercent($pdo);
$driverShare = $rides->sumCompletedDriverShareForDriver($riderUserId, $globalPct);
$effectivePct = DriverEarningsPolicy::effectivePercentForDriverRiderUserId($pdo, $riderUserId);
$count = $rides->countCompletedTripsForDriver($riderUserId);
$cur = 'NGN';
$decimals = 2;
if (PlatformPromoSettingsRepository::tableExists($pdo)) {
    $s = (new PlatformPromoSettingsRepository($pdo))->getSettings();
    $cur = $s['currency_code'];
    $decimals = $s['decimal_places'];
}

$platformApprox = max(0.0, round($gross - $driverShare, $decimals));

echo json_encode([
    'completedTrips' => $count,
    'grossFareTotal' => round($gross, $decimals),
    'driverShareTotal' => round($driverShare, $decimals),
    'platformShareApprox' => $platformApprox,
    'driverEarningsPercentEffective' => $effectivePct,
    'driverEarningsPercentGlobal' => $globalPct,
    'currency' => $cur,
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
