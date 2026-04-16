<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;
use PDOException;
use RuntimeException;

final class RideRepository
{
    public function __construct(private PDO $pdo) {}

    public function createRequested(
        int $riderUserId,
        float $pickupLat,
        float $pickupLng,
        ?string $pickupAddress,
    ): int {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO rides (rider_user_id, status, pickup_lat, pickup_lng, pickup_address) '
                . 'VALUES (?, \'requested\', ?, ?, ?)',
            );
            $stmt->execute([
                $riderUserId,
                round($pickupLat, 7),
                round($pickupLng, 7),
                $pickupAddress !== null && $pickupAddress !== '' ? $pickupAddress : null,
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            if (self::isMissingTable($e)) {
                throw new RuntimeException(
                    'The rides table is missing. Import backend/sql/migration_rides.sql (or full schema) on this database.',
                    0,
                    $e,
                );
            }
            throw $e;
        }
    }

    public function countAll(): int
    {
        try {
            return (int) $this->pdo->query('SELECT COUNT(*) FROM rides')->fetchColumn();
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
        return $this->listFiltered(null, null, null, $limit, 0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listFiltered(?string $status, ?string $fromDate, ?string $toDate, int $limit, int $offset): array
    {
        $limit = max(1, min(10000, $limit));
        $offset = max(0, $offset);
        [$sql, $params] = $this->buildFilterQuery($status, $fromDate, $toDate, false);
        $sql .= ' ORDER BY r.id DESC LIMIT ? OFFSET ?';
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

    public function countFiltered(?string $status, ?string $fromDate, ?string $toDate): int
    {
        [$sql, $params] = $this->buildFilterQuery($status, $fromDate, $toDate, true);
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
    private function buildFilterQuery(?string $status, ?string $fromDate, ?string $toDate, bool $countOnly): array
    {
        $allowed = ['requested', 'accepted', 'in_progress', 'completed', 'cancelled'];
        $where = ['1=1'];
        $params = [];
        if ($status !== null && $status !== '' && in_array($status, $allowed, true)) {
            $where[] = 'r.status = ?';
            $params[] = $status;
        }
        if ($fromDate !== null && $fromDate !== '') {
            $where[] = 'r.created_at >= ?';
            $params[] = $fromDate . ' 00:00:00';
        }
        if ($toDate !== null && $toDate !== '') {
            $where[] = 'r.created_at <= ?';
            $params[] = $toDate . ' 23:59:59';
        }
        $w = implode(' AND ', $where);
        if ($countOnly) {
            return ["SELECT COUNT(*) FROM rides r INNER JOIN rider_users u ON u.id = r.rider_user_id WHERE {$w}", $params];
        }

        return [
            'SELECT r.id, r.status, r.pickup_lat, r.pickup_lng, r.pickup_address, '
            . 'r.dropoff_lat, r.dropoff_lng, r.dropoff_address, r.created_at, u.email AS rider_email '
            . "FROM rides r INNER JOIN rider_users u ON u.id = r.rider_user_id WHERE {$w}",
            $params,
        ];
    }
}
