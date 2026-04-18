<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;
use PDOException;

final class PlatformPromoSettingsRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return array{
     *   currency_code: string,
     *   decimal_places: int,
     *   default_ride_estimate: float,
     *   promo_timezone: string,
     *   loyalty_enabled: bool,
     *   loyalty_trips_per_reward: int,
     *   loyalty_reward_promotion_id: ?int
     * }
     */
    public function getSettings(): array
    {
        if (! self::tableExists($this->pdo)) {
            return self::defaults();
        }
        try {
            $stmt = $this->pdo->query(
                'SELECT currency_code, decimal_places, default_ride_estimate, promo_timezone, '
                . 'loyalty_enabled, loyalty_trips_per_reward, loyalty_reward_promotion_id '
                . 'FROM platform_promo_settings WHERE id = 1 LIMIT 1',
            );
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        } catch (PDOException) {
            return self::defaults();
        }
        if ($row === false) {
            return self::defaults();
        }

        return [
            'currency_code' => trim((string) ($row['currency_code'] ?? 'NGN')) ?: 'NGN',
            'decimal_places' => max(0, min(4, (int) ($row['decimal_places'] ?? 2))),
            'default_ride_estimate' => (float) ($row['default_ride_estimate'] ?? 1500),
            'promo_timezone' => trim((string) ($row['promo_timezone'] ?? 'Africa/Lagos')) ?: 'UTC',
            'loyalty_enabled' => ! empty($row['loyalty_enabled']),
            'loyalty_trips_per_reward' => max(1, (int) ($row['loyalty_trips_per_reward'] ?? 5)),
            'loyalty_reward_promotion_id' => isset($row['loyalty_reward_promotion_id']) && $row['loyalty_reward_promotion_id'] !== null
                ? (int) $row['loyalty_reward_promotion_id']
                : null,
        ];
    }

    /**
     * @param array{
     *   currency_code?: string,
     *   decimal_places?: int,
     *   default_ride_estimate?: float,
     *   promo_timezone?: string,
     *   loyalty_enabled?: bool,
     *   loyalty_trips_per_reward?: int,
     *   loyalty_reward_promotion_id?: int|null
     * } $patch
     */
    public function save(array $patch, int $updatedByAdminId): void
    {
        $cur = $this->getSettings();
        $currency = isset($patch['currency_code']) ? strtoupper(trim((string) $patch['currency_code'])) : $cur['currency_code'];
        if (strlen($currency) !== 3) {
            throw new \RuntimeException('invalid_currency');
        }
        $decimals = isset($patch['decimal_places']) ? (int) $patch['decimal_places'] : $cur['decimal_places'];
        $decimals = max(0, min(4, $decimals));
        $est = isset($patch['default_ride_estimate']) ? (float) $patch['default_ride_estimate'] : $cur['default_ride_estimate'];
        if ($est < 0 || $est > 99999999.99) {
            throw new \RuntimeException('invalid_estimate');
        }
        $tz = isset($patch['promo_timezone']) ? trim((string) $patch['promo_timezone']) : $cur['promo_timezone'];
        if ($tz === '' || strlen($tz) > 64) {
            throw new \RuntimeException('invalid_timezone');
        }
        try {
            new \DateTimeZone($tz);
        } catch (\Throwable) {
            throw new \RuntimeException('invalid_timezone');
        }
        $loyEn = isset($patch['loyalty_enabled']) ? self::boolish($patch['loyalty_enabled']) : $cur['loyalty_enabled'];
        $loyN = isset($patch['loyalty_trips_per_reward']) ? (int) $patch['loyalty_trips_per_reward'] : $cur['loyalty_trips_per_reward'];
        $loyN = max(1, min(1000, $loyN));
        $loyPid = array_key_exists('loyalty_reward_promotion_id', $patch)
            ? ($patch['loyalty_reward_promotion_id'] === null ? null : (int) $patch['loyalty_reward_promotion_id'])
            : $cur['loyalty_reward_promotion_id'];
        if ($loyPid !== null && $loyPid < 1) {
            $loyPid = null;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO platform_promo_settings (id, currency_code, decimal_places, default_ride_estimate, promo_timezone, '
            . 'loyalty_enabled, loyalty_trips_per_reward, loyalty_reward_promotion_id, updated_by_admin_id) '
            . 'VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE currency_code = VALUES(currency_code), decimal_places = VALUES(decimal_places), '
            . 'default_ride_estimate = VALUES(default_ride_estimate), promo_timezone = VALUES(promo_timezone), '
            . 'loyalty_enabled = VALUES(loyalty_enabled), loyalty_trips_per_reward = VALUES(loyalty_trips_per_reward), '
            . 'loyalty_reward_promotion_id = VALUES(loyalty_reward_promotion_id), updated_by_admin_id = VALUES(updated_by_admin_id)',
        );
        $stmt->execute([
            $currency,
            $decimals,
            round($est, $decimals),
            $tz,
            $loyEn ? 1 : 0,
            $loyN,
            $loyPid,
            $updatedByAdminId,
        ]);
    }

    public static function tableExists(PDO $pdo): bool
    {
        return SchemaInspector::tableExists($pdo, 'platform_promo_settings');
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaults(): array
    {
        return [
            'currency_code' => 'NGN',
            'decimal_places' => 2,
            'default_ride_estimate' => 1500.0,
            'promo_timezone' => 'Africa/Lagos',
            'loyalty_enabled' => true,
            'loyalty_trips_per_reward' => 5,
            'loyalty_reward_promotion_id' => null,
        ];
    }

    private static function boolish(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        $s = strtolower(trim((string) $v));

        return $s === '1' || $s === 'true' || $s === 'yes' || $s === 'on';
    }
}
