<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;
use PDOException;

final class RiderRewardGrantRepository
{
    public function __construct(private PDO $pdo) {}

    public static function tableExists(PDO $pdo): bool
    {
        return SchemaInspector::tableExists($pdo, 'rider_reward_grant');
    }

    /**
     * Best discount first: join promotion, order by computed discount desc — simplified: highest priority promo from available grants.
     *
     * @return list<array{grant_id: int, promotion: array<string, mixed>}>
     */
    public function listAvailableGrantsWithPromotions(int $riderUserId): array
    {
        if (! self::tableExists($this->pdo)) {
            return [];
        }
        try {
            $stmt = $this->pdo->prepare(
                'SELECT g.id AS grant_id, p.* FROM rider_reward_grant g '
                . 'INNER JOIN promotions p ON p.id = g.promotion_id '
                . 'WHERE g.rider_user_id = ? AND g.status = \'available\' '
                . 'AND (g.expires_at IS NULL OR g.expires_at > NOW()) '
                . 'AND p.is_active = 1 '
                . 'ORDER BY p.priority DESC, g.id ASC',
            );
            $stmt->execute([$riderUserId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $gid = (int) $r['grant_id'];
            $promo = $r;
            unset($promo['grant_id']);
            $out[] = ['grant_id' => $gid, 'promotion' => $promo];
        }

        return $out;
    }

    public function markApplied(int $grantId, int $rideId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE rider_reward_grant SET status = \'applied\', applied_ride_id = ? WHERE id = ? AND status = \'available\'',
        );
        $stmt->execute([$rideId, $grantId]);
    }

    public function insertGrant(int $riderUserId, int $promotionId, ?string $expiresAt): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rider_reward_grant (rider_user_id, promotion_id, status, expires_at) VALUES (?, ?, \'available\', ?)',
        );
        $stmt->execute([$riderUserId, $promotionId, $expiresAt]);

        return (int) $this->pdo->lastInsertId();
    }
}
