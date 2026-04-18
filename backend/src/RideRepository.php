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
            'SELECT rider_user_id FROM rides WHERE id = ? AND payment_status IN (\'pending\', \'submitted\') '
            . 'AND status IN (\'requested\', \'accepted\', \'in_progress\', \'completed\') LIMIT 1',
        );
        $sel->execute([$rideId]);
        $rid = $sel->fetchColumn();
        if ($rid === false) {
            return null;
        }
        $riderUserId = (int) $rid;
        $stmt = $this->pdo->prepare(
            'UPDATE rides SET payment_status = \'paid\', paid_at = NOW() WHERE id = ? '
            . 'AND payment_status IN (\'pending\', \'submitted\') '
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

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $rideId): ?array
    {
        if ($rideId < 1) {
            return null;
        }
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM rides WHERE id = ? LIMIT 1');
            $stmt->execute([$rideId]);
        } catch (PDOException $e) {
            if (self::isMissingTable($e)) {
                return null;
            }
            throw $e;
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function countRefusalsForRide(int $rideId): int
    {
        if (! SchemaInspector::tableExists($this->pdo, 'ride_driver_refusals')) {
            return 0;
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM ride_driver_refusals WHERE ride_id = ?');
        $stmt->execute([$rideId]);

        return (int) $stmt->fetchColumn();
    }

    public function recordDriverRefusal(int $rideId, int $driverRiderUserId): void
    {
        if (! SchemaInspector::tableExists($this->pdo, 'ride_driver_refusals')) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO ride_driver_refusals (ride_id, driver_rider_user_id) VALUES (?, ?)',
        );
        $stmt->execute([$rideId, $driverRiderUserId]);
    }

    public function assignDriverToRequestedRide(int $rideId, int $driverRiderUserId, string $source): void
    {
        if (! SchemaInspector::columnExists($this->pdo, 'rides', 'driver_rider_user_id')) {
            return;
        }
        $src = in_array($source, ['auto', 'manual'], true) ? $source : 'auto';
        if (SchemaInspector::columnExists($this->pdo, 'rides', 'assign_source')) {
            $stmt = $this->pdo->prepare(
                'UPDATE rides SET driver_rider_user_id = ?, assign_source = ? '
                . "WHERE id = ? AND status = 'requested'",
            );
            $stmt->execute([$driverRiderUserId, $src, $rideId]);
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE rides SET driver_rider_user_id = ? WHERE id = ? AND status = \'requested\'',
            );
            $stmt->execute([$driverRiderUserId, $rideId]);
        }
    }

    public function clearDriverOnRequestedRide(int $rideId): void
    {
        if (! SchemaInspector::columnExists($this->pdo, 'rides', 'driver_rider_user_id')) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE rides SET driver_rider_user_id = NULL '
            . "WHERE id = ? AND status = 'requested'",
        );
        $stmt->execute([$rideId]);
        if (SchemaInspector::columnExists($this->pdo, 'rides', 'assign_source')) {
            $u = $this->pdo->prepare(
                "UPDATE rides SET assign_source = 'none' WHERE id = ? AND status = 'requested' AND driver_rider_user_id IS NULL",
            );
            $u->execute([$rideId]);
        }
    }

    /**
     * Manual dispatch from admin. Optionally skip accept step.
     */
    public function adminAssignDriver(int $rideId, int $driverRiderUserId, bool $forceAccepted): bool
    {
        if (! SchemaInspector::columnExists($this->pdo, 'rides', 'driver_rider_user_id')) {
            return false;
        }
        if ($forceAccepted) {
            if (SchemaInspector::columnExists($this->pdo, 'rides', 'assign_source')) {
                $stmt = $this->pdo->prepare(
                    'UPDATE rides SET driver_rider_user_id = ?, assign_source = \'manual\', status = \'accepted\' '
                    . "WHERE id = ? AND status IN ('requested', 'accepted')",
                );
            } else {
                $stmt = $this->pdo->prepare(
                    'UPDATE rides SET driver_rider_user_id = ?, status = \'accepted\' '
                    . "WHERE id = ? AND status IN ('requested', 'accepted')",
                );
            }
            $stmt->execute([$driverRiderUserId, $rideId]);
        } else {
            if (SchemaInspector::columnExists($this->pdo, 'rides', 'assign_source')) {
                $stmt = $this->pdo->prepare(
                    'UPDATE rides SET driver_rider_user_id = ?, assign_source = \'manual\' '
                    . "WHERE id = ? AND status = 'requested'",
                );
            } else {
                $stmt = $this->pdo->prepare(
                    'UPDATE rides SET driver_rider_user_id = ? WHERE id = ? AND status = \'requested\'',
                );
            }
            $stmt->execute([$driverRiderUserId, $rideId]);
        }

        return $stmt->rowCount() > 0;
    }

    public function driverAccept(int $rideId, int $driverRiderUserId): bool
    {
        if (! SchemaInspector::columnExists($this->pdo, 'rides', 'driver_rider_user_id')) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE rides SET status = \'accepted\' WHERE id = ? AND driver_rider_user_id = ? '
            . "AND status = 'requested'",
        );
        $stmt->execute([$rideId, $driverRiderUserId]);

        return $stmt->rowCount() > 0;
    }

    public function driverStartTrip(int $rideId, int $driverRiderUserId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE rides SET status = \'in_progress\' WHERE id = ? AND driver_rider_user_id = ? '
            . "AND status = 'accepted'",
        );
        $stmt->execute([$rideId, $driverRiderUserId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Rider cancels their own ride before completion. Fee is taken from server policy (admin-configured).
     *
     * @return 'ok'|'not_found'|'not_rider'|'cannot_cancel'
     */
    public function riderCancelRide(int $rideId, int $riderUserId, float $cancellationFee): string
    {
        $row = $this->findById($rideId);
        if ($row === null) {
            return 'not_found';
        }
        if ((int) ($row['rider_user_id'] ?? 0) !== $riderUserId) {
            return 'not_rider';
        }
        $st = (string) ($row['status'] ?? '');
        if (! in_array($st, ['requested', 'accepted', 'in_progress'], true)) {
            return 'cannot_cancel';
        }
        $fee = round(max(0.0, min(99_999_999.0, $cancellationFee)), 2, PHP_ROUND_HALF_UP);
        $hasFeeCol = SchemaInspector::columnExists($this->pdo, 'rides', 'cancellation_fee_amount');
        if ($hasFeeCol) {
            $stmt = $this->pdo->prepare(
                'UPDATE rides SET status = \'cancelled\', cancellation_fee_amount = ?, '
                . "cancelled_by = 'rider', cancelled_at = UTC_TIMESTAMP() "
                . 'WHERE id = ? AND rider_user_id = ? '
                . "AND status IN ('requested', 'accepted', 'in_progress')",
            );
            $stmt->execute([$fee, $rideId, $riderUserId]);
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE rides SET status = \'cancelled\' WHERE id = ? AND rider_user_id = ? '
                . "AND status IN ('requested', 'accepted', 'in_progress')",
            );
            $stmt->execute([$rideId, $riderUserId]);
        }

        return $stmt->rowCount() > 0 ? 'ok' : 'cannot_cancel';
    }

    /**
     * Completes trip. Optional final fare (validated, rounded) when console / manual-dispatch rules allow.
     *
     * @return 'ok'|'cannot_complete'|'fare_not_allowed'|'invalid_fare'
     */
    public function driverCompleteTrip(int $rideId, int $driverRiderUserId, ?float $finalFare = null): string
    {
        $row = $this->findById($rideId);
        if ($row === null) {
            return 'cannot_complete';
        }
        if ((int) ($row['driver_rider_user_id'] ?? 0) !== $driverRiderUserId) {
            return 'cannot_complete';
        }
        if (($row['status'] ?? '') !== 'in_progress') {
            return 'cannot_complete';
        }

        $hasEarn = SchemaInspector::columnExists($this->pdo, 'rides', 'driver_earnings_amount')
            && SchemaInspector::columnExists($this->pdo, 'rides', 'driver_earnings_percent_applied');
        $pct = DriverEarningsPolicy::effectivePercentForDriverRiderUserId($this->pdo, $driverRiderUserId);

        if ($finalFare !== null) {
            if (! RideJsonPresenter::driverMaySetFinalFare($row)) {
                return 'fare_not_allowed';
            }
            if ($finalFare <= 0.0 || $finalFare > 99_999_999.0 || ! is_finite($finalFare)) {
                return 'invalid_fare';
            }
            $finalFare = round($finalFare, 2, PHP_ROUND_HALF_UP);
            $gross = DriverEarningsPolicy::grossFareForEarnings($row, $finalFare);
            $net = DriverEarningsPolicy::driverShareAmount($gross, $pct);
            if (! SchemaInspector::columnExists($this->pdo, 'rides', 'final_fare_amount')) {
                if ($hasEarn) {
                    $stmt = $this->pdo->prepare(
                        'UPDATE rides SET status = \'completed\', driver_earnings_amount = ?, '
                        . 'driver_earnings_percent_applied = ? WHERE id = ? AND driver_rider_user_id = ? '
                        . "AND status = 'in_progress'",
                    );
                    $stmt->execute([$net, $pct, $rideId, $driverRiderUserId]);
                } else {
                    $stmt = $this->pdo->prepare(
                        'UPDATE rides SET status = \'completed\' WHERE id = ? AND driver_rider_user_id = ? '
                        . "AND status = 'in_progress'",
                    );
                    $stmt->execute([$rideId, $driverRiderUserId]);
                }

                return $stmt->rowCount() > 0 ? 'ok' : 'cannot_complete';
            }
            if ($hasEarn) {
                $stmt = $this->pdo->prepare(
                    'UPDATE rides SET status = \'completed\', final_fare_amount = ?, '
                    . 'driver_earnings_amount = ?, driver_earnings_percent_applied = ? '
                    . 'WHERE id = ? AND driver_rider_user_id = ? AND status = \'in_progress\'',
                );
                $stmt->execute([$finalFare, $net, $pct, $rideId, $driverRiderUserId]);
            } else {
                $stmt = $this->pdo->prepare(
                    'UPDATE rides SET status = \'completed\', final_fare_amount = ? '
                    . 'WHERE id = ? AND driver_rider_user_id = ? AND status = \'in_progress\'',
                );
                $stmt->execute([$finalFare, $rideId, $driverRiderUserId]);
            }

            return $stmt->rowCount() > 0 ? 'ok' : 'cannot_complete';
        }

        $gross = DriverEarningsPolicy::grossFareForEarnings($row, null);
        $net = DriverEarningsPolicy::driverShareAmount($gross, $pct);
        if ($hasEarn) {
            $stmt = $this->pdo->prepare(
                'UPDATE rides SET status = \'completed\', driver_earnings_amount = ?, '
                . 'driver_earnings_percent_applied = ? WHERE id = ? AND driver_rider_user_id = ? '
                . "AND status = 'in_progress'",
            );
            $stmt->execute([$net, $pct, $rideId, $driverRiderUserId]);
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE rides SET status = \'completed\' WHERE id = ? AND driver_rider_user_id = ? '
                . "AND status = 'in_progress'",
            );
            $stmt->execute([$rideId, $driverRiderUserId]);
        }

        return $stmt->rowCount() > 0 ? 'ok' : 'cannot_complete';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listIncomingForDriver(int $driverRiderUserId, int $limit = 20): array
    {
        if (! SchemaInspector::columnExists($this->pdo, 'rides', 'driver_rider_user_id')) {
            return [];
        }
        $limit = max(1, min(50, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT * FROM rides WHERE driver_rider_user_id = ? AND status = \'requested\' '
            . 'ORDER BY id ASC LIMIT ' . (int) $limit,
        );
        $stmt->execute([$driverRiderUserId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listHistoryForDriver(int $driverRiderUserId, int $limit = 50): array
    {
        if (! SchemaInspector::columnExists($this->pdo, 'rides', 'driver_rider_user_id')) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT r.*, u.email AS rider_email FROM rides r '
            . 'INNER JOIN rider_users u ON u.id = r.rider_user_id '
            . 'WHERE r.driver_rider_user_id = ? AND r.status IN (\'completed\', \'cancelled\') '
            . 'ORDER BY r.id DESC LIMIT ' . (int) $limit,
        );
        $stmt->execute([$driverRiderUserId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sum final fares for completed trips (rider gross / trip total).
     */
    public function sumCompletedGrossFareForDriver(int $driverRiderUserId): float
    {
        if (! SchemaInspector::columnExists($this->pdo, 'rides', 'driver_rider_user_id')
            || ! SchemaInspector::columnExists($this->pdo, 'rides', 'final_fare_amount')) {
            return 0.0;
        }
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(final_fare_amount), 0) FROM rides '
            . "WHERE driver_rider_user_id = ? AND status = 'completed' "
            . 'AND final_fare_amount IS NOT NULL',
        );
        $stmt->execute([$driverRiderUserId]);

        return round((float) $stmt->fetchColumn(), 4);
    }

    /**
     * Driver-retained share. Uses stored driver_earnings_amount when present; otherwise approximates
     * with global platform percent on final_fare_amount (legacy trips — per-driver override not applied).
     */
    public function sumCompletedDriverShareForDriver(int $driverRiderUserId, float $globalPercentFallback): float
    {
        if (! SchemaInspector::columnExists($this->pdo, 'rides', 'driver_rider_user_id')) {
            return 0.0;
        }
        $pct = max(0.0, min(100.0, $globalPercentFallback));
        if (SchemaInspector::columnExists($this->pdo, 'rides', 'driver_earnings_amount')
            && SchemaInspector::columnExists($this->pdo, 'rides', 'final_fare_amount')) {
            $stmt = $this->pdo->prepare(
                'SELECT COALESCE(SUM(CASE '
                . 'WHEN driver_earnings_amount IS NOT NULL THEN driver_earnings_amount '
                . 'WHEN final_fare_amount IS NOT NULL AND final_fare_amount > 0 '
                . 'THEN ROUND(final_fare_amount * (? / 100), 2) '
                . 'ELSE 0 END), 0) FROM rides '
                . "WHERE driver_rider_user_id = ? AND status = 'completed'",
            );
            $stmt->execute([$pct, $driverRiderUserId]);

            return round((float) $stmt->fetchColumn(), 4);
        }
        if (SchemaInspector::columnExists($this->pdo, 'rides', 'final_fare_amount')) {
            $gross = $this->sumCompletedGrossFareForDriver($driverRiderUserId);

            return round($gross * ($pct / 100.0), 4);
        }

        return 0.0;
    }

    /** @deprecated Use sumCompletedGrossFareForDriver */
    public function sumCompletedEarningsForDriver(int $driverRiderUserId): float
    {
        return $this->sumCompletedGrossFareForDriver($driverRiderUserId);
    }

    public function countCompletedTripsForDriver(int $driverRiderUserId): int
    {
        if (! SchemaInspector::columnExists($this->pdo, 'rides', 'driver_rider_user_id')) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM rides WHERE driver_rider_user_id = ? AND status = \'completed\'',
        );
        $stmt->execute([$driverRiderUserId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveTripForDriver(int $driverRiderUserId): ?array
    {
        if (! SchemaInspector::columnExists($this->pdo, 'rides', 'driver_rider_user_id')) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM rides WHERE driver_rider_user_id = ? AND status IN (\'accepted\', \'in_progress\') '
            . 'ORDER BY id DESC LIMIT 1',
        );
        $stmt->execute([$driverRiderUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function riderSubmitOfflinePayment(
        int $rideId,
        int $riderUserId,
        string $method,
        ?string $proofUrl,
        ?string $referenceNote,
    ): bool {
        if (! SchemaInspector::columnExists($this->pdo, 'rides', 'payment_method')) {
            return false;
        }
        if (! in_array($method, ['cash', 'pos', 'bank_transfer'], true)) {
            return false;
        }
        if ($method === 'bank_transfer') {
            $p = $proofUrl !== null ? trim($proofUrl) : '';
            if ($p === '') {
                return false;
            }
        }
        $proof = $proofUrl !== null ? mb_substr(trim($proofUrl), 0, 768) : null;
        if ($proof === '') {
            $proof = null;
        }
        $note = $referenceNote !== null ? mb_substr(trim($referenceNote), 0, 500) : null;
        if ($note === '') {
            $note = null;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE rides SET payment_method = ?, payment_proof_url = ?, payment_reference_note = ?, '
            . 'payment_submitted_at = NOW(), payment_status = \'submitted\' '
            . 'WHERE id = ? AND rider_user_id = ? AND status = \'completed\' AND payment_status = \'pending\'',
        );
        $stmt->execute([$method, $proof, $note, $rideId, $riderUserId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @return array{rider_user_id: int}|null
     */
    public function driverConfirmOfflinePaymentReceived(int $rideId, int $driverRiderUserId): ?array
    {
        if (! SchemaInspector::columnExists($this->pdo, 'rides', 'payment_status')) {
            return null;
        }
        $sel = $this->pdo->prepare(
            'SELECT rider_user_id FROM rides WHERE id = ? AND driver_rider_user_id = ? '
            . "AND status = 'completed' AND payment_status = 'submitted' LIMIT 1",
        );
        $sel->execute([$rideId, $driverRiderUserId]);
        $rid = $sel->fetchColumn();
        if ($rid === false) {
            return null;
        }
        $riderUserId = (int) $rid;
        $stmt = $this->pdo->prepare(
            'UPDATE rides SET payment_status = \'paid\', paid_at = NOW() WHERE id = ? '
            . 'AND driver_rider_user_id = ? AND payment_status = \'submitted\' AND status = \'completed\'',
        );
        $stmt->execute([$rideId, $driverRiderUserId]);
        if ($stmt->rowCount() < 1) {
            return null;
        }

        return ['rider_user_id' => $riderUserId];
    }
}
