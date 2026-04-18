<?php

declare(strict_types=1);

namespace VprideBackend;

/**
 * JSON mobile API: CORS + security headers. Use HTTPS in production.
 *
 * Env API_CORS_ORIGIN:
 * - unset or * → Access-Control-Allow-Origin: *
 * - single URL → always emit that origin (works for one Flutter web origin + native clients)
 * - comma-separated URLs → echo matching request Origin when present; otherwise first entry for native/curl
 */
final class ApiMobileCors
{
    public static function sendPreflightIfOptions(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'OPTIONS') {
            return false;
        }
        self::applyCorsHeaders();
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
        header('Access-Control-Max-Age: 86400');
        http_response_code(204);
        exit;
    }

    public static function headers(): void
    {
        self::applyCorsHeaders();
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
    }

    private static function applyCorsHeaders(): void
    {
        $raw = getenv('API_CORS_ORIGIN');
        if (! is_string($raw)) {
            $raw = '';
        }
        $raw = trim($raw);
        if ($raw === '' || $raw === '*') {
            header('Access-Control-Allow-Origin: *');
            header('Vary: Origin');

            return;
        }

        $list = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn ($s) => $s !== ''));
        if ($list === []) {
            header('Access-Control-Allow-Origin: *');
            header('Vary: Origin');

            return;
        }

        $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $emit = null;
        if (is_string($requestOrigin) && $requestOrigin !== '' && in_array($requestOrigin, $list, true)) {
            $emit = $requestOrigin;
        } elseif (count($list) === 1) {
            $emit = $list[0];
        } elseif ($requestOrigin === '' || $requestOrigin === null) {
            $emit = $list[0];
        }

        if ($emit !== null) {
            header('Access-Control-Allow-Origin: ' . $emit);
            header('Access-Control-Allow-Credentials: true');
        }
        header('Vary: Origin');
    }
}
