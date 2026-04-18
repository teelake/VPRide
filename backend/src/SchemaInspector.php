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

    public static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        if ($table === '' || $column === ''
            || ! preg_match('/^[a-z0-9_]+$/i', $table)
            || ! preg_match('/^[a-z0-9_]+$/i', $column)
        ) {
            return false;
        }
        try {
            $safeTable = str_replace('`', '', $table);
            $stmt = $pdo->query('SHOW COLUMNS FROM `' . $safeTable . '` LIKE ' . $pdo->quote($column));

            return $stmt && (bool) $stmt->fetch(PDO::FETCH_NUM);
        } catch (PDOException) {
            return false;
        }
    }
}
