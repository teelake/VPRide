<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;
use PDOException;

final class RiderLoyaltyRepository
{
    public function __construct(private PDO $pdo) {}

    public static function tableExists(PDO $pdo): bool
    {
        return SchemaInspector::tableExists($pdo, 'rider_loyalty_state');
    }

    public function getPaidTripsCount(int $riderUserId): int
    {
        if (! self::tableExists($this->pdo)) {
            return 0;
        }
        try {
            $stmt = $this->pdo->prepare(
                'SELECT paid_trips_count FROM rider_loyalty_state WHERE rider_user_id = ? LIMIT 1',
            );
            $stmt->execute([$riderUserId]);
            $n = $stmt->fetchColumn();

            return $n !== false ? (int) $n : 0;
        } catch (PDOException) {
            return 0;
        }
    }

    /**
     * Increment after a ride is marked paid (idempotent per ride handled by caller).
     */
    public function incrementPaidTrips(int $riderUserId): int
    {
        $this->pdo->prepare(
            'INSERT INTO rider_loyalty_state (rider_user_id, paid_trips_count) VALUES (?, 1) '
            . 'ON DUPLICATE KEY UPDATE paid_trips_count = paid_trips_count + 1',
        )->execute([$riderUserId]);

        return $this->getPaidTripsCount($riderUserId);
    }
}
