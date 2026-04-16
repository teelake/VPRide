<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;
use PDOException;
use RuntimeException;

final class FleetVehicleRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listForAdmin(?string $q, int $limit, int $offset): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        [$sql, $params] = $this->buildListQuery($q, false);
        $sql .= ' ORDER BY fv.id DESC LIMIT ? OFFSET ?';
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
     * Active vehicles for driver assignment dropdowns.
     *
     * @return list<array{id: int, ownership: string, plate_number: string, make: ?string, model: ?string, company_fleet_label: ?string}>
     */
    public function listActiveForSelect(): array
    {
        $sql = 'SELECT id, ownership, plate_number, make, model, company_fleet_label '
            . 'FROM fleet_vehicles WHERE status = ? ORDER BY ownership ASC, plate_number ASC';
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['active']);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            if (self::isMissingTable($e)) {
                return [];
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
        $stmt = $this->pdo->prepare('SELECT * FROM fleet_vehicles WHERE id = ? LIMIT 1');
        try {
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

    public function countDriversUsingVehicle(int $vehicleId): int
    {
        if ($vehicleId < 1) {
            return 0;
        }
        try {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM fleet_drivers WHERE fleet_vehicle_id = ?',
            );
            $stmt->execute([$vehicleId]);

            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            if (self::isMissingTable($e)) {
                return 0;
            }
            throw $e;
        }
    }

    /**
     * @param array{
     *   ownership: string,
     *   company_fleet_label?: string,
     *   plate_number: string,
     *   make?: string,
     *   model?: string,
     *   color?: string,
     *   year?: int|null,
     *   seat_count?: int|null,
     *   vin?: string,
     *   status: string,
     *   notes?: string
     * } $data
     */
    public function insert(array $data): int
    {
        $row = self::normalizeRow($data);
        $stmt = $this->pdo->prepare(
            'INSERT INTO fleet_vehicles (ownership, company_fleet_label, plate_number, make, model, color, year, seat_count, vin, status, notes) '
            . 'VALUES (?,?,?,?,?,?,?,?,?,?,?)',
        );
        $stmt->execute([
            $row['ownership'],
            $row['company_fleet_label'],
            $row['plate_number'],
            $row['make'],
            $row['model'],
            $row['color'],
            $row['year'],
            $row['seat_count'],
            $row['vin'],
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
            throw new RuntimeException('Invalid vehicle id');
        }
        $row = self::normalizeRow($data);
        $stmt = $this->pdo->prepare(
            'UPDATE fleet_vehicles SET ownership = ?, company_fleet_label = ?, plate_number = ?, make = ?, model = ?, color = ?, '
            . 'year = ?, seat_count = ?, vin = ?, status = ?, notes = ? WHERE id = ?',
        );
        $stmt->execute([
            $row['ownership'],
            $row['company_fleet_label'],
            $row['plate_number'],
            $row['make'],
            $row['model'],
            $row['color'],
            $row['year'],
            $row['seat_count'],
            $row['vin'],
            $row['status'],
            $row['notes'],
            $id,
        ]);
        if ($stmt->rowCount() < 1) {
            $check = $this->findById($id);
            if ($check === null) {
                throw new RuntimeException('Vehicle not found');
            }
        }
    }

    public function delete(int $id): void
    {
        if ($id < 1) {
            throw new RuntimeException('Invalid vehicle id');
        }
        $n = $this->countDriversUsingVehicle($id);
        if ($n > 0) {
            throw new RuntimeException(
                'Cannot delete this vehicle while ' . $n . ' driver(s) are assigned. Unassign them first.',
            );
        }
        $stmt = $this->pdo->prepare('DELETE FROM fleet_vehicles WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('Vehicle not found');
        }
    }

    /**
     * @return array{0: string, 1: list<int|string>}
     */
    private function buildListQuery(?string $q, bool $countOnly): array
    {
        $q = $q !== null ? trim($q) : '';
        $baseFrom = 'FROM fleet_vehicles fv';
        if ($q === '') {
            if ($countOnly) {
                return ['SELECT COUNT(*) ' . $baseFrom, []];
            }

            return [
                'SELECT fv.*, '
                . '(SELECT COUNT(*) FROM fleet_drivers d WHERE d.fleet_vehicle_id = fv.id) AS driver_count '
                . $baseFrom,
                [],
            ];
        }
        $like = '%' . $q . '%';
        $where = ' WHERE fv.plate_number LIKE ? OR fv.make LIKE ? OR fv.model LIKE ? OR fv.company_fleet_label LIKE ? OR fv.vin LIKE ?';
        $params = [$like, $like, $like, $like, $like];
        if ($countOnly) {
            return ['SELECT COUNT(*) ' . $baseFrom . $where, $params];
        }

        return [
            'SELECT fv.*, '
            . '(SELECT COUNT(*) FROM fleet_drivers d WHERE d.fleet_vehicle_id = fv.id) AS driver_count '
            . $baseFrom . $where,
            $params,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ownership: string, company_fleet_label: ?string, plate_number: string, make: ?string, model: ?string, color: ?string, year: ?int, seat_count: ?int, vin: ?string, status: string, notes: ?string}
     */
    private static function normalizeRow(array $data): array
    {
        $own = strtolower(trim((string) ($data['ownership'] ?? '')));
        if (! in_array($own, ['personal', 'company'], true)) {
            throw new RuntimeException('Invalid ownership type');
        }
        $label = trim((string) ($data['company_fleet_label'] ?? ''));
        if ($own === 'company' && $label === '') {
            throw new RuntimeException('Company / brand label is required for company vehicles');
        }
        if ($own === 'personal') {
            $label = null;
        } else {
            $label = mb_substr($label, 0, 128);
        }
        $plate = trim((string) ($data['plate_number'] ?? ''));
        if ($plate === '') {
            throw new RuntimeException('Plate / registration is required');
        }
        $plate = mb_substr($plate, 0, 32);
        $make = self::nullableTrim($data['make'] ?? null, 64);
        $model = self::nullableTrim($data['model'] ?? null, 64);
        $color = self::nullableTrim($data['color'] ?? null, 48);
        $vin = self::nullableTrim($data['vin'] ?? null, 32);
        $notes = self::nullableTrim($data['notes'] ?? null, 512);
        $year = self::optionalYear($data['year'] ?? null);
        $seats = self::optionalSeats($data['seat_count'] ?? null);
        $status = strtolower(trim((string) ($data['status'] ?? 'active')));
        if (! in_array($status, ['active', 'maintenance', 'retired'], true)) {
            throw new RuntimeException('Invalid vehicle status');
        }

        return [
            'ownership' => $own,
            'company_fleet_label' => $label,
            'plate_number' => $plate,
            'make' => $make,
            'model' => $model,
            'color' => $color,
            'year' => $year,
            'seat_count' => $seats,
            'vin' => $vin,
            'status' => $status,
            'notes' => $notes,
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

    private static function optionalYear(mixed $y): ?int
    {
        if ($y === null || $y === '') {
            return null;
        }
        $n = is_numeric($y) ? (int) $y : null;
        if ($n === null || $n < 1900 || $n > 2100) {
            throw new RuntimeException('Model year must be between 1900 and 2100 when set');
        }

        return $n;
    }

    private static function optionalSeats(mixed $s): ?int
    {
        if ($s === null || $s === '') {
            return null;
        }
        $n = is_numeric($s) ? (int) $s : null;
        if ($n === null || $n < 1 || $n > 60) {
            throw new RuntimeException('Seat count must be 1–60 when set');
        }

        return $n;
    }

    private static function isMissingTable(PDOException $e): bool
    {
        $m = $e->getMessage();

        return str_contains($m, '42S02')
            || str_contains($m, "doesn't exist")
            || str_contains($m, 'Base table or view not found');
    }
}
