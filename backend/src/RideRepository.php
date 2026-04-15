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
}
