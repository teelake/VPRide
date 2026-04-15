<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;

final class RiderUserRepository
{
    public function __construct(private PDO $pdo) {}

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM rider_users')->fetchColumn();
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
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countFiltered(?string $q): int
    {
        [$sql, $params] = $this->buildSearchQuery($q, true);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
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
