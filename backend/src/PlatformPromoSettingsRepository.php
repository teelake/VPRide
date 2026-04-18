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
     *   pricing_base_fare: float,
     *   pricing_per_km: float,
     *   pricing_minimum_fare: float,
     *   advance_booking_max_days: int,
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
            $stmt = $this->pdo->query('SELECT * FROM platform_promo_settings WHERE id = 1 LIMIT 1');
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        } catch (PDOException) {
            return self::defaults();
        }
        if ($row === false) {
            return self::defaults();
        }
        $d = self::defaults();

        return [
            'currency_code' => trim((string) ($row['currency_code'] ?? $d['currency_code'])) ?: $d['currency_code'],
            'decimal_places' => max(0, min(4, (int) ($row['decimal_places'] ?? $d['decimal_places']))),
            'default_ride_estimate' => (float) ($row['default_ride_estimate'] ?? $d['default_ride_estimate']),
            'pricing_base_fare' => isset($row['pricing_base_fare']) ? (float) $row['pricing_base_fare'] : $d['pricing_base_fare'],
            'pricing_per_km' => isset($row['pricing_per_km']) ? (float) $row['pricing_per_km'] : $d['pricing_per_km'],
            'pricing_minimum_fare' => isset($row['pricing_minimum_fare']) ? (float) $row['pricing_minimum_fare'] : $d['pricing_minimum_fare'],
            'advance_booking_max_days' => isset($row['advance_booking_max_days'])
                ? max(1, min(365, (int) $row['advance_booking_max_days']))
                : $d['advance_booking_max_days'],
            'promo_timezone' => trim((string) ($row['promo_timezone'] ?? $d['promo_timezone'])) ?: 'UTC',
            'loyalty_enabled' => ! empty($row['loyalty_enabled']),
            'loyalty_trips_per_reward' => max(1, (int) ($row['loyalty_trips_per_reward'] ?? $d['loyalty_trips_per_reward'])),
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
     *   loyalty_reward_promotion_id?: int|null,
     *   pricing_base_fare?: float,
     *   pricing_per_km?: float,
     *   pricing_minimum_fare?: float,
     *   advance_booking_max_days?: int
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
        $pBase = isset($patch['pricing_base_fare']) ? (float) $patch['pricing_base_fare'] : $cur['pricing_base_fare'];
        $pKm = isset($patch['pricing_per_km']) ? (float) $patch['pricing_per_km'] : $cur['pricing_per_km'];
        $pMin = isset($patch['pricing_minimum_fare']) ? (float) $patch['pricing_minimum_fare'] : $cur['pricing_minimum_fare'];
        if ($pBase < 0 || $pBase > 99999999.99 || $pKm < 0 || $pKm > 999999.9999 || $pMin < 0 || $pMin > 99999999.99) {
            throw new \RuntimeException('invalid_pricing');
        }
        $advDays = isset($patch['advance_booking_max_days']) ? (int) $patch['advance_booking_max_days'] : $cur['advance_booking_max_days'];
        $advDays = max(1, min(365, $advDays));
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

        $hasPricingCols = SchemaInspector::columnExists($this->pdo, 'platform_promo_settings', 'pricing_base_fare');
        if ($hasPricingCols) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO platform_promo_settings (id, currency_code, decimal_places, default_ride_estimate, '
                . 'pricing_base_fare, pricing_per_km, pricing_minimum_fare, promo_timezone, '
                . 'loyalty_enabled, loyalty_trips_per_reward, loyalty_reward_promotion_id, advance_booking_max_days, updated_by_admin_id) '
                . 'VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) '
                . 'ON DUPLICATE KEY UPDATE currency_code = VALUES(currency_code), decimal_places = VALUES(decimal_places), '
                . 'default_ride_estimate = VALUES(default_ride_estimate), '
                . 'pricing_base_fare = VALUES(pricing_base_fare), pricing_per_km = VALUES(pricing_per_km), '
                . 'pricing_minimum_fare = VALUES(pricing_minimum_fare), '
                . 'promo_timezone = VALUES(promo_timezone), '
                . 'loyalty_enabled = VALUES(loyalty_enabled), loyalty_trips_per_reward = VALUES(loyalty_trips_per_reward), '
                . 'loyalty_reward_promotion_id = VALUES(loyalty_reward_promotion_id), '
                . 'advance_booking_max_days = VALUES(advance_booking_max_days), updated_by_admin_id = VALUES(updated_by_admin_id)',
            );
            $stmt->execute([
                $currency,
                $decimals,
                round($est, $decimals),
                round($pBase, 2),
                round($pKm, 4),
                round($pMin, 2),
                $tz,
                $loyEn ? 1 : 0,
                $loyN,
                $loyPid,
                $advDays,
                $updatedByAdminId,
            ]);
        } else {
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
            'pricing_base_fare' => 500.0,
            'pricing_per_km' => 350.0,
            'pricing_minimum_fare' => 0.0,
            'advance_booking_max_days' => 30,
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
