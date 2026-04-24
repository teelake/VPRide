<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;
use PDOException;
use RuntimeException;

if (! class_exists(SchemaInspector::class, false)) {
    require_once __DIR__ . '/SchemaInspector.php';
}

final class ConsoleDriverRepository
{
    public function __construct(
        private PDO $pdo,
        private FleetVehicleRepository $vehicles,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listForAdmin(?string $q, int $limit, int $offset): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        [$sql, $params] = $this->buildListQuery($q, false);
        $sql .= ' ORDER BY d.id DESC LIMIT ? OFFSET ?';
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

    public function countForAdmin(?string $q): int
    {
        [$sql, $params] = $this->buildListQuery($q, true);
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

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $sql = 'SELECT d.*, fv.plate_number AS vehicle_plate, fv.ownership AS vehicle_ownership, '
            . 'fv.company_fleet_label AS vehicle_company_label, fv.make AS vehicle_make, fv.model AS vehicle_model '
            . 'FROM fleet_drivers d '
            . 'LEFT JOIN fleet_vehicles fv ON fv.id = d.fleet_vehicle_id '
            . 'WHERE d.id = ? LIMIT 1';
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
        } catch (PDOException $e) {
            if (self::isMissingTable($e)) {
                return null;
            }
            throw $e;
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @param array{
     *   full_name: string,
     *   driver_kind: string,
     *   phone?: string,
     *   email?: string,
     *   fleet_vehicle_id?: int|null,
     *   license_number?: string,
     *   status: string,
     *   notes?: string,
     *   vehicle_assignment_mode?: 'fixed'|'flexible' (if column exists)
     * } $data
     */
    public function insert(array $data): int
    {
        $row = $this->normalizeAndValidateVehicle($data);
        $this->assertFleetVehicleExclusive($row['fleet_vehicle_id'], null);
        $hasRider = SchemaInspector::columnExists($this->pdo, 'fleet_drivers', 'rider_user_id');
        $hasEarn = SchemaInspector::columnExists($this->pdo, 'fleet_drivers', 'earnings_percent_override');
        if ($hasRider && $hasEarn) {
            $this->assertRiderUserLinkUnique($row['rider_user_id'], null);
            $stmt = $this->pdo->prepare(
                'INSERT INTO fleet_drivers (full_name, phone, email, driver_kind, fleet_vehicle_id, license_number, status, notes, rider_user_id, earnings_percent_override) '
                . 'VALUES (?,?,?,?,?,?,?,?,?,?)',
            );
            $stmt->execute([
                $row['full_name'],
                $row['phone'],
                $row['email'],
                $row['driver_kind'],
                $row['fleet_vehicle_id'],
                $row['license_number'],
                $row['status'],
                $row['notes'],
                $row['rider_user_id'],
                $row['earnings_percent_override'],
            ]);
        } elseif ($hasRider) {
            $this->assertRiderUserLinkUnique($row['rider_user_id'], null);
            $stmt = $this->pdo->prepare(
                'INSERT INTO fleet_drivers (full_name, phone, email, driver_kind, fleet_vehicle_id, license_number, status, notes, rider_user_id) '
                . 'VALUES (?,?,?,?,?,?,?,?,?)',
            );
            $stmt->execute([
                $row['full_name'],
                $row['phone'],
                $row['email'],
                $row['driver_kind'],
                $row['fleet_vehicle_id'],
                $row['license_number'],
                $row['status'],
                $row['notes'],
                $row['rider_user_id'],
            ]);
        } elseif ($hasEarn) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO fleet_drivers (full_name, phone, email, driver_kind, fleet_vehicle_id, license_number, status, notes, earnings_percent_override) '
                . 'VALUES (?,?,?,?,?,?,?,?,?)',
            );
            $stmt->execute([
                $row['full_name'],
                $row['phone'],
                $row['email'],
                $row['driver_kind'],
                $row['fleet_vehicle_id'],
                $row['license_number'],
                $row['status'],
                $row['notes'],
                $row['earnings_percent_override'],
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO fleet_drivers (full_name, phone, email, driver_kind, fleet_vehicle_id, license_number, status, notes) '
                . 'VALUES (?,?,?,?,?,?,?,?)',
            );
            $stmt->execute([
                $row['full_name'],
                $row['phone'],
                $row['email'],
                $row['driver_kind'],
                $row['fleet_vehicle_id'],
                $row['license_number'],
                $row['status'],
                $row['notes'],
            ]);
        }

        $newId = (int) $this->pdo->lastInsertId();
        $this->applyVehicleAssignmentMode($newId, (string) $row['vehicle_assignment_mode']);

        return $newId;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        if ($id < 1) {
            throw new RuntimeException('Invalid driver id');
        }
        $row = $this->normalizeAndValidateVehicle($data);
        $this->assertFleetVehicleExclusive($row['fleet_vehicle_id'], $id);
        $hasRider = SchemaInspector::columnExists($this->pdo, 'fleet_drivers', 'rider_user_id');
        $hasEarn = SchemaInspector::columnExists($this->pdo, 'fleet_drivers', 'earnings_percent_override');
        if ($hasRider && $hasEarn) {
            $this->assertRiderUserLinkUnique($row['rider_user_id'], $id);
            $stmt = $this->pdo->prepare(
                'UPDATE fleet_drivers SET full_name = ?, phone = ?, email = ?, driver_kind = ?, fleet_vehicle_id = ?, '
                . 'license_number = ?, status = ?, notes = ?, rider_user_id = ?, earnings_percent_override = ? WHERE id = ?',
            );
            $stmt->execute([
                $row['full_name'],
                $row['phone'],
                $row['email'],
                $row['driver_kind'],
                $row['fleet_vehicle_id'],
                $row['license_number'],
                $row['status'],
                $row['notes'],
                $row['rider_user_id'],
                $row['earnings_percent_override'],
                $id,
            ]);
        } elseif ($hasRider) {
            $this->assertRiderUserLinkUnique($row['rider_user_id'], $id);
            $stmt = $this->pdo->prepare(
                'UPDATE fleet_drivers SET full_name = ?, phone = ?, email = ?, driver_kind = ?, fleet_vehicle_id = ?, '
                . 'license_number = ?, status = ?, notes = ?, rider_user_id = ? WHERE id = ?',
            );
            $stmt->execute([
                $row['full_name'],
                $row['phone'],
                $row['email'],
                $row['driver_kind'],
                $row['fleet_vehicle_id'],
                $row['license_number'],
                $row['status'],
                $row['notes'],
                $row['rider_user_id'],
                $id,
            ]);
        } elseif ($hasEarn) {
            $stmt = $this->pdo->prepare(
                'UPDATE fleet_drivers SET full_name = ?, phone = ?, email = ?, driver_kind = ?, fleet_vehicle_id = ?, '
                . 'license_number = ?, status = ?, notes = ?, earnings_percent_override = ? WHERE id = ?',
            );
            $stmt->execute([
                $row['full_name'],
                $row['phone'],
                $row['email'],
                $row['driver_kind'],
                $row['fleet_vehicle_id'],
                $row['license_number'],
                $row['status'],
                $row['notes'],
                $row['earnings_percent_override'],
                $id,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE fleet_drivers SET full_name = ?, phone = ?, email = ?, driver_kind = ?, fleet_vehicle_id = ?, '
                . 'license_number = ?, status = ?, notes = ? WHERE id = ?',
            );
            $stmt->execute([
                $row['full_name'],
                $row['phone'],
                $row['email'],
                $row['driver_kind'],
                $row['fleet_vehicle_id'],
                $row['license_number'],
                $row['status'],
                $row['notes'],
                $id,
            ]);
        }
        $this->applyVehicleAssignmentMode($id, (string) $row['vehicle_assignment_mode']);
        if ($stmt->rowCount() < 1) {
            if ($this->findById($id) === null) {
                throw new RuntimeException('Driver not found');
            }
        }
    }

    public function delete(int $id): void
    {
        if ($id < 1) {
            throw new RuntimeException('Invalid driver id');
        }
        $stmt = $this->pdo->prepare('DELETE FROM fleet_drivers WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('Driver not found');
        }
    }

    /**
     * One vehicle may be held by only one driver at a time among active / pending rows.
     * Suspended drivers do not block reassignment (change status or clear vehicle first if you need stricter data).
     */
    private function assertFleetVehicleExclusive(?int $fleetVehicleId, ?int $excludeDriverId): void
    {
        if ($fleetVehicleId === null || $fleetVehicleId < 1) {
            return;
        }
        $sql = 'SELECT id, full_name FROM fleet_drivers WHERE fleet_vehicle_id = ? '
            . "AND status IN ('active', 'pending')";
        $params = [$fleetVehicleId];
        if ($excludeDriverId !== null && $excludeDriverId > 0) {
            $sql .= ' AND id != ?';
            $params[] = $excludeDriverId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $other = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($other !== false) {
            $name = trim((string) ($other['full_name'] ?? 'Driver'));
            $oid = (int) $other['id'];
            throw new RuntimeException(
                'This vehicle is already assigned to ' . $name . ' (#' . $oid . ', active or pending). '
                . 'Clear that driver\'s vehicle, set them to suspended, or choose another vehicle.',
            );
        }
    }

    private function assertRiderUserLinkUnique(?int $riderUserId, ?int $excludeFleetDriverId): void
    {
        if ($riderUserId === null || $riderUserId < 1) {
            return;
        }
        if (! SchemaInspector::columnExists($this->pdo, 'fleet_drivers', 'rider_user_id')) {
            return;
        }
        if ($excludeFleetDriverId === null) {
            $stmt = $this->pdo->prepare('SELECT id FROM fleet_drivers WHERE rider_user_id = ? LIMIT 1');
            $stmt->execute([$riderUserId]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT id FROM fleet_drivers WHERE rider_user_id = ? AND id != ? LIMIT 1',
            );
            $stmt->execute([$riderUserId, $excludeFleetDriverId]);
        }
        if ($stmt->fetchColumn() !== false) {
            throw new RuntimeException('That app user is already linked to another driver record');
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array{full_name: string, phone: ?string, email: ?string, driver_kind: string, fleet_vehicle_id: ?int, license_number: ?string, status: string, notes: ?string, rider_user_id: ?int, earnings_percent_override: ?float}
     */
    private function normalizeAndValidateVehicle(array $data): array
    {
        $name = trim((string) ($data['full_name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Driver name is required');
        }
        $name = mb_substr($name, 0, 120);
        $kind = strtolower(trim((string) ($data['driver_kind'] ?? '')));
        if (! in_array($kind, ['owner_operator', 'company_driver'], true)) {
            throw new RuntimeException('Invalid driver type');
        }
        $phone = self::nullableTrim($data['phone'] ?? null, 32);
        $email = self::nullableTrim($data['email'] ?? null, 255);
        if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid email address');
        }
        $license = self::nullableTrim($data['license_number'] ?? null, 64);
        $notes = self::nullableTrim($data['notes'] ?? null, 512);
        $status = strtolower(trim((string) ($data['status'] ?? 'pending')));
        if (! in_array($status, ['pending', 'active', 'suspended'], true)) {
            throw new RuntimeException('Invalid driver status');
        }
        $vid = isset($data['fleet_vehicle_id']) ? (int) $data['fleet_vehicle_id'] : 0;
        $vehicleId = $vid > 0 ? $vid : null;
        if ($vehicleId !== null) {
            $v = $this->vehicles->findById($vehicleId);
            if ($v === null) {
                throw new RuntimeException('Selected vehicle does not exist');
            }
            $own = (string) $v['ownership'];
            if ($kind === 'company_driver' && $own !== 'company') {
                throw new RuntimeException('Company drivers must be assigned a company / brand vehicle');
            }
            if ($kind === 'owner_operator' && $own !== 'personal') {
                throw new RuntimeException('Owner-operators must be assigned a personal vehicle record');
            }
            if (($v['status'] ?? '') !== 'active') {
                throw new RuntimeException('Assign an active vehicle, or clear the vehicle until it is ready');
            }
        }

        $riderUserId = null;
        if (SchemaInspector::columnExists($this->pdo, 'fleet_drivers', 'rider_user_id')
            && array_key_exists('rider_user_id', $data)) {
            $rawRu = $data['rider_user_id'];
            if ($rawRu !== null && $rawRu !== '') {
                $r = (int) $rawRu;
                if ($r > 0) {
                    $chk = $this->pdo->prepare('SELECT id FROM rider_users WHERE id = ? LIMIT 1');
                    $chk->execute([$r]);
                    if ($chk->fetchColumn() === false) {
                        throw new RuntimeException('Linked rider user ID does not exist in the app');
                    }
                    $riderUserId = $r;
                }
            }
        }

        $earnOverride = null;
        if (SchemaInspector::columnExists($this->pdo, 'fleet_drivers', 'earnings_percent_override')
            && array_key_exists('earnings_percent_override', $data)) {
            $rawE = $data['earnings_percent_override'];
            if ($rawE !== null && $rawE !== '') {
                $ev = (float) str_replace(',', '.', trim((string) $rawE));
                if (! is_finite($ev)) {
                    throw new RuntimeException('Driver earnings % override is not a valid number');
                }
                if ($ev < 0.0 || $ev > 100.0) {
                    throw new RuntimeException('Driver earnings % override must be between 0 and 100');
                }
                $earnOverride = round($ev, 2, PHP_ROUND_HALF_UP);
            }
        }

        $assignmentMode = 'fixed';
        if (SchemaInspector::columnExists($this->pdo, 'fleet_drivers', 'vehicle_assignment_mode')) {
            $am = strtolower(trim((string) ($data['vehicle_assignment_mode'] ?? 'fixed')));
            $assignmentMode = in_array($am, ['fixed', 'flexible'], true) ? $am : 'fixed';
        }

        return [
            'full_name' => $name,
            'phone' => $phone,
            'email' => $email,
            'driver_kind' => $kind,
            'fleet_vehicle_id' => $vehicleId,
            'license_number' => $license,
            'status' => $status,
            'notes' => $notes,
            'rider_user_id' => $riderUserId,
            'earnings_percent_override' => $earnOverride,
            'vehicle_assignment_mode' => $assignmentMode,
        ];
    }

    private function applyVehicleAssignmentMode(int $fleetDriverId, string $mode): void
    {
        if (! SchemaInspector::columnExists($this->pdo, 'fleet_drivers', 'vehicle_assignment_mode')) {
            return;
        }
        $m = in_array($mode, ['fixed', 'flexible'], true) ? $mode : 'fixed';
        $stmt = $this->pdo->prepare('UPDATE fleet_drivers SET vehicle_assignment_mode = ? WHERE id = ?');
        $stmt->execute([$m, $fleetDriverId]);
    }

    /**
     * @return array{0: string, 1: list<int|string>}
     */
    private function buildListQuery(?string $q, bool $countOnly): array
    {
        $q = $q !== null ? trim($q) : '';
        $baseFrom = 'FROM fleet_drivers d '
            . 'LEFT JOIN fleet_vehicles fv ON fv.id = d.fleet_vehicle_id';
        if ($q === '') {
            if ($countOnly) {
                return ['SELECT COUNT(*) ' . $baseFrom, []];
            }

            return [
                'SELECT d.*, fv.plate_number AS vehicle_plate, fv.ownership AS vehicle_ownership, '
                . 'fv.company_fleet_label AS vehicle_company_label '
                . $baseFrom,
                [],
            ];
        }
        $like = '%' . $q . '%';
        $where = ' WHERE d.full_name LIKE ? OR d.email LIKE ? OR d.phone LIKE ? OR fv.plate_number LIKE ?';
        $params = [$like, $like, $like, $like];
        if ($countOnly) {
            return ['SELECT COUNT(*) ' . $baseFrom . $where, $params];
        }

        return [
            'SELECT d.*, fv.plate_number AS vehicle_plate, fv.ownership AS vehicle_ownership, '
            . 'fv.company_fleet_label AS vehicle_company_label '
            . $baseFrom . $where,
            $params,
        ];
    }

    private static function nullableTrim(mixed $v, int $max): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : mb_substr($s, 0, $max);
    }

    private static function isMissingTable(PDOException $e): bool
    {
        $m = $e->getMessage();

        return str_contains($m, '42S02')
            || str_contains($m, "doesn't exist")
            || str_contains($m, 'Base table or view not found');
    }
}
