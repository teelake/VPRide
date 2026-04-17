<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/RiderAuthService.php';
require_once $backendRoot . '/src/RiderPasswordResetRepository.php';
require_once $backendRoot . '/src/RateLimiter.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\Database;
use VprideBackend\RateLimiter;
use VprideBackend\RiderAuthService;
use VprideBackend\RiderPasswordResetRepository;

Config::load($backendRoot . '/.env');

ApiMobileCors::sendPreflightIfOptions();
ApiMobileCors::headers();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed'], JSON_THROW_ON_ERROR);
    exit;
}

$max = (int) (getenv('API_RATE_LIMIT_RESET_PASSWORD_PER_HOUR') ?: '40');
if (! RateLimiter::allow('auth_reset_password', RateLimiter::clientIp(), max(1, $max), 3600)) {
    http_response_code(429);
    echo json_encode(['error' => 'rate_limited'], JSON_THROW_ON_ERROR);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
try {
    /** @var mixed $data */
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('[vpride] POST /api/v1/auth/reset-password invalid_json: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'invalid_json'], JSON_THROW_ON_ERROR);
    exit;
}

if (! is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_body'], JSON_THROW_ON_ERROR);
    exit;
}

$token = isset($data['token']) && is_string($data['token']) ? trim($data['token']) : '';
$password = isset($data['password']) && is_string($data['password']) ? $data['password'] : '';
$passwordConfirm = isset($data['passwordConfirm']) && is_string($data['passwordConfirm'])
    ? $data['passwordConfirm']
    : '';

if ($token === '') {
    http_response_code(400);
    echo json_encode(['error' => 'token_required'], JSON_THROW_ON_ERROR);
    exit;
}

if ($password !== $passwordConfirm) {
    http_response_code(400);
    echo json_encode(['error' => 'password_mismatch'], JSON_THROW_ON_ERROR);
    exit;
}

try {
    $pdo = Database::pdo();
    $resets = new RiderPasswordResetRepository($pdo);
    $validRow = $resets->findValidRowByRawToken($token);
    if ($validRow === null) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_or_expired_token'], JSON_THROW_ON_ERROR);
        exit;
    }
    $auth = new RiderAuthService($pdo);
    $auth->setPasswordFromReset($validRow['rider_user_id'], $password);
    $resets->markUsed($validRow['id']);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (RuntimeException $e) {
    if ($e->getMessage() === 'password_too_short') {
        http_response_code(400);
        echo json_encode(['error' => 'password_too_short'], JSON_THROW_ON_ERROR);
        exit;
    }
    error_log('[vpride] POST /api/v1/auth/reset-password: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('[vpride] POST /api/v1/auth/reset-password: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
}
