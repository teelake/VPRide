<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;

/**
 * Global and per-driver earnings share (percent of gross fare retained by the driver).
 */
final class DriverEarningsPolicy
{
    public static function globalPercent(PDO $pdo): float
    {
        $o = (new AppSettingsRepository($pdo))->getOperations();

        return self::clampPercent((float) ($o['driverEarningsPercentGlobal'] ?? 80.0));
    }

    /**
     * Effective percent for a fleet-linked app user (driver). Uses override when set on fleet_drivers.
     */
    public static function effectivePercentForDriverRiderUserId(PDO $pdo, int $driverRiderUserId): float
    {
        if ($driverRiderUserId < 1) {
            return self::globalPercent($pdo);
        }
        $global = self::globalPercent($pdo);
        if (! SchemaInspector::columnExists($pdo, 'fleet_drivers', 'earnings_percent_override')) {
            return $global;
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT earnings_percent_override FROM fleet_drivers WHERE rider_user_id = ? LIMIT 1',
            );
            $stmt->execute([$driverRiderUserId]);
            $raw = $stmt->fetchColumn();
        } catch (\Throwable) {
            return $global;
        }
        if ($raw === false || $raw === null) {
            return $global;
        }

        return self::clampPercent((float) $raw);
    }

    /**
     * Gross fare for earnings: final fare when present, otherwise estimated minus promo discount.
     *
     * @param array<string, mixed> $ride
     */
    public static function grossFareForEarnings(array $ride, ?float $finalFareOverride = null): float
    {
        if ($finalFareOverride !== null && $finalFareOverride > 0.0 && is_finite($finalFareOverride)) {
            return round($finalFareOverride, 2, PHP_ROUND_HALF_UP);
        }
        $final = $ride['final_fare_amount'] ?? null;
        if ($final !== null && $final !== '' && is_numeric($final) && (float) $final > 0.0) {
            return round((float) $final, 2, PHP_ROUND_HALF_UP);
        }
        $est = isset($ride['estimated_fare_amount']) && $ride['estimated_fare_amount'] !== null
            ? (float) $ride['estimated_fare_amount']
            : 0.0;
        $disc = isset($ride['promo_discount_amount']) ? (float) $ride['promo_discount_amount'] : 0.0;
        $g = max(0.0, $est - $disc);

        return round($g, 2, PHP_ROUND_HALF_UP);
    }

    public static function driverShareAmount(float $gross, float $percent): float
    {
        if ($gross <= 0.0 || ! is_finite($gross)) {
            return 0.0;
        }
        $p = self::clampPercent($percent);

        return round($gross * ($p / 100.0), 2, PHP_ROUND_HALF_UP);
    }

    private static function clampPercent(float $p): float
    {
        if (! is_finite($p)) {
            return 80.0;
        }
        if ($p < 0.0) {
            return 0.0;
        }
        if ($p > 100.0) {
            return 100.0;
        }

        return round($p, 2, PHP_ROUND_HALF_UP);
    }
}
