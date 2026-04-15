<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;

final class RideRepository
{
    public function __construct(private PDO $pdo) {}

    public function createRequested(
        int $riderUserId,
        float $pickupLat,
        float $pickupLng,
        ?string $pickupAddress,
    ): int {
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
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM rides')->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecent(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.status, r.pickup_lat, r.pickup_lng, r.pickup_address, '
            . 'r.dropoff_address, r.created_at, u.email AS rider_email '
            . 'FROM rides r INNER JOIN rider_users u ON u.id = r.rider_user_id '
            . 'ORDER BY r.id DESC LIMIT ?',
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
