<?php

declare(strict_types=1);

namespace VprideBackend;

use RuntimeException;

/**
 * Stores payment proof images under public/uploads/ride_payments/ (JPEG/PNG/WebP).
 */
final class PaymentProofUpload
{
    private const MAX_BYTES = 2 * 1024 * 1024;

    private const MAX_EDGE = 4096;

    public static function saveFromRequest(string $fieldName, string $backendRoot, int $rideId): string
    {
        if ($rideId < 1) {
            throw new RuntimeException('Invalid ride.');
        }
        if (! isset($_FILES[$fieldName]) || ! is_array($_FILES[$fieldName])) {
            throw new RuntimeException('No file uploaded.');
        }
        $f = $_FILES[$fieldName];
        if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed (code ' . (int) ($f['error'] ?? 0) . ').');
        }
        $size = (int) ($f['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_BYTES) {
            throw new RuntimeException('File must be under 2 MB.');
        }
        $tmp = (string) ($f['tmp_name'] ?? '');
        if ($tmp === '' || ! is_uploaded_file($tmp)) {
            throw new RuntimeException('Invalid upload.');
        }
        if (! function_exists('finfo_open')) {
            throw new RuntimeException('fileinfo extension required.');
        }
        $fi = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $fi->file($tmp);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (! is_string($mime) || ! isset($allowed[$mime])) {
            throw new RuntimeException('Only JPEG, PNG, or WebP allowed.');
        }
        $info = @getimagesize($tmp);
        if ($info === false || ! isset($info[0], $info[1])) {
            throw new RuntimeException('Could not read image.');
        }
        $w = (int) $info[0];
        $h = (int) $info[1];
        if ($w > self::MAX_EDGE || $h > self::MAX_EDGE) {
            throw new RuntimeException('Image too large (max ' . self::MAX_EDGE . ' px per side).');
        }

        $ext = $allowed[$mime];
        $dir = $backendRoot . '/public/uploads/ride_payments/' . $rideId;
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('Could not create upload directory.');
        }
        $name = 'proof_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = $dir . '/' . $name;
        if (! move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException('Could not store file.');
        }

        return Config::absoluteUrl('/uploads/ride_payments/' . $rideId . '/' . $name);
    }
}
