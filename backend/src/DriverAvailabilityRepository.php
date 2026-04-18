<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;
use PDOException;

final class DriverAvailabilityRepository
{
    public function __construct(private PDO $pdo) {}

    public static function tableExists(PDO $pdo): bool
    {
        return SchemaInspector::tableExists($pdo, 'driver_availability');
    }

    /**
     * @return 'offline'|'online'|'busy'
     */
    public function getStatus(int $riderUserId): string
    {
        if (! self::tableExists($this->pdo)) {
            return 'offline';
        }
        $stmt = $this->pdo->prepare(
            'SELECT status FROM driver_availability WHERE rider_user_id = ? LIMIT 1',
        );
        $stmt->execute([$riderUserId]);
        $v = $stmt->fetchColumn();
        if ($v === false || ! is_string($v)) {
            return 'offline';
        }

        return in_array($v, ['offline', 'online', 'busy'], true) ? $v : 'offline';
    }

    public function setStatus(int $riderUserId, string $status): void
    {
        if (! self::tableExists($this->pdo)) {
            return;
        }
        if (! in_array($status, ['offline', 'online', 'busy'], true)) {
            throw new \RuntimeException('invalid_availability');
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO driver_availability (rider_user_id, status) VALUES (?, ?) '
            . 'ON DUPLICATE KEY UPDATE status = VALUES(status)',
        );
        try {
            $stmt->execute([$riderUserId, $status]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), '42S02')) {
                return;
            }
            throw $e;
        }
    }

    /**
     * Stores last reported coordinates for rider ETA (columns from migration_trip_tracking.sql).
     */
    public function upsertLastKnownLocation(int $riderUserId, float $latitude, float $longitude): void
    {
        if (! self::tableExists($this->pdo)
            || ! SchemaInspector::columnExists($this->pdo, 'driver_availability', 'last_latitude')) {
            return;
        }
        if ($latitude < -90.0 || $latitude > 90.0 || $longitude < -180.0 || $longitude > 180.0) {
            return;
        }
        $upd = $this->pdo->prepare(
            'UPDATE driver_availability SET last_latitude = ?, last_longitude = ?, '
            . 'location_updated_at = CURRENT_TIMESTAMP WHERE rider_user_id = ?',
        );
        try {
            $upd->execute([$latitude, $longitude, $riderUserId]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), '42S02')) {
                return;
            }
            throw $e;
        }
        if ($upd->rowCount() > 0) {
            return;
        }
        $ins = $this->pdo->prepare(
            'INSERT INTO driver_availability (rider_user_id, status, last_latitude, last_longitude, location_updated_at) '
            . 'VALUES (?, \'online\', ?, ?, CURRENT_TIMESTAMP)',
        );
        try {
            $ins->execute([$riderUserId, $latitude, $longitude]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), '42S02')) {
                return;
            }
            throw $e;
        }
    }
}
