<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;
use PDOException;

final class RiderUserRepository
{
    public function __construct(private PDO $pdo) {}

    public function countAll(): int
    {
        try {
            return (int) $this->pdo->query('SELECT COUNT(*) FROM rider_users')->fetchColumn();
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

    /**
     * @return array{0: string, 1: list<int|string>}
     */
    private function buildSearchQuery(?string $q, bool $countOnly): array
    {
        $q = $q !== null ? trim($q) : '';
        if ($q === '') {
            if ($countOnly) {
                return ['SELECT COUNT(*) FROM rider_users', []];
            }

            return ['SELECT id, email, display_name, google_sub, created_at, updated_at FROM rider_users', []];
        }
        $like = '%' . $q . '%';
        if ($countOnly) {
            return [
                'SELECT COUNT(*) FROM rider_users WHERE email LIKE ? OR display_name LIKE ? OR google_sub LIKE ? OR CAST(id AS CHAR) LIKE ?',
                [$like, $like, $like, $like],
            ];
        }

        return [
            'SELECT id, email, display_name, google_sub, created_at, updated_at FROM rider_users '
            . 'WHERE email LIKE ? OR display_name LIKE ? OR google_sub LIKE ? OR CAST(id AS CHAR) LIKE ?',
            [$like, $like, $like, $like],
        ];
    }
}
