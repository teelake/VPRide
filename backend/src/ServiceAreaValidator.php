<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;

/**
 * Licensed service area: each active city in the region JSON is a disc around `center` with
 * radius `licensedRadiusKm` (per-city override or platform default) plus `serviceBufferKm` (e.g. 10 km outskirts).
 */
final class ServiceAreaValidator
{
    /**
     * @param array<string, mixed>|null $regionPayload from RegionRepository::getActivePayload()
     * @return string|null null if inside serviceable area, or error code for API
     */
    public static function firstLocationOutside(
        ?array $regionPayload,
        float $lat,
        float $lng,
        float $defaultLicensedRadiusKm,
        float $bufferKm,
    ): ?string {
        if ($regionPayload === null) {
            return 'region_config_unavailable';
        }
        if (! self::isPointCovered($regionPayload, $lat, $lng, $defaultLicensedRadiusKm, $bufferKm)) {
            return 'pickup_outside_service_area';
        }

        return null;
    }

    /**
     * @return string|null `pickup_outside_service_area` or `dropoff_outside_service_area`
     */
    public static function checkTrip(
        ?array $regionPayload,
        float $pickupLat,
        float $pickupLng,
        ?float $dropLat,
        ?float $dropLng,
        float $defaultLicensedRadiusKm,
        float $bufferKm,
    ): ?string {
        $a = self::firstLocationOutside(
            $regionPayload,
            $pickupLat,
            $pickupLng,
            $defaultLicensedRadiusKm,
            $bufferKm,
        );
        if ($a !== null) {
            return $a;
        }
        if ($dropLat !== null && $dropLng !== null) {
            if (! ($dropLat === 0.0 && $dropLng === 0.0)
                && ! self::isPointCovered(
                    $regionPayload,
                    $dropLat,
                    $dropLng,
                    $defaultLicensedRadiusKm,
                    $bufferKm,
                )) {
                return 'dropoff_outside_service_area';
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $regionPayload
     */
    public static function isPointCovered(
        array $regionPayload,
        float $lat,
        float $lng,
        float $defaultLicensedRadiusKm,
        float $bufferKm,
    ): bool {
        $cities = self::iterCityCenters($regionPayload);
        if ($cities === []) {
            return true;
        }
        $bufferKm = max(0.0, $bufferKm);
        foreach ($cities as $c) {
            $inner = $c['licensedRadiusKm'] > 0.0
                ? $c['licensedRadiusKm']
                : max(0.0, $defaultLicensedRadiusKm);
            $maxDist = $inner + $bufferKm;
            $km = FixedPricingService::haversineKm(
                $lat,
                $lng,
                (float) $c['lat'],
                (float) $c['lng'],
            );
            if ($km <= $maxDist) {
                return true;
            }
        }

        return false;
    }

    /**
     * Active cities: `isActive` true, or if missing, all cities in default country.
     *
     * @return list<array{lat: float, lng: float, licensedRadiusKm: float}>
     */
    private static function iterCityCenters(array $regionPayload): array
    {
        $out = [];
        $countries = $regionPayload['countries'] ?? [];
        if (! is_array($countries)) {
            return [];
        }
        foreach ($countries as $co) {
            if (! is_array($co)) {
                continue;
            }
            $cities = $co['cities'] ?? [];
            if (! is_array($cities)) {
                continue;
            }
            foreach ($cities as $city) {
                if (! is_array($city)) {
                    continue;
                }
                if (isset($city['isActive']) && $city['isActive'] === false) {
                    continue;
                }
                $center = $city['center'] ?? null;
                if (! is_array($center)) {
                    continue;
                }
                $la = (float) ($center['latitude'] ?? 0);
                $lo = (float) ($center['longitude'] ?? 0);
                if ($la === 0.0 && $lo === 0.0) {
                    continue;
                }
                $lr = 0.0;
                if (array_key_exists('licensedRadiusKm', $city)) {
                    $lr = max(0.0, (float) $city['licensedRadiusKm']);
                }
                $out[] = ['lat' => $la, 'lng' => $lo, 'licensedRadiusKm' => $lr];
            }
        }

        return $out;
    }

    public static function loadSettings(PDO $pdo): array
    {
        if (! PlatformPromoSettingsRepository::tableExists($pdo)
            || ! SchemaInspector::columnExists($pdo, 'platform_promo_settings', 'service_buffer_km')) {
            return [
                'bufferKm' => 10.0,
                'licensedRadiusKm' => 15.0,
                'enforce' => false,
            ];
        }
        $p = (new PlatformPromoSettingsRepository($pdo))->getSettings();

        return [
            'bufferKm' => (float) ($p['service_buffer_km'] ?? 10.0),
            'licensedRadiusKm' => (float) ($p['service_licensed_radius_km'] ?? 15.0),
            'enforce' => ! empty($p['enforce_service_area']),
        ];
    }
}
