<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;

if (! class_exists(SchemaInspector::class, false)) {
    require_once __DIR__ . '/SchemaInspector.php';
}

/**
 * Links rider app accounts (rider_users) to console fleet driver records.
 */
final class DriverFleetRepository
{
    public function __construct(private PDO $pdo) {}

    public static function fleetTableExists(PDO $pdo): bool
    {
        return SchemaInspector::tableExists($pdo, 'fleet_drivers');
    }

    /**
     * True if this email appears on a fleet driver record (admin-onboarded drivers must not self-register with the same address).
     */
    public static function fleetDriverEmailExists(PDO $pdo, string $email): bool
    {
        if (! self::fleetTableExists($pdo)
            || ! SchemaInspector::columnExists($pdo, 'fleet_drivers', 'email')) {
            return false;
        }
        $email = trim(strtolower($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $stmt = $pdo->prepare(
            'SELECT 1 FROM fleet_drivers WHERE LOWER(TRIM(IFNULL(email, \'\'))) = ? LIMIT 1',
        );
        $stmt->execute([$email]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Active fleet driver row linked to this app user, or null.
     *
     * @return array<string, mixed>|null
     */
    public function findActiveFleetRowForRiderUser(int $riderUserId): ?array
    {
        if (! self::fleetTableExists($this->pdo)
            || ! SchemaInspector::columnExists($this->pdo, 'fleet_drivers', 'rider_user_id')) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT id, full_name, status, rider_user_id FROM fleet_drivers '
            . 'WHERE rider_user_id = ? AND status = \'active\' LIMIT 1',
        );
        $stmt->execute([$riderUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAssignableForAdmin(): array
    {
        if (! self::fleetTableExists($this->pdo)
            || ! SchemaInspector::columnExists($this->pdo, 'fleet_drivers', 'rider_user_id')) {
            return [];
        }
        $stmt = $this->pdo->query(
            'SELECT id, full_name, email, phone, rider_user_id, status FROM fleet_drivers '
            . "WHERE status = 'active' AND rider_user_id IS NOT NULL "
            . 'ORDER BY full_name ASC',
        );
        if ($stmt === false) {
            return [];
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
