<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/DriverApiContext.php';
require_once $backendRoot . '/src/RideRepository.php';
require_once $backendRoot . '/src/PlatformPromoSettingsRepository.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\DriverApiContext;
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
$total = $rides->sumCompletedEarningsForDriver($ctx['riderUserId']);
$count = $rides->countCompletedTripsForDriver($ctx['riderUserId']);
$cur = 'NGN';
$decimals = 2;
if (PlatformPromoSettingsRepository::tableExists($pdo)) {
    $s = (new PlatformPromoSettingsRepository($pdo))->getSettings();
    $cur = $s['currency_code'];
    $decimals = $s['decimal_places'];
}

echo json_encode([
    'completedTrips' => $count,
    'grossFareTotal' => round($total, $decimals),
    'currency' => $cur,
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
