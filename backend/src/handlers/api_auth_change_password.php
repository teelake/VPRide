<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/RiderAuthService.php';
require_once $backendRoot . '/src/RateLimiter.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\Config;
use VprideBackend\Database;
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

$max = (int) (getenv('API_RATE_LIMIT_CHANGE_PASSWORD_PER_HOUR') ?: '20');
if (! RateLimiter::allow('auth_change_password', RateLimiter::clientIp(), max(1, $max), 3600)) {
    http_response_code(429);
    echo json_encode(['error' => 'rate_limited'], JSON_THROW_ON_ERROR);
    exit;
}

$token = RiderAuthService::readBearerFromRequest();
if ($token === null || $token === '') {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized'], JSON_THROW_ON_ERROR);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
try {
    /** @var mixed $data */
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('[vpride] POST /api/v1/auth/change-password invalid_json: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'invalid_json'], JSON_THROW_ON_ERROR);
    exit;
}

if (! is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_body'], JSON_THROW_ON_ERROR);
    exit;
}

$current = isset($data['currentPassword']) && is_string($data['currentPassword']) ? $data['currentPassword'] : '';
$new = isset($data['newPassword']) && is_string($data['newPassword']) ? $data['newPassword'] : '';
$confirm = isset($data['newPasswordConfirm']) && is_string($data['newPasswordConfirm'])
    ? $data['newPasswordConfirm']
    : '';

if ($new !== $confirm) {
    http_response_code(400);
    echo json_encode([
        'error' => 'password_mismatch',
        'message' => 'New password and confirmation do not match.',
    ], JSON_THROW_ON_ERROR);
    exit;
}

$svc = new RiderAuthService(Database::pdo());
$userRow = $svc->resolveBearerToken($token);
if ($userRow === null) {
    http_response_code(401);
    echo json_encode(['error' => 'invalid_session'], JSON_THROW_ON_ERROR);
    exit;
}

try {
    $out = $svc->changePasswordAuthenticated(
        $userRow['rider_user_id'],
        $current,
        $new,
    );
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (RuntimeException $e) {
    $code = $e->getMessage();
    if ($code === 'invalid_credentials') {
        http_response_code(401);
        echo json_encode([
            'error' => $code,
            'message' => 'Current password is incorrect.',
        ], JSON_THROW_ON_ERROR);
        exit;
    }
    if ($code === 'no_password_account') {
        http_response_code(400);
        echo json_encode([
            'error' => $code,
            'message' => 'This account uses Google sign-in. Password change is not available.',
        ], JSON_THROW_ON_ERROR);
        exit;
    }
    if ($code === 'password_too_short') {
        http_response_code(400);
        echo json_encode([
            'error' => $code,
            'message' => 'New password must be at least 8 characters.',
        ], JSON_THROW_ON_ERROR);
        exit;
    }
    if ($code === 'password_unchanged') {
        http_response_code(400);
        echo json_encode([
            'error' => $code,
            'message' => 'Choose a different password than your current one.',
        ], JSON_THROW_ON_ERROR);
        exit;
    }
    error_log('[vpride] POST /api/v1/auth/change-password: ' . $code);
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('[vpride] POST /api/v1/auth/change-password: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
}
