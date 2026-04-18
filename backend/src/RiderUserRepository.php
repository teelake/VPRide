<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;
use PDOException;

if (! class_exists(SchemaInspector::class, false)) {
    require_once __DIR__ . '/SchemaInspector.php';
}

final class RiderUserRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Counts app accounts that can use the passenger / ride-booking experience.
     * Excludes rows with driver_account_only = 1 (fleet driver-only logins) when that column exists.
     */
    public function countAll(): int
    {
        $sql = 'SELECT COUNT(*) FROM rider_users' . $this->sqlWhereExcludeDriverOnly();
        try {
            return (int) $this->pdo->query($sql)->fetchColumn();
        } catch (PDOException $e) {
            if (self::isMissingTable($e)) {
                return 0;
            }
            throw $e;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecent(int $limit = 100): array
    {
        return $this->listFiltered(null, $limit, 0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listFiltered(?string $q, int $limit, int $offset): array
    {
        $limit = max(1, min(10000, $limit));
        $offset = max(0, $offset);
        [$sql, $params] = $this->buildSearchQuery($q, false);
        $sql .= ' ORDER BY id DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;
        $stmt = $this->pdo->prepare($sql);
        $i = 1;
        foreach ($params as $p) {
            $type = is_int($p) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($i++, $p, $type);
        }
        try {
            $stmt->execute();
        } catch (PDOException $e) {
            if (self::isMissingTable($e)) {
                return [];
            }
            throw $e;
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countFiltered(?string $q): int
    {
        [$sql, $params] = $this->buildSearchQuery($q, true);
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            if (self::isMissingTable($e)) {
                return 0;
            }
            throw $e;
        }
    }

    private static function isMissingTable(PDOException $e): bool
    {
        $m = $e->getMessage();

        return str_contains($m, '42S02')
            || str_contains($m, "doesn't exist")
            || str_contains($m, 'Base table or view not found');
    }

    /** `WHERE …` fragment, or empty string if column absent / no filter. */
    private function sqlWhereExcludeDriverOnly(): string
    {
        if (! SchemaInspector::columnExists($this->pdo, 'rider_users', 'driver_account_only')) {
            return '';
        }

        return ' WHERE COALESCE(driver_account_only, 0) = 0';
    }

    /**
     * @return array{0: string, 1: list<int|string>}
     */
    private function buildSearchQuery(?string $q, bool $countOnly): array
    {
        $q = $q !== null ? trim($q) : '';
        $exclude = $this->sqlWhereExcludeDriverOnly();
        if ($q === '') {
            if ($countOnly) {
                return ['SELECT COUNT(*) FROM rider_users' . $exclude, []];
            }

            return [
                'SELECT id, email, display_name, google_sub, created_at, updated_at FROM rider_users'
                    . ($exclude !== '' ? $exclude : ''),
                [],
            ];
        }
        $like = '%' . $q . '%';
        $driverFilter = $exclude !== '' ? ' AND COALESCE(driver_account_only, 0) = 0' : '';
        if ($countOnly) {
            return [
                'SELECT COUNT(*) FROM rider_users WHERE (email LIKE ? OR display_name LIKE ? OR google_sub LIKE ? OR CAST(id AS CHAR) LIKE ?)'
                    . $driverFilter,
                [$like, $like, $like, $like],
            ];
        }

        return [
            'SELECT id, email, display_name, google_sub, created_at, updated_at FROM rider_users '
                . 'WHERE (email LIKE ? OR display_name LIKE ? OR google_sub LIKE ? OR CAST(id AS CHAR) LIKE ?)'
                . $driverFilter,
            [$like, $like, $like, $like],
        ];
    }
}
