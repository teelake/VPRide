<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;
use PDOException;
use RuntimeException;

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
     *   notes?: string
     * } $data
     */
    public function insert(array $data): int
    {
        $row = $this->normalizeAndValidateVehicle($data);
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

        return (int) $this->pdo->lastInsertId();
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
     * @param array<string, mixed> $data
     * @return array{full_name: string, phone: ?string, email: ?string, driver_kind: string, fleet_vehicle_id: ?int, license_number: ?string, status: string, notes: ?string}
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

        return [
            'full_name' => $name,
            'phone' => $phone,
            'email' => $email,
            'driver_kind' => $kind,
            'fleet_vehicle_id' => $vehicleId,
            'license_number' => $license,
            'status' => $status,
            'notes' => $notes,
        ];
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
