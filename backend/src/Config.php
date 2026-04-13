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
}
