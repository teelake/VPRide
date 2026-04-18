<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/RiderAuthService.php';
require_once $backendRoot . '/src/RideRepository.php';
require_once $backendRoot . '/src/RiderRideViewPresenter.php';
require_once $backendRoot . '/src/RateLimiter.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\RateLimiter;
use VprideBackend\RideRepository;
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

$auth = new RiderAuthService(Database::pdo());
$user = $auth->resolveBearerToken($token);
if ($user === null) {
    http_response_code(401);
    echo json_encode(['error' => 'invalid_session'], JSON_THROW_ON_ERROR);
    exit;
}

$maxPay = (int) (getenv('API_RATE_LIMIT_RIDE_PAYMENT_POST_PER_HOUR') ?: '60');
if (! RateLimiter::allow('ride_payment_post', (string) $user['rider_user_id'], max(1, $maxPay), 3600)) {
    http_response_code(429);
    echo json_encode(['error' => 'rate_limited'], JSON_THROW_ON_ERROR);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
try {
    /** @var mixed $data */
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_json'], JSON_THROW_ON_ERROR);
    exit;
}

if (! is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_body'], JSON_THROW_ON_ERROR);
    exit;
}

$method = isset($data['method']) && is_string($data['method']) ? trim($data['method']) : '';
$proofUrl = isset($data['proofUrl']) && is_string($data['proofUrl']) ? trim($data['proofUrl']) : null;
if ($proofUrl === '') {
    $proofUrl = null;
}
$note = isset($data['referenceNote']) && is_string($data['referenceNote']) ? trim($data['referenceNote']) : null;
if ($note === '') {
    $note = null;
}

$pdo = Database::pdo();
$repo = new RideRepository($pdo);
$ok = $repo->riderSubmitOfflinePayment(
    $rideId,
    $user['rider_user_id'],
    $method,
    $proofUrl,
    $note,
);
if (! $ok) {
    http_response_code(409);
    echo json_encode(['error' => 'cannot_submit_payment'], JSON_THROW_ON_ERROR);
    exit;
}

$ride = $repo->findByIdForRiderUser($rideId, $user['rider_user_id']);
if ($ride === null) {
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
    exit;
}

echo json_encode([
    'ride' => RiderRideViewPresenter::toRiderArray($pdo, $ride),
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
