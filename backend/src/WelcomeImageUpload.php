<?php

declare(strict_types=1);

namespace VprideBackend;

use RuntimeException;

/**
 * Stores a welcome-screen hero under public/uploads/welcome/ and returns absolute URL.
 */
final class WelcomeImageUpload
{
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
        if ($size < 1 || $size > 3 * 1024 * 1024) {
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
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (! is_string($mime) || ! isset($map[$mime])) {
            throw new RuntimeException('Only JPEG, PNG, or WebP images are allowed.');
        }
        $ext = $map[$mime];
        $dir = $backendRoot . '/public/uploads/welcome';
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('Could not create uploads directory.');
        }
        $name = 'hero_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $name;
        if (! move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException('Could not store image.');
        }

        return Config::absoluteUrl(Config::url('/uploads/welcome/' . $name));
    }
}
