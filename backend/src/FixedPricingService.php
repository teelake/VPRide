<?php

declare(strict_types=1);

namespace VprideBackend;

/**
 * Distance-based fixed fare: base + per_km * distance, floored at minimum.
 * When per_km is zero or negative, callers should use platform default_ride_estimate instead.
 */
final class FixedPricingService
{
    public static function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthKm = 6371.0;
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos($p1) * cos($p2) * sin($dLon / 2) * sin($dLon / 2);

        return 2 * $earthKm * asin(min(1.0, sqrt($a)));
    }

    /**
     * @param array<string, mixed> $settings from PlatformPromoSettingsRepository::getSettings()
     */
    public static function fareBeforePromosFromDistance(array $settings, float $distanceKm): float
    {
        $decimals = max(0, min(4, (int) ($settings['decimal_places'] ?? 2)));
        $perKm = (float) ($settings['pricing_per_km'] ?? 0.0);
        if ($perKm <= 0.0 || $distanceKm <= 0.0) {
            return round((float) ($settings['default_ride_estimate'] ?? 0.0), $decimals, PHP_ROUND_HALF_UP);
        }
        $base = (float) ($settings['pricing_base_fare'] ?? 0.0);
        $min = (float) ($settings['pricing_minimum_fare'] ?? 0.0);
        $raw = $base + $distanceKm * $perKm;
        $fare = max($min, $raw);

        return round($fare, $decimals, PHP_ROUND_HALF_UP);
    }
}
