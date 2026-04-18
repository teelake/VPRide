<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/RiderAuthService.php';
require_once $backendRoot . '/src/RateLimiter.php';
require_once $backendRoot . '/src/Mailer.php';
require_once $backendRoot . '/src/AppSettingsRepository.php';
require_once $backendRoot . '/src/DriverFleetRepository.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\AppSettingsRepository;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\DriverFleetRepository;
use VprideBackend\Mailer;
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

$max = (int) (getenv('API_RATE_LIMIT_REGISTER_PER_HOUR') ?: '40');
if (! RateLimiter::allow('auth_register', RateLimiter::clientIp(), max(1, $max), 3600)) {
    http_response_code(429);
    echo json_encode(['error' => 'rate_limited'], JSON_THROW_ON_ERROR);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
try {
    /** @var mixed $data */
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('[vpride] POST /api/v1/auth/register invalid_json: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'invalid_json'], JSON_THROW_ON_ERROR);
    exit;
}

if (! is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_body'], JSON_THROW_ON_ERROR);
    exit;
}

$email = isset($data['email']) && is_string($data['email']) ? $data['email'] : '';
$password = isset($data['password']) && is_string($data['password']) ? $data['password'] : '';
$displayName = null;

$emailNorm = trim(strtolower($email));
if ($emailNorm !== '' && filter_var($emailNorm, FILTER_VALIDATE_EMAIL)
    && DriverFleetRepository::fleetDriverEmailExists(Database::pdo(), $emailNorm)) {
    http_response_code(403);
    echo json_encode([
        'error' => 'fleet_driver_invite_only',
        'message' => 'This email is reserved for a fleet driver account. Use the password your administrator emailed you, or sign in with that email.',
    ], JSON_THROW_ON_ERROR);
    exit;
}

if (isset($data['displayName']) && is_string($data['displayName'])) {
    $displayName = mb_substr(trim($data['displayName']), 0, 255);
    if ($displayName === '') {
        $displayName = null;
    }
}

try {
    $out = (new RiderAuthService(Database::pdo()))->registerWithPassword($email, $password, $displayName);
    try {
        $uid = (int) ($out['user']['id'] ?? 0);
        $riderEmail = (string) ($out['user']['email'] ?? '');
        $riderName = $out['user']['displayName'] ?? null;
        $dn = is_string($riderName) ? $riderName : '';
        $greeting = $dn !== '' ? 'Hi ' . $dn . ",\n\n" : "Hello,\n\n";
        $vars = [
            'email' => $riderEmail,
            'displayName' => $dn,
            'userId' => (string) $uid,
            'greeting' => $greeting,
        ];
        $cfg = AppSettingsRepository::emailOutboundEffective(Database::pdo());
        $from = $cfg['mailFrom'] !== '' ? $cfg['mailFrom'] : null;
        if ($cfg['staffNotifyOnRiderSignup'] && trim($cfg['staffNotifyEmails']) !== '') {
            $subj = Mailer::expandTemplate($cfg['staffNotifySubject'], $vars);
            $body = Mailer::expandTemplate($cfg['staffNotifyBody'], $vars);
            foreach (array_filter(array_map('trim', explode(',', $cfg['staffNotifyEmails']))) as $adminAddr) {
                if (! Mailer::sendPlain($adminAddr, $subj, $body, $from)) {
                    error_log('[vpride] staff rider-signup notify failed for ' . $adminAddr);
                }
            }
        }
        if ($cfg['riderWelcomeEnabled'] && $riderEmail !== '') {
            $wSub = Mailer::expandTemplate($cfg['riderWelcomeSubject'], $vars);
            $wBody = Mailer::expandTemplate($cfg['riderWelcomeBody'], $vars);
            if (! Mailer::sendPlain($riderEmail, $wSub, $wBody, $from)) {
                error_log('[vpride] rider welcome email failed for ' . $riderEmail);
            }
        }
    } catch (Throwable $mailEx) {
        error_log('[vpride] rider signup notification: ' . $mailEx->getMessage());
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (RuntimeException $e) {
    $code = $e->getMessage();
    if ($code === 'invalid_email' || $code === 'password_too_short') {
        http_response_code(400);
        echo json_encode(['error' => $code], JSON_THROW_ON_ERROR);
        exit;
    }
    if ($code === 'display_name_required') {
        http_response_code(400);
        echo json_encode([
            'error' => $code,
            'message' => 'Please enter your full name.',
        ], JSON_THROW_ON_ERROR);
        exit;
    }
    if ($code === 'display_name_too_long') {
        http_response_code(400);
        echo json_encode([
            'error' => $code,
            'message' => 'Name is too long (max 255 characters).',
        ], JSON_THROW_ON_ERROR);
        exit;
    }
    if ($code === 'email_taken') {
        http_response_code(409);
        echo json_encode(['error' => 'email_taken'], JSON_THROW_ON_ERROR);
        exit;
    }
    error_log('[vpride] POST /api/v1/auth/register: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('[vpride] POST /api/v1/auth/register: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
}
