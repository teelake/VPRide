<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/GoogleIdTokenVerifier.php';
require_once $backendRoot . '/src/RiderAuthService.php';
require_once $backendRoot . '/src/RateLimiter.php';
require_once $backendRoot . '/src/AppSettingsRepository.php';
require_once $backendRoot . '/src/DriverFleetRepository.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\AppSettingsRepository;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\DriverFleetRepository;
use VprideBackend\GoogleIdTokenVerifier;
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

$maxAuth = (int) (getenv('API_RATE_LIMIT_AUTH_PER_HOUR') ?: '60');
if (! RateLimiter::allow('auth_google', RateLimiter::clientIp(), max(1, $maxAuth), 3600)) {
    http_response_code(429);
    echo json_encode(['error' => 'rate_limited'], JSON_THROW_ON_ERROR);
    exit;
}

if (! class_exists(\Firebase\JWT\JWT::class)) {
    http_response_code(500);
    echo json_encode([
        'error' => 'server_misconfigured',
        'message' => 'Run composer install in the backend directory.',
    ], JSON_THROW_ON_ERROR);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
try {
    /** @var mixed $data */
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('[vpride] POST /api/v1/auth/google invalid_json: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'invalid_json'], JSON_THROW_ON_ERROR);
    exit;
}

$idToken = is_array($data) && isset($data['idToken']) && is_string($data['idToken'])
    ? trim($data['idToken'])
    : '';
if ($idToken === '') {
    http_response_code(400);
    echo json_encode(['error' => 'id_token_required'], JSON_THROW_ON_ERROR);
    exit;
}

$clientId = AppSettingsRepository::effectiveGoogleOAuthClientId(Database::pdo());
try {
    $payload = GoogleIdTokenVerifier::verify($idToken, $clientId);
    if (isset($payload->email_verified) && $payload->email_verified === false) {
        http_response_code(401);
        echo json_encode(['error' => 'email_not_verified'], JSON_THROW_ON_ERROR);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(401);
    echo json_encode([
        'error' => 'invalid_id_token',
        'message' => $e->getMessage(),
    ], JSON_THROW_ON_ERROR);
    exit;
}

$googleEmail = isset($payload->email) && is_string($payload->email)
    ? trim(strtolower($payload->email))
    : '';
if ($googleEmail !== '' && filter_var($googleEmail, FILTER_VALIDATE_EMAIL)
    && DriverFleetRepository::fleetDriverEmailExists(Database::pdo(), $googleEmail)) {
    http_response_code(403);
    echo json_encode([
        'error' => 'fleet_driver_invite_only',
        'message' => 'This email is used for a fleet driver account. Sign in with email and the password your administrator sent you.',
    ], JSON_THROW_ON_ERROR);
    exit;
}

try {
    $svc = new RiderAuthService(Database::pdo());
    $out = $svc->issueSessionForGoogleUser($payload);
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (\RuntimeException $e) {
    $m = $e->getMessage();
    if ($m === 'name_required') {
        http_response_code(400);
        echo json_encode([
            'error' => $m,
            'message' => 'Your Google account must include a name. Add it in your Google profile, or sign up with email.',
        ], JSON_THROW_ON_ERROR);
        exit;
    }
    if ($m === 'email_has_password_account' || $m === 'email_linked_other_google') {
        http_response_code(409);
        echo json_encode(['error' => $m], JSON_THROW_ON_ERROR);
        exit;
    }
    error_log('[vpride] POST /api/v1/auth/google session_error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('[vpride] POST /api/v1/auth/google session_error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
}
