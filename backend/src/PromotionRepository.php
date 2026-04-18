<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;
use PDOException;

final class PromotionRepository
{
    public function __construct(private PDO $pdo) {}

    public static function tableExists(PDO $pdo): bool
    {
        return SchemaInspector::tableExists($pdo, 'promotions');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAll(): array
    {
        if (! self::tableExists($this->pdo)) {
            return [];
        }
        try {
            $stmt = $this->pdo->query(
                'SELECT * FROM promotions ORDER BY priority DESC, id DESC',
            );

            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (PDOException) {
            return [];
        }
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM promotions WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function findByCouponCode(string $code): ?array
    {
        $c = strtoupper(trim($code));
        if ($c === '') {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM promotions WHERE coupon_code = ? AND kind = \'coupon\' LIMIT 1',
        );
        $stmt->execute([$c]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveAutomatic(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM promotions WHERE is_active = 1 AND kind = \'automatic\' '
            . 'ORDER BY priority DESC, id ASC',
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countRedemptionsForRider(int $promotionId, int $riderUserId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM promotion_redemptions WHERE promotion_id = ? AND rider_user_id = ?',
        );
        $stmt->execute([$promotionId, $riderUserId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insert(array $row): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO promotions (name, is_active, starts_at, ends_at, kind, coupon_code, discount_kind, '
            . 'discount_value, max_discount_amount, new_users_only, schedule_json, max_uses_per_rider, min_fare_amount, priority) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $stmt->execute([
            $row['name'],
            ! empty($row['is_active']) ? 1 : 0,
            $row['starts_at'] !== null && $row['starts_at'] !== '' ? $row['starts_at'] : null,
            $row['ends_at'] !== null && $row['ends_at'] !== '' ? $row['ends_at'] : null,
            $row['kind'],
            $row['coupon_code'] !== null && $row['coupon_code'] !== '' ? strtoupper(trim((string) $row['coupon_code'])) : null,
            $row['discount_kind'],
            (float) $row['discount_value'],
            $row['max_discount_amount'] !== null && $row['max_discount_amount'] !== '' ? (float) $row['max_discount_amount'] : null,
            ! empty($row['new_users_only']) ? 1 : 0,
            $row['schedule_json'],
            $row['max_uses_per_rider'] !== null && $row['max_uses_per_rider'] !== '' ? (int) $row['max_uses_per_rider'] : null,
            $row['min_fare_amount'] !== null && $row['min_fare_amount'] !== '' ? (float) $row['min_fare_amount'] : null,
            (int) ($row['priority'] ?? 0),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $row
     */
    public function recordRedemption(int $promotionId, int $riderUserId, int $rideId, float $discountAmount): void
    {
        if (! SchemaInspector::tableExists($this->pdo, 'promotion_redemptions')) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO promotion_redemptions (promotion_id, rider_user_id, ride_id, discount_amount) VALUES (?, ?, ?, ?)',
        );
        $stmt->execute([$promotionId, $riderUserId, $rideId, round($discountAmount, 4)]);
    }

    public function update(int $id, array $row): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE promotions SET name = ?, is_active = ?, starts_at = ?, ends_at = ?, kind = ?, coupon_code = ?, '
            . 'discount_kind = ?, discount_value = ?, max_discount_amount = ?, new_users_only = ?, schedule_json = ?, '
            . 'max_uses_per_rider = ?, min_fare_amount = ?, priority = ? WHERE id = ?',
        );
        $stmt->execute([
            $row['name'],
            ! empty($row['is_active']) ? 1 : 0,
            $row['starts_at'] !== null && $row['starts_at'] !== '' ? $row['starts_at'] : null,
            $row['ends_at'] !== null && $row['ends_at'] !== '' ? $row['ends_at'] : null,
            $row['kind'],
            $row['coupon_code'] !== null && $row['coupon_code'] !== '' ? strtoupper(trim((string) $row['coupon_code'])) : null,
            $row['discount_kind'],
            (float) $row['discount_value'],
            $row['max_discount_amount'] !== null && $row['max_discount_amount'] !== '' ? (float) $row['max_discount_amount'] : null,
            ! empty($row['new_users_only']) ? 1 : 0,
            $row['schedule_json'],
            $row['max_uses_per_rider'] !== null && $row['max_uses_per_rider'] !== '' ? (int) $row['max_uses_per_rider'] : null,
            $row['min_fare_amount'] !== null && $row['min_fare_amount'] !== '' ? (float) $row['min_fare_amount'] : null,
            (int) ($row['priority'] ?? 0),
            $id,
        ]);
    }
}
