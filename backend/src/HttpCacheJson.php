<?php

declare(strict_types=1);

namespace VprideBackend;

/**
 * Cacheable JSON responses: ETag + Cache-Control, 304 when If-None-Match matches.
 */
final class HttpCacheJson
{
    public static function emit(string $json, int $maxAgeSeconds, int $staleWhileRevalidateSeconds = 0): void
    {
        $etag = 'W/"' . hash('sha256', $json) . '"';
        header('ETag: ' . $etag);
        $cc = 'public, max-age=' . max(0, $maxAgeSeconds);
        if ($staleWhileRevalidateSeconds > 0) {
            $cc .= ', stale-while-revalidate=' . $staleWhileRevalidateSeconds;
        }
        header('Cache-Control: ' . $cc);
        $inm = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if (is_string($inm) && $inm !== '' && self::etagMatches($inm, $etag)) {
            http_response_code(304);

            return;
        }
        echo $json;
    }

    private static function etagMatches(string $ifNoneMatch, string $etag): bool
    {
        $want = self::normalizeEtag($etag);
        foreach (explode(',', $ifNoneMatch) as $part) {
            $p = self::normalizeEtag(trim($part));
            if ($p === '*' || $p === $want) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeEtag(string $e): string
    {
        $e = trim($e);
        if ($e === '') {
            return '';
        }
        if (str_starts_with(strtoupper($e), 'W/')) {
            $e = trim(substr($e, 2));
        }

        return trim($e, '"');
    }
}
