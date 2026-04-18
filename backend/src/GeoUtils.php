<?php

declare(strict_types=1);

namespace VprideBackend;

/**
 * Great-circle distance for ETA-style estimates.
 */
final class GeoUtils
{
    public static function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public static function minutesForDistanceKm(float $km, float $assumedSpeedKmh): int
    {
        if ($km <= 0 || $assumedSpeedKmh <= 0) {
            return 1;
        }

        return max(1, (int) round($km / $assumedSpeedKmh * 60));
    }
}
