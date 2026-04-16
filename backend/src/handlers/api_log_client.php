<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/RateLimiter.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\Config;
use VprideBackend\RateLimiter;

Config::load($backendRoot . '/.env');

ApiMobileCors::sendPreflightIfOptions();
ApiMobileCors::headers();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed'], JSON_THROW_ON_ERROR);
    exit;
}

$raw = file_get_contents('php://input');
if (! is_string($raw) || strlen($raw) > 8192) {
    http_response_code(413);
    echo json_encode(['error' => 'payload_too_large'], JSON_THROW_ON_ERROR);
    exit;
}

$max = (int) (getenv('API_RATE_LIMIT_CLIENT_LOG_PER_HOUR') ?: '180');
$ip = RateLimiter::clientIp();
if (! RateLimiter::allow('client_log', $ip, $max, 3600)) {
    http_response_code(429);
    echo json_encode(['error' => 'rate_limited'], JSON_THROW_ON_ERROR);
    exit;
}

try {
    /** @var mixed $decoded */
    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_json'], JSON_THROW_ON_ERROR);
    exit;
}

if (! is_array($decoded)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_body'], JSON_THROW_ON_ERROR);
    exit;
}

$level = strtolower(trim((string) ($decoded['level'] ?? 'error')));
if (! in_array($level, ['debug', 'info', 'warning', 'error', 'fatal'], true)) {
    $level = 'error';
}
$message = trim((string) ($decoded['message'] ?? ''));
if ($message === '' || strlen($message) > 4000) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_message'], JSON_THROW_ON_ERROR);
    exit;
}

$context = $decoded['context'] ?? null;
if ($context !== null && ! is_array($context)) {
    $context = ['value' => $context];
}

$line = json_encode(
    [
        'ts' => gmdate('c'),
        'ip' => $ip,
        'level' => $level,
        'message' => $message,
        'context' => $context,
    ],
    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
);
error_log('[vpride-client] ' . $line);

http_response_code(204);
exit;
