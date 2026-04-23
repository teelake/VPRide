<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;

/**
 * Default auto-assignment: first eligible online driver not busy and who has not refused this ride.
 */
final class DispatchService
{
    public function __construct(private PDO $pdo) {}

    public function tryAutoAssign(int $rideId): void
    {
        if (! SchemaInspector::columnExists($this->pdo, 'rides', 'driver_rider_user_id')) {
            return;
        }
        if (! DriverAvailabilityRepository::tableExists($this->pdo)) {
            return;
        }
        if (! SchemaInspector::tableExists($this->pdo, 'ride_driver_refusals')) {
            return;
        }
        $maxAttempts = (new AppSettingsRepository($this->pdo))->getDispatchSettings()['maxAutoDriverAttempts'];
        $rides = new RideRepository($this->pdo);
        $row = $rides->findById($rideId);
        if ($row === null || ($row['status'] ?? '') !== 'requested') {
            return;
        }
        if (($row['driver_rider_user_id'] ?? null) !== null && (int) $row['driver_rider_user_id'] > 0) {
            return;
        }
        if ($rides->countRefusalsForRide($rideId) >= $maxAttempts) {
            return;
        }

        $driverId = $this->pickNextDriverRiderUserId($rideId);
        if ($driverId === null) {
            return;
        }

        $rides->assignDriverToRequestedRide($rideId, $driverId, 'auto');
    }

    private function pickNextDriverRiderUserId(int $rideId): ?int
    {
        if (! SchemaInspector::columnExists($this->pdo, 'fleet_drivers', 'rider_user_id')) {
            return null;
        }
        $sql = <<<'SQL'
SELECT fd.rider_user_id
FROM fleet_drivers fd
INNER JOIN driver_availability da ON da.rider_user_id = fd.rider_user_id
WHERE fd.status = 'active'
  AND fd.rider_user_id IS NOT NULL
  AND da.status = 'online'
  AND fd.rider_user_id NOT IN (
    SELECT r.driver_rider_user_id FROM rides r
    WHERE r.driver_rider_user_id IS NOT NULL
      AND r.status IN ('accepted', 'in_progress')
  )
  AND fd.rider_user_id NOT IN (
    SELECT rdr.driver_rider_user_id FROM ride_driver_refusals rdr WHERE rdr.ride_id = ?
  )
ORDER BY fd.id ASC
LIMIT 1
SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$rideId]);
        $v = $stmt->fetchColumn();
        if ($v === false) {
            return null;
        }

        return (int) $v;
    }
}
