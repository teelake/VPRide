<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/RiderAuthService.php';
require_once $backendRoot . '/src/RideRepository.php';
require_once $backendRoot . '/src/PaymentProofUpload.php';
require_once $backendRoot . '/src/RateLimiter.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\PaymentProofUpload;
use VprideBackend\RateLimiter;
use VprideBackend\RideRepository;
use VprideBackend\RiderAuthService;

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

$pdo = Database::pdo();
$ride = (new RideRepository($pdo))->findByIdForRiderUser($rideId, $user['rider_user_id']);
if ($ride === null) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found'], JSON_THROW_ON_ERROR);
    exit;
}
if (($ride['status'] ?? '') !== 'completed' || ($ride['payment_status'] ?? '') !== 'pending') {
    http_response_code(409);
    echo json_encode(['error' => 'cannot_upload_proof'], JSON_THROW_ON_ERROR);
    exit;
}

try {
    $url = PaymentProofUpload::saveFromRequest('proof', $backendRoot, $rideId);
    echo json_encode(['proofUrl' => $url], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('[vpride] payment proof: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'upload_failed', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}
