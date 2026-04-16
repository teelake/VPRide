<?php

declare(strict_types=1);

namespace VprideBackend;

final class Config
{
    public static function load(string $envPath): void
    {
        if (! is_readable($envPath)) {
            return;
        }
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (! str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\"'");
            if ($k !== '' && getenv($k) === false) {
                putenv("{$k}={$v}");
                $_ENV[$k] = $v;
            }
        }
    }

    public static function dbDsn(): string
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_DATABASE') ?: 'vpride';

        return "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    }

    public static function dbUser(): string
    {
        return getenv('DB_USERNAME') ?: 'root';
    }

    public static function dbPass(): string
    {
        return getenv('DB_PASSWORD') ?: '';
    }

    /**
     * URL path prefix when the app is not at the domain root (shared hosting subfolder).
     * Example: site is https://example.com/vpride/backend/public → set APP_BASE_PATH=vpride/backend/public
     * No leading/trailing slashes required; empty when the app is at domain root.
     */
    public static function basePath(): string
    {
        $raw = getenv('APP_BASE_PATH') ?: '';
        $raw = trim($raw, "/ \t\r\n");
        if ($raw === '') {
            return '';
        }

        return '/' . $raw;
    }

    /** Absolute path for redirects and links, e.g. /vpride/backend/public/admin/login */
    public static function url(string $path): string
    {
        $base = self::basePath();
        $path = '/' . ltrim($path, '/');

        return $base . $path;
    }

    /**
     * Full URL for password-reset emails and external links.
     * Uses PUBLIC_BASE_URL from .env when set; otherwise the current request origin + APP_BASE_PATH.
     */
    public static function absoluteUrl(string $path): string
    {
        $path = self::url($path);
        $fromEnv = trim((string) getenv('PUBLIC_BASE_URL'));
        $fromEnv = rtrim($fromEnv, '/');
        if ($fromEnv !== '') {
            return $fromEnv . $path;
        }
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return $path;
        }
        $https = (! empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
        $scheme = $https ? 'https' : 'http';

        return $scheme . '://' . $host . $path;
    }
}
