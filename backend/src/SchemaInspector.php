<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;
use PDOException;

/**
 * Lightweight schema checks for admin UX (missing migrations).
 */
final class SchemaInspector
{
    public static function tableExists(PDO $pdo, string $table): bool
    {
        if ($table === '' || ! preg_match('/^[a-z0-9_]+$/i', $table)) {
            return false;
        }
        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);

            return (bool) $stmt->fetch(PDO::FETCH_NUM);
        } catch (PDOException) {
            return false;
        }
    }
}
