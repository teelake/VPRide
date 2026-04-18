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
}
