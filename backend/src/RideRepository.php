<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;
use PDOException;
use RuntimeException;

final class RideRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * @param array{
     *   estimated_fare?: float|null,
     *   promo_discount?: float,
     *   final_fare?: float|null,
     *   currency?: string,
     *   applied_promotion_id?: int|null,
     *   promo_code_used?: string|null,
     *   reward_grant_id?: int|null
     * } $pricing
     * @param array{
     *   dropoff_lat?: float|null,
     *   dropoff_lng?: float|null,
     *   dropoff_address?: string|null,
     *   scheduled_pickup_at?: string|null,
     *   trip_leg?: string,
     *   companion_ride_id?: int|null,
     *   distance_km?: float|null
     * } $meta
     */
    public function createRequested(
        int $riderUserId,
        float $pickupLat,
        float $pickupLng,
        ?string $pickupAddress,
        array $pricing = [],
        array $meta = [],
    ): int {
        try {
            $hasPricing = SchemaInspector::columnExists($this->pdo, 'rides', 'estimated_fare_amount');
            $cols = ['rider_user_id', 'status', 'pickup_lat', 'pickup_lng', 'pickup_address'];
            $vals = [
                $riderUserId,
                'requested',
                round($pickupLat, 7),
                round($pickupLng, 7),
                $pickupAddress !== null && $pickupAddress !== '' ? $pickupAddress : null,
            ];
            $types = ['int', 'str', 'float', 'float', 'str'];

            if ($hasPricing) {
                $est = isset($pricing['estimated_fare']) ? (float) $pricing['estimated_fare'] : null;
                $disc = isset($pricing['promo_discount']) ? (float) $pricing['promo_discount'] : 0.0;
                $fin = isset($pricing['final_fare']) ? (float) $pricing['final_fare'] : null;
                $cur = isset($pricing['currency']) ? strtoupper(trim((string) $pricing['currency'])) : 'NGN';
                if (strlen($cur) !== 3) {
                    $cur = 'NGN';
                }
                $pid = isset($pricing['applied_promotion_id']) && $pricing['applied_promotion_id'] !== null
                    ? (int) $pricing['applied_promotion_id']
                    : null;
                $pcode = isset($pricing['promo_code_used']) && $pricing['promo_code_used'] !== ''
                    ? mb_substr((string) $pricing['promo_code_used'], 0, 64)
                    : null;
                $gid = isset($pricing['reward_grant_id']) && $pricing['reward_grant_id'] !== null
                    ? (int) $pricing['reward_grant_id']
                    : null;
                array_push(
                    $cols,
                    'estimated_fare_amount',
                    'promo_discount_amount',
                    'final_fare_amount',
                    'fare_currency',
                    'applied_promotion_id',
                    'promo_code_used',
                    'reward_grant_id',
                );
                array_push($vals, $est, round($disc, 4), $fin, $cur, $pid, $pcode, $gid);
                $types = array_merge($types, ['floatnull', 'float', 'floatnull', 'str', 'intnull', 'strnull', 'intnull']);
            }

            $dlat = $meta['dropoff_lat'] ?? null;
            $dlng = $meta['dropoff_lng'] ?? null;
            if (SchemaInspector::columnExists($this->pdo, 'rides', 'dropoff_lat')
                && $dlat !== null && $dlng !== null) {
                $cols[] = 'dropoff_lat';
                $cols[] = 'dropoff_lng';
                $vals[] = round((float) $dlat, 7);
                $vals[] = round((float) $dlng, 7);
                $types[] = 'float';
                $types[] = 'float';
            }
            if (SchemaInspector::columnExists($this->pdo, 'rides', 'dropoff_address')) {
                $daddr = isset($meta['dropoff_address']) && is_string($meta['dropoff_address'])
                    ? mb_substr(trim($meta['dropoff_address']), 0, 500)
                    : null;
                if ($daddr === '') {
                    $daddr = null;
                }
                $cols[] = 'dropoff_address';
                $vals[] = $daddr;
                $types[] = 'strnull';
            }

            if (SchemaInspector::columnExists($this->pdo, 'rides', 'scheduled_pickup_at')) {
                $sched = $meta['scheduled_pickup_at'] ?? null;
                $cols[] = 'scheduled_pickup_at';
                $vals[] = $sched !== null && $sched !== '' ? (string) $sched : null;
                $types[] = 'strnull';
            }
            if (SchemaInspector::columnExists($this->pdo, 'rides', 'trip_leg')) {
                $leg = isset($meta['trip_leg']) ? (string) $meta['trip_leg'] : 'single';
                if (! in_array($leg, ['single', 'outbound', 'return'], true)) {
                    $leg = 'single';
                }
                $cols[] = 'trip_leg';
                $vals[] = $leg;
                $types[] = 'str';
            }
            if (SchemaInspector::columnExists($this->pdo, 'rides', 'companion_ride_id')) {
                $cid = $meta['companion_ride_id'] ?? null;
                $cols[] = 'companion_ride_id';
                $vals[] = $cid !== null ? (int) $cid : null;
                $types[] = 'intnull';
            }
            if (SchemaInspector::columnExists($this->pdo, 'rides', 'distance_km')) {
                $dkm = $meta['distance_km'] ?? null;
                $cols[] = 'distance_km';
                $vals[] = $dkm !== null ? round((float) $dkm, 5) : null;
                $types[] = 'floatnull';
            }

            $placeholders = implode(', ', array_fill(0, count($cols), '?'));
            $colSql = implode(', ', $cols);
            $stmt = $this->pdo->prepare("INSERT INTO rides ({$colSql}) VALUES ({$placeholders})");
            $i = 1;
            foreach ($vals as $idx => $v) {
                $t = $types[$idx] ?? 'strnull';
                if ($t === 'int') {
                    $stmt->bindValue($i++, (int) $v, PDO::PARAM_INT);
                } elseif ($t === 'intnull') {
                    if ($v === null) {
                        $stmt->bindValue($i++, null, PDO::PARAM_NULL);
                    } else {
                        $stmt->bindValue($i++, (int) $v, PDO::PARAM_INT);
                    }
                } elseif ($t === 'floatnull') {
                    if ($v === null) {
                        $stmt->bindValue($i++, null, PDO::PARAM_NULL);
                    } else {
                        $stmt->bindValue($i++, (float) $v);
                    }
                } elseif ($t === 'float') {
                    $stmt->bindValue($i++, (float) $v);
                } elseif ($t === 'str') {
                    $stmt->bindValue($i++, (string) $v);
                } else {
                    if ($v === null) {
                        $stmt->bindValue($i++, null, PDO::PARAM_NULL);
                    } else {
                        $stmt->bindValue($i++, $v);
                    }
                }
            }
            $stmt->execute();

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            if (self::isMissingTable($e)) {
                throw new RuntimeException(
                    'The rides table is missing. Import backend/sql/migration_rides.sql (or full schema) on this database.',
                    0,
                    $e,
                );
            }
            throw $e;
        }
    }

    /**
     * One advance (scheduled) booking per rider: counts non-return legs with a future pickup.
     */
    public function countFutureScheduledBookingsForRider(int $riderUserId): int
    {
        if (! SchemaInspector::columnExists($this->pdo, 'rides', 'scheduled_pickup_at')) {
            return 0;
        }
        $legClause = SchemaInspector::columnExists($this->pdo, 'rides', 'trip_leg')
            ? "AND (trip_leg IS NULL OR trip_leg IN ('single', 'outbound'))"
            : '';
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM rides WHERE rider_user_id = ? '
            . "AND scheduled_pickup_at IS NOT NULL AND scheduled_pickup_at > UTC_TIMESTAMP() "
            . "AND status IN ('requested', 'accepted', 'in_progress') {$legClause}",
        );
        $stmt->execute([$riderUserId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForRiderUser(int $riderUserId, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM rides WHERE rider_user_id = ? ORDER BY id DESC LIMIT ' . (int) $limit,
            );
            $stmt->execute([$riderUserId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            if (self::isMissingTable($e)) {
                return [];
            }
            throw $e;
        }
    }

    public function setCompanionRideIds(int $rideIdA, int $rideIdB): void
    {
        if (! SchemaInspector::columnExists($this->pdo, 'rides', 'companion_ride_id')) {
            return;
        }
        $u = $this->pdo->prepare('UPDATE rides SET companion_ride_id = ? WHERE id = ?');
        $u->execute([$rideIdB, $rideIdA]);
        $u->execute([$rideIdA, $rideIdB]);
    }

    /**
     * @return array{rider_user_id: int}|null
     */
    public function submitRating(int $rideId, int $riderUserId, int $stars, ?string $feedback): ?array
    {
        if (! SchemaInspector::columnExists($this->pdo, 'rides', 'rating_stars')) {
            return null;
        }
        if ($stars < 1 || $stars > 5) {
            return null;
        }
        $fb = $feedback !== null ? mb_substr(trim($feedback), 0, 2000) : null;
        if ($fb === '') {
            $fb = null;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE rides SET rating_stars = ?, feedback_text = ?, rated_at = NOW() '
            . 'WHERE id = ? AND rider_user_id = ? AND status = \'completed\' '
            . 'AND rating_stars IS NULL',
        );
        $stmt->execute([$stars, $fb, $rideId, $riderUserId]);
        if ($stmt->rowCount() < 1) {
            return null;
        }

        return ['rider_user_id' => $riderUserId];
    }

    /**
     * @return array{rider_user_id: int}|null when updated
     */
    public function markPaid(int $rideId): ?array
    {
        if (! SchemaInspector::columnExists($this->pdo, 'rides', 'payment_status')) {
            return null;
        }
        $sel = $this->pdo->prepare(
            'SELECT rider_user_id FROM rides WHERE id = ? AND payment_status = \'pending\' '
            . 'AND status IN (\'requested\', \'accepted\', \'in_progress\', \'completed\') LIMIT 1',
        );
        $sel->execute([$rideId]);
        $rid = $sel->fetchColumn();
        if ($rid === false) {
            return null;
        }
        $riderUserId = (int) $rid;
        $stmt = $this->pdo->prepare(
            'UPDATE rides SET payment_status = \'paid\', paid_at = NOW() WHERE id = ? AND payment_status = \'pending\' '
            . 'AND status IN (\'requested\', \'accepted\', \'in_progress\', \'completed\')',
        );
        $stmt->execute([$rideId]);
        if ($stmt->rowCount() < 1) {
            return null;
        }

        return ['rider_user_id' => $riderUserId];
    }

    /**
     * Active booking: in-flight from rider or assigned driver perspective.
     *
     * @return array<string, mixed>|null
     */
    public function findActiveRideForRiderUser(int $riderUserId): ?array
    {
        $hasDriver = SchemaInspector::columnExists($this->pdo, 'rides', 'driver_rider_user_id');
        if ($hasDriver) {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM rides WHERE status IN (\'requested\', \'accepted\', \'in_progress\') '
                . 'AND (rider_user_id = ? OR driver_rider_user_id = ?) '
                . 'ORDER BY id DESC LIMIT 1',
            );
            $stmt->execute([$riderUserId, $riderUserId]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM rides WHERE status IN (\'requested\', \'accepted\', \'in_progress\') '
                . 'AND rider_user_id = ? ORDER BY id DESC LIMIT 1',
            );
            $stmt->execute([$riderUserId]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdForRiderUser(int $rideId, int $riderUserId): ?array
    {
        $hasDriver = SchemaInspector::columnExists($this->pdo, 'rides', 'driver_rider_user_id');
        if ($hasDriver) {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM rides WHERE id = ? AND (rider_user_id = ? OR driver_rider_user_id = ?) LIMIT 1',
            );
            $stmt->execute([$rideId, $riderUserId, $riderUserId]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM rides WHERE id = ? AND rider_user_id = ? LIMIT 1',
            );
            $stmt->execute([$rideId, $riderUserId]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function countAll(): int
    {
        try {
            return (int) $this->pdo->query('SELECT COUNT(*) FROM rides')->fetchColumn();
        } catch (PDOException $e) {
            if (self::isMissingTable($e)) {
                return 0;
            }
            throw $e;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecent(int $limit = 100): array
    {
        return $this->listFiltered(null, null, null, $limit, 0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listFiltered(?string $status, ?string $fromDate, ?string $toDate, int $limit, int $offset): array
    {
        $limit = max(1, min(10000, $limit));
        $offset = max(0, $offset);
        [$sql, $params] = $this->buildFilterQuery($status, $fromDate, $toDate, false);
        $sql .= ' ORDER BY r.id DESC LIMIT ? OFFSET ?';
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

    public function countFiltered(?string $status, ?string $fromDate, ?string $toDate): int
    {
        [$sql, $params] = $this->buildFilterQuery($status, $fromDate, $toDate, true);
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

    private static function isMissingTable(PDOException $e): bool
    {
        $m = $e->getMessage();

        return str_contains($m, '42S02')
            || str_contains($m, "doesn't exist")
            || str_contains($m, 'Base table or view not found');
    }

    /**
     * @return array{0: string, 1: list<int|string>}
     */
    private function buildFilterQuery(?string $status, ?string $fromDate, ?string $toDate, bool $countOnly): array
    {
        $allowed = ['requested', 'accepted', 'in_progress', 'completed', 'cancelled'];
        $where = ['1=1'];
        $params = [];
        if ($status !== null && $status !== '' && in_array($status, $allowed, true)) {
            $where[] = 'r.status = ?';
            $params[] = $status;
        }
        if ($fromDate !== null && $fromDate !== '') {
            $where[] = 'r.created_at >= ?';
            $params[] = $fromDate . ' 00:00:00';
        }
        if ($toDate !== null && $toDate !== '') {
            $where[] = 'r.created_at <= ?';
            $params[] = $toDate . ' 23:59:59';
        }
        $w = implode(' AND ', $where);
        if ($countOnly) {
            return ["SELECT COUNT(*) FROM rides r INNER JOIN rider_users u ON u.id = r.rider_user_id WHERE {$w}", $params];
        }

        return [
            'SELECT r.*, u.email AS rider_email '
            . "FROM rides r INNER JOIN rider_users u ON u.id = r.rider_user_id WHERE {$w}",
            $params,
        ];
    }
}
