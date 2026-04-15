<?php

declare(strict_types=1);

namespace VprideBackend;

/**
 * Simple per-IP fixed-window rate limit using the temp directory (no Redis required).
 */
final class RateLimiter
{
    /**
     * @return bool true if allowed, false if rate limited
     */
    public static function allow(
        string $routeKey,
        string $ip,
        int $maxHits,
        int $windowSeconds,
    ): bool {
        if ($maxHits < 1 || $windowSeconds < 1) {
            return true;
        }
        $safeIp = preg_replace('/[^0-9a-fA-F:.]/', '_', $ip) ?: 'unknown';
        $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'vpride_rl';
        if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            return true;
        }
        $file = $dir . DIRECTORY_SEPARATOR . hash('sha256', $routeKey . '|' . $safeIp) . '.json';
        $now = time();
        $windowStart = intdiv($now, $windowSeconds) * $windowSeconds;

        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            return true;
        }
        try {
            if (! flock($fp, LOCK_EX)) {
                return true;
            }
            $raw = stream_get_contents($fp);
            $count = 0;
            $storedWindow = $windowStart;
            if (is_string($raw) && $raw !== '') {
                try {
                    /** @var array<string, mixed> $j */
                    $j = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                    $storedWindow = (int) ($j['w'] ?? $windowStart);
                    $count = (int) ($j['n'] ?? 0);
                } catch (\Throwable) {
                    $count = 0;
                    $storedWindow = $windowStart;
                }
            }
            if ($storedWindow !== $windowStart) {
                $storedWindow = $windowStart;
                $count = 0;
            }
            if ($count >= $maxHits) {
                return false;
            }
            $count++;
            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, json_encode(['w' => $storedWindow, 'n' => $count], JSON_THROW_ON_ERROR));
            fflush($fp);

            return true;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public static function clientIp(): string
    {
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if (is_string($xff) && $xff !== '') {
            $first = trim(explode(',', $xff, 2)[0]);

            return $first !== '' ? $first : '0.0.0.0';
        }

        return is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
}
