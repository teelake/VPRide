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
     * Example (subfolder): https://vpride.ca/backend → APP_BASE_PATH=backend. Root (https://vpride.ca/): leave empty.
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

    /** Absolute path for redirects and links, e.g. /login (APP_BASE_PATH is prepended for subfolder deploys). */
    public static function url(string $path): string
    {
        $base = self::basePath();
        $path = '/' . ltrim($path, '/');

        return $base . $path;
    }

    /**
     * Full URL for password-reset emails, welcome hero images, and external links.
     *
     * [PUBLIC_BASE_URL] may be either:
     * - origin only (e.g. https://vpride.ca) — path is appended as APP_BASE_PATH + path; or
     * - full public entry URL if not at host root (e.g. https://vpride.ca/backend) — must match the
     *   start of the path-only URL so it is not duplicated.
     */
    public static function absoluteUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            $path = '/';
        }
        $base = self::basePath();
        $norm = '/' . ltrim($path, '/');
        if ($base !== '' && (str_starts_with($norm, $base . '/') || $norm === $base)) {
            $fullPath = $norm;
        } else {
            $fullPath = self::url($path);
        }

        $fromEnv = rtrim(trim((string) getenv('PUBLIC_BASE_URL')), '/');
        if ($fromEnv !== '') {
            $parsed = parse_url($fromEnv);
            if (is_array($parsed) && isset($parsed['scheme'], $parsed['host'])) {
                $origin = $parsed['scheme'] . '://' . $parsed['host']
                    . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
                $envPath = isset($parsed['path']) ? rtrim((string) $parsed['path'], '/') : '';
                if ($envPath !== '' && (str_starts_with($fullPath, $envPath . '/') || $fullPath === $envPath)) {
                    return $origin . $fullPath;
                }
            }

            return $fromEnv . $fullPath;
        }
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return $fullPath;
        }
        $https = (! empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
        $scheme = $https ? 'https' : 'http';

        return $scheme . '://' . $host . $fullPath;
    }
}
