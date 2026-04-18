<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/RiderAuthService.php';
require_once $backendRoot . '/src/RideRepository.php';
require_once $backendRoot . '/src/RiderRideViewPresenter.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\RideRepository;
use VprideBackend\RiderAuthService;
use VprideBackend\RiderRideViewPresenter;

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
    $pdo = Database::pdo();
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
    $limit = max(1, min(100, $limit));
    $beforeId = isset($_GET['before_id']) ? (int) $_GET['before_id'] : 0;
    $beforeId = $beforeId > 0 ? $beforeId : null;
    $rows = (new RideRepository($pdo))->listForRiderUser($user['rider_user_id'], $limit, $beforeId);
    $rides = RiderRideViewPresenter::mapManyForRider($pdo, $rows);
    $nextBeforeId = null;
    if (count($rows) === $limit && $rows !== []) {
        $last = $rows[array_key_last($rows)];
        $nextBeforeId = isset($last['id']) ? (int) $last['id'] : null;
    }
    echo json_encode(
        [
            'rides' => $rides,
            'nextBeforeId' => $nextBeforeId,
        ],
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    );
} catch (Throwable $e) {
    error_log('[vpride] GET /api/v1/rides/mine: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
}
