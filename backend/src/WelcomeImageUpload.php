<?php

declare(strict_types=1);

namespace VprideBackend;

use RuntimeException;

/**
 * Stores a welcome-screen hero under public/uploads/welcome/ and returns absolute URL.
 * Validates magic bytes + dimensions, resizes very large sources, re-encodes as WebP (preferred)
 * or JPEG at high quality to reduce payload without visible loss on phones.
 */
final class WelcomeImageUpload
{
    private const MAX_UPLOAD_BYTES = 3 * 1024 * 1024;

    /** Reject decompression bombs / oversized sources. */
    private const MAX_SOURCE_EDGE_PX = 8192;

    private const MAX_MEGAPIXELS = 36;

    /** Longest edge after processing — enough for @3x phones, smaller downloads. */
    private const TARGET_MAX_LONG_EDGE = 2560;

    private const JPEG_QUALITY = 90;

    private const WEBP_QUALITY = 88;

    public static function saveFromRequest(string $fieldName, string $backendRoot): ?string
    {
        if (! isset($_FILES[$fieldName]) || ! is_array($_FILES[$fieldName])) {
            return null;
        }
        $f = $_FILES[$fieldName];
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Image upload failed (code ' . (int) ($f['error'] ?? 0) . ').');
        }
        $size = (int) ($f['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_UPLOAD_BYTES) {
            throw new RuntimeException('Image must be under 3 MB.');
        }
        $tmp = (string) ($f['tmp_name'] ?? '');
        if ($tmp === '' || ! is_uploaded_file($tmp)) {
            throw new RuntimeException('Invalid upload.');
        }
        if (! function_exists('finfo_open')) {
            throw new RuntimeException('fileinfo extension required for image uploads.');
        }
        $fi = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $fi->file($tmp);
        $allowed = [
            'image/jpeg' => true,
            'image/png' => true,
            'image/webp' => true,
        ];
        if (! is_string($mime) || ! isset($allowed[$mime])) {
            throw new RuntimeException('Only JPEG, PNG, or WebP images are allowed.');
        }

        $info = @getimagesize($tmp);
        if ($info === false || ! isset($info[0], $info[1])) {
            throw new RuntimeException('Could not read image dimensions (file may be corrupt).');
        }
        $w = (int) $info[0];
        $h = (int) $info[1];
        if ($w < 8 || $h < 8) {
            throw new RuntimeException('Image is too small (minimum 8×8 pixels).');
        }
        if ($w > self::MAX_SOURCE_EDGE_PX || $h > self::MAX_SOURCE_EDGE_PX) {
            throw new RuntimeException('Image too large (max ' . self::MAX_SOURCE_EDGE_PX . ' px per side).');
        }
        if (($w * $h) > self::MAX_MEGAPIXELS * 1_000_000) {
            throw new RuntimeException('Image has too many pixels — use a smaller resolution.');
        }

        if (! extension_loaded('gd')) {
            throw new RuntimeException('PHP GD extension is required to optimize welcome images. Enable gd in php.ini.');
        }

        return self::processAndStore($tmp, $backendRoot);
    }

    private static function processAndStore(string $tmpPath, string $backendRoot): string
    {
        $binary = file_get_contents($tmpPath);
        if ($binary === false || $binary === '') {
            throw new RuntimeException('Could not read uploaded file.');
        }
        $src = @imagecreatefromstring($binary);
        if ($src === false) {
            throw new RuntimeException('The file could not be decoded as an image.');
        }

        $w = imagesx($src);
        $h = imagesy($src);
        [$tw, $th] = self::targetDimensions($w, $h);

        $work = $src;
        if ($tw !== $w || $th !== $h) {
            $work = self::resizeTruecolor($src, $w, $h, $tw, $th);
            imagedestroy($src);
        }

        $dir = $backendRoot . '/public/uploads/welcome';
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            imagedestroy($work);
            throw new RuntimeException('Could not create uploads directory.');
        }

        $useWebp = function_exists('imagewebp');
        $ext = $useWebp ? 'webp' : 'jpg';
        $name = 'hero_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $name;

        $ok = false;
        if ($useWebp) {
            imagepalettetotruecolor($work);
            imagesavealpha($work, true);
            $ok = imagewebp($work, $dest, self::WEBP_QUALITY);
        } else {
            self::flattenAndSaveJpeg($work, $dest);
            $ok = is_file($dest) && filesize($dest) > 0;
        }
        imagedestroy($work);

        if (! $ok || ! is_file($dest) || filesize($dest) < 1) {
            if (is_file($dest)) {
                @unlink($dest);
            }
            throw new RuntimeException('Could not save optimized image (check GD WebP/JPEG support).');
        }

        return Config::absoluteUrl('/uploads/welcome/' . $name);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function targetDimensions(int $w, int $h): array
    {
        $long = max($w, $h);
        if ($long <= self::TARGET_MAX_LONG_EDGE) {
            return [$w, $h];
        }
        $scale = self::TARGET_MAX_LONG_EDGE / $long;
        $tw = max(1, (int) round($w * $scale));
        $th = max(1, (int) round($h * $scale));

        return [$tw, $th];
    }

    /**
     * @param resource $src
     * @return resource
     */
    private static function resizeTruecolor($src, int $sw, int $sh, int $dw, int $dh)
    {
        if (! imageistruecolor($src)) {
            imagepalettetotruecolor($src);
        }
        $dst = imagecreatetruecolor($dw, $dh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $dw, $dh, $transparent);
        imagealphablending($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dw, $dh, $sw, $sh);

        return $dst;
    }

    /**
     * @param resource $im
     */
    private static function flattenAndSaveJpeg($im, string $dest): void
    {
        $w = imagesx($im);
        $h = imagesy($im);
        $flat = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($flat, 255, 255, 255);
        imagefilledrectangle($flat, 0, 0, $w, $h, $white);
        imagecopy($flat, $im, 0, 0, 0, 0, $w, $h);
        imagejpeg($flat, $dest, self::JPEG_QUALITY);
        imagedestroy($flat);
    }
}
