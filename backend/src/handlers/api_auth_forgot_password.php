<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/RiderAuthService.php';
require_once $backendRoot . '/src/RiderPasswordResetRepository.php';
require_once $backendRoot . '/src/RateLimiter.php';
require_once $backendRoot . '/src/Mailer.php';
require_once $backendRoot . '/src/AppSettingsRepository.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\AppSettingsRepository;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\Mailer;
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

$max = (int) (getenv('API_RATE_LIMIT_FORGOT_PASSWORD_PER_HOUR') ?: '20');
if (! RateLimiter::allow('auth_forgot_password', RateLimiter::clientIp(), max(1, $max), 3600)) {
    http_response_code(429);
    echo json_encode(['error' => 'rate_limited'], JSON_THROW_ON_ERROR);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
try {
    /** @var mixed $data */
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('[vpride] POST /api/v1/auth/forgot-password invalid_json: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'invalid_json'], JSON_THROW_ON_ERROR);
    exit;
}

if (! is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_body'], JSON_THROW_ON_ERROR);
    exit;
}

$email = isset($data['email']) && is_string($data['email']) ? trim($data['email']) : '';
if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_email'], JSON_THROW_ON_ERROR);
    exit;
}

try {
    $pdo = Database::pdo();
    $auth = new RiderAuthService($pdo);
    $riderId = $auth->findRiderIdWithPasswordByEmail($email);
    if ($riderId !== null) {
        $resets = new RiderPasswordResetRepository($pdo);
        $rawToken = $resets->createTokenForRider($riderId);
        $link = Config::absoluteUrl('/rider/reset-password?token=' . rawurlencode($rawToken));
        $body = "Reset your VP Ride password using this link (valid for one hour):\r\n\r\n"
            . $link
            . "\r\n\r\nIf you did not request this, you can ignore this email.\r\n";
        $from = AppSettingsRepository::emailOutboundEffective($pdo)['mailFrom'];
        $ok = Mailer::sendPlain(
            $email,
            'VP Ride — reset your password',
            $body,
            $from !== '' ? $from : null,
        );
        if (! $ok) {
            error_log('[vpride] rider password reset email failed for ' . $email);
        }
    }
    echo json_encode([
        'ok' => true,
        'message' => 'If that email has a password account, you will receive a reset link shortly.',
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('[vpride] POST /api/v1/auth/forgot-password: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
}
