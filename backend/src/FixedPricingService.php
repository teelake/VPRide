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

    /**
     * Public estimate / booking fare before promos, using admin "pricing mode":
     * distance (legacy), flat_town (e.g. $10 in town up to a max distance), or metered (base + m/100 + wait/15s).
     *
     * @param array<string, mixed> $settings from PlatformPromoSettingsRepository::getSettings()
     * @param int $waitSeconds waiting time to include in metered mode (0 for a quick estimate)
     */
    public static function fareForBookingRequest(
        array $settings,
        float $distanceKm,
        int $waitSeconds,
        ?\DateTimeInterface $atUtc = null,
    ): float {
        $decimals = max(0, min(4, (int) ($settings['decimal_places'] ?? 2)));
        $maxD = (float) ($settings['flat_town_max_distance_km'] ?? 0.0);
        $flat = (float) ($settings['flat_town_fare'] ?? 0.0);
        $modeEarly = trim((string) ($settings['pricing_mode'] ?? 'distance'));
        $useFlat = ! empty($settings['use_flat_town_pricing']) || $modeEarly === 'flat_town';
        if ($useFlat && $maxD > 0.0 && $flat > 0.0
            && $distanceKm >= 0.0 && $distanceKm <= $maxD) {
            return round($flat, $decimals, PHP_ROUND_HALF_UP);
        }
        $mode = trim((string) ($settings['pricing_mode'] ?? 'distance'));
        if ($mode === 'metered') {
            $tz = new \DateTimeZone((string) ($settings['promo_timezone'] ?? 'America/Winnipeg'));
            $now = $atUtc ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $local = \DateTimeImmutable::createFromInterface($now)->setTimezone($tz);
            $h = (int) $local->format('G');
            $ns = (int) ($settings['meter_night_start_hour'] ?? 0);
            $ne = (int) ($settings['meter_night_end_hour'] ?? 6);
            $isNight = $h >= $ns && $h < $ne;
            $bDay = (float) ($settings['meter_base_day'] ?? 4.75);
            $bNight = (float) ($settings['meter_base_night'] ?? 6.65);
            $base = $isNight ? $bNight : $bDay;
            $per100m = (float) ($settings['meter_per_100m'] ?? 0.211);
            $per15s = (float) ($settings['meter_per_15s_wait'] ?? 0.15);
            $meters = max(0.0, $distanceKm * 1000.0);
            $distPart = ($meters / 100.0) * $per100m;
            $waitPart = (max(0, $waitSeconds) / 15.0) * $per15s;
            $raw = $base + $distPart + $waitPart;
            if ($raw <= 0.0) {
                return round((float) ($settings['default_ride_estimate'] ?? 0.0), $decimals, PHP_ROUND_HALF_UP);
            }

            return round($raw, $decimals, PHP_ROUND_HALF_UP);
        }

        return self::fareBeforePromosFromDistance($settings, $distanceKm);
    }
}
