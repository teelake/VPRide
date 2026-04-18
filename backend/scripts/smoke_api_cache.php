<?php

declare(strict_types=1);

/**
 * Quick repeat GETs to exercise Cache-Control / ETag (304) on public JSON endpoints.
 * Usage: php smoke_api_cache.php https://your-host https://your-host/api/v1/config/public 25
 *
 * Args: baseUrl path iterations (defaults: iterations 20)
 */

$base = rtrim($argv[1] ?? '', '/');
$path = $argv[2] ?? '/api/v1/config/public';
$iters = max(1, min(500, (int) ($argv[3] ?? 20)));

if ($base === '') {
    fwrite(STDERR, "Usage: php smoke_api_cache.php <baseUrl> [path] [iterations]\n");
    exit(1);
}

$url = $base . (str_starts_with($path, '/') ? $path : '/' . $path);
$etag = null;
$ok = 0;
$notModified = 0;

for ($i = 0; $i < $iters; $i++) {
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($etag !== null) {
        $headers[] = 'If-None-Match: ' . $etag;
    }
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    if (! is_string($raw)) {
        fwrite(STDERR, "curl failed on $url\n");
        exit(2);
    }
    $hdr = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    if (preg_match('/^ETag:\\s*(.+)$/mi', $hdr, $m)) {
        $etag = trim($m[1]);
    }
    if ($code === 304) {
        $notModified++;
    } elseif ($code === 200) {
        $ok++;
        if ($body === '' && $i === 0) {
            fwrite(STDERR, "Warning: empty body on 200\n");
        }
    } else {
        fwrite(STDERR, "HTTP $code for $url\n$body\n");
        exit(3);
    }
}

echo "OK 200 responses: $ok, 304 Not Modified: $notModified (iterations $iters)\n";
