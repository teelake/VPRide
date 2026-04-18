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

$max = (int) (getenv('API_RATE_LIMIT_ME_PHOTO_PER_HOUR') ?: '30');
if (! RateLimiter::allow('me_photo', RateLimiter::clientIp(), max(1, $max), 3600)) {
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

$svc = new RiderAuthService(Database::pdo());
$sess = $svc->resolveBearerToken($token);
if ($sess === null) {
    http_response_code(401);
    echo json_encode(['error' => 'invalid_session'], JSON_THROW_ON_ERROR);
    exit;
}

$riderId = $sess['rider_user_id'];

if (! isset($_FILES['photo']) || ! is_array($_FILES['photo'])) {
    http_response_code(400);
    echo json_encode(['error' => 'photo_required'], JSON_THROW_ON_ERROR);
    exit;
}

$file = $_FILES['photo'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'upload_failed'], JSON_THROW_ON_ERROR);
    exit;
}

$maxBytes = 2 * 1024 * 1024;
$size = (int) ($file['size'] ?? 0);
if ($size < 1 || $size > $maxBytes) {
    http_response_code(400);
    echo json_encode([
        'error' => 'file_too_large',
        'message' => 'Photo must be under 2 MB.',
    ], JSON_THROW_ON_ERROR);
    exit;
}

$tmp = (string) ($file['tmp_name'] ?? '');
if ($tmp === '' || ! is_readable($tmp)) {
    http_response_code(400);
    echo json_encode(['error' => 'upload_invalid'], JSON_THROW_ON_ERROR);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($tmp);
$extMap = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
if (! is_string($mime) || ! isset($extMap[$mime])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'invalid_image_type',
        'message' => 'Use JPEG, PNG, or WebP.',
    ], JSON_THROW_ON_ERROR);
    exit;
}
$ext = $extMap[$mime];

$dirFs = $backendRoot . '/public/uploads/riders/' . $riderId;
if (! is_dir($dirFs) && ! @mkdir($dirFs, 0755, true)) {
    error_log('[vpride] me/photo mkdir failed: ' . $dirFs);
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
    exit;
}

$basename = 'avatar-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
$destFs = $dirFs . '/' . $basename;
if (! move_uploaded_file($tmp, $destFs)) {
    error_log('[vpride] me/photo move failed');
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
    exit;
}

foreach (glob($dirFs . '/avatar-*.*') ?: [] as $old) {
    if (is_string($old) && $old !== $destFs && is_file($old)) {
        @unlink($old);
    }
}

$publicPath = '/uploads/riders/' . $riderId . '/' . $basename;
$absolute = Config::absoluteUrl($publicPath);

try {
    $svc->setPhotoUrl($riderId, $absolute);
} catch (Throwable $e) {
    @unlink($destFs);
    error_log('[vpride] me/photo db: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
    exit;
}

$user = $svc->getUserPayloadForMe($riderId);
if ($user === null) {
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
    exit;
}

echo json_encode(['user' => $user], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
