<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;

/**
 * Rider-facing ride JSON: lifecycle phase, driver snapshot, ETA hints.
 */
final class RiderRideViewPresenter
{
    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function mapManyForRider(PDO $pdo, array $rows, int $decimals = 2): array
    {
        /** @var array<int, array<string, mixed>> $cache */
        $cache = [];
        $out = [];
        foreach ($rows as $row) {
            $out[] = self::buildRiderArray($pdo, $row, $decimals, $cache);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $ride
     * @return array<string, mixed>
     */
    public static function toRiderArray(PDO $pdo, array $ride, int $decimals = 2): array
    {
        /** @var array<int, array<string, mixed>> $cache */
        $cache = [];

        return self::buildRiderArray($pdo, $ride, $decimals, $cache);
    }

    /**
     * @param array<string, mixed> $ride
     * @param array<int, array<string, mixed>> $driverSnapByRiderId
     * @return array<string, mixed>
     */
    private static function buildRiderArray(PDO $pdo, array $ride, int $decimals, array &$driverSnapByRiderId): array
    {
        $out = RideJsonPresenter::toPublicArray($ride, $decimals);
        $out['lifecyclePhase'] = self::lifecyclePhase($ride);
        $driverRiderId = isset($ride['driver_rider_user_id']) && $ride['driver_rider_user_id'] !== null
            && $ride['driver_rider_user_id'] !== ''
            ? (int) $ride['driver_rider_user_id']
            : null;
        $driverSnap = null;
        if ($driverRiderId !== null && $driverRiderId > 0) {
            if (! array_key_exists($driverRiderId, $driverSnapByRiderId)) {
                $driverSnapByRiderId[$driverRiderId] = self::driverSnapshot($pdo, $driverRiderId);
            }
            $driverSnap = $driverSnapByRiderId[$driverRiderId];
        }
        $out['driver'] = $driverSnap;
        $out['eta'] = self::etaSnapshot($ride, $driverSnap);

        return $out;
    }

    /**
     * booking → assignment → pickup → trip → completed | cancelled
     *
     * @param array<string, mixed> $ride
     */
    public static function lifecyclePhase(array $ride): string
    {
        $st = (string) ($ride['status'] ?? '');
        $du = $ride['driver_rider_user_id'] ?? null;

        return match ($st) {
            'cancelled' => 'cancelled',
            'completed' => 'completed',
            'requested' => ($du !== null && $du !== '') ? 'assignment' : 'booking',
            'accepted' => 'pickup',
            'in_progress' => 'trip',
            default => 'booking',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function driverSnapshot(PDO $pdo, int $driverRiderUserId): array
    {
        $displayName = null;
        $vehicle = null;
        $avgRating = null;
        $live = null;

        try {
            $u = $pdo->prepare('SELECT display_name FROM rider_users WHERE id = ? LIMIT 1');
            $u->execute([$driverRiderUserId]);
            $dn = $u->fetchColumn();
            if ($dn !== false && is_string($dn) && trim($dn) !== '') {
                $displayName = trim($dn);
            }
        } catch (\Throwable) {
        }

        if (SchemaInspector::tableExists($pdo, 'fleet_drivers')
            && SchemaInspector::columnExists($pdo, 'fleet_drivers', 'rider_user_id')) {
            try {
                $sql = 'SELECT fd.full_name, fv.make, fv.model, fv.color, fv.plate_number '
                    . 'FROM fleet_drivers fd '
                    . 'LEFT JOIN fleet_vehicles fv ON fv.id = fd.fleet_vehicle_id '
                    . 'WHERE fd.rider_user_id = ? AND fd.status = \'active\' LIMIT 1';
                $st = $pdo->prepare($sql);
                $st->execute([$driverRiderUserId]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if ($row !== false) {
                    $fn = trim((string) ($row['full_name'] ?? ''));
                    if ($fn !== '') {
                        $displayName = $fn;
                    }
                    $make = trim((string) ($row['make'] ?? ''));
                    $model = trim((string) ($row['model'] ?? ''));
                    $color = trim((string) ($row['color'] ?? ''));
                    $plate = trim((string) ($row['plate_number'] ?? ''));
                    $parts = array_filter([$color, trim($make . ' ' . $model), $plate]);
                    if ($parts !== []) {
                        $vehicle = [
                            'make' => $make !== '' ? $make : null,
                            'model' => $model !== '' ? $model : null,
                            'color' => $color !== '' ? $color : null,
                            'plateNumber' => $plate !== '' ? $plate : null,
                            'summary' => implode(' · ', $parts),
                        ];
                    }
                }
            } catch (\Throwable) {
            }
        }

        if (SchemaInspector::columnExists($pdo, 'rides', 'driver_rider_user_id')) {
            try {
                $st = $pdo->prepare(
                    'SELECT AVG(rating_stars) AS a FROM rides '
                    . 'WHERE driver_rider_user_id = ? AND status = \'completed\' '
                    . 'AND rating_stars IS NOT NULL',
                );
                $st->execute([$driverRiderUserId]);
                $a = $st->fetchColumn();
                if ($a !== false && $a !== null && is_numeric($a)) {
                    $avgRating = round((float) $a, 1);
                }
            } catch (\Throwable) {
            }
        }

        $staleSec = max(30, (int) (getenv('ETA_LOCATION_STALE_SECONDS') ?: '180'));
        if (DriverAvailabilityRepository::tableExists($pdo)
            && SchemaInspector::columnExists($pdo, 'driver_availability', 'last_latitude')) {
            try {
                $st = $pdo->prepare(
                    'SELECT last_latitude, last_longitude, location_updated_at '
                    . 'FROM driver_availability WHERE rider_user_id = ? LIMIT 1',
                );
                $st->execute([$driverRiderUserId]);
                $loc = $st->fetch(PDO::FETCH_ASSOC);
                if ($loc !== false
                    && $loc['last_latitude'] !== null
                    && $loc['last_longitude'] !== null
                    && $loc['location_updated_at'] !== null) {
                    $ts = strtotime((string) $loc['location_updated_at']);
                    if ($ts !== false && (time() - $ts) <= $staleSec) {
                        $live = [
                            'latitude' => (float) $loc['last_latitude'],
                            'longitude' => (float) $loc['last_longitude'],
                            'capturedAt' => str_replace(' ', 'T', (string) $loc['location_updated_at']) . 'Z',
                        ];
                    }
                }
            } catch (\Throwable) {
            }
        }

        if ($displayName === null) {
            $displayName = 'Your driver';
        }

        return [
            'displayName' => $displayName,
            'vehicle' => $vehicle,
            'averageRatingStars' => $avgRating,
            'liveLocation' => $live,
        ];
    }

    /**
     * @param array<string, mixed> $ride
     * @param array<string, mixed>|null $driverSnap
     * @return array<string, mixed>
     */
    private static function etaSnapshot(array $ride, ?array $driverSnap): array
    {
        $speed = max(5.0, (float) (getenv('ETA_ASSUMED_SPEED_KMH') ?: '28'));
        $staleSec = max(30, (int) (getenv('ETA_LOCATION_STALE_SECONDS') ?: '180'));

        $routeMin = null;
        if (isset($ride['distance_km']) && $ride['distance_km'] !== null && $ride['distance_km'] !== '') {
            $routeMin = GeoUtils::minutesForDistanceKm((float) $ride['distance_km'], $speed);
        }

        $live = $driverSnap['liveLocation'] ?? null;
        $fresh = is_array($live) && isset($live['latitude'], $live['longitude']);

        $toPickup = null;
        $toDropoff = null;
        $st = (string) ($ride['status'] ?? '');

        if ($fresh) {
            $dlat = (float) $live['latitude'];
            $dlng = (float) $live['longitude'];
            if (isset($ride['pickup_lat'], $ride['pickup_lng'])) {
                $plat = (float) $ride['pickup_lat'];
                $plng = (float) $ride['pickup_lng'];
                if (in_array($st, ['requested', 'accepted'], true)) {
                    $km = GeoUtils::distanceKm($dlat, $dlng, $plat, $plng);
                    $toPickup = GeoUtils::minutesForDistanceKm($km, $speed);
                }
            }
            if ($st === 'in_progress'
                && isset($ride['dropoff_lat'], $ride['dropoff_lng'])
                && $ride['dropoff_lat'] !== null
                && $ride['dropoff_lng'] !== null) {
                $km = GeoUtils::distanceKm(
                    $dlat,
                    $dlng,
                    (float) $ride['dropoff_lat'],
                    (float) $ride['dropoff_lng'],
                );
                $toDropoff = GeoUtils::minutesForDistanceKm($km, $speed);
            }
        }

        return [
            'toPickupMinutes' => $toPickup,
            'toDropoffMinutes' => $toDropoff,
            'routeDurationMinutesEstimate' => $routeMin,
            'driverLocationFresh' => $fresh,
            'assumedAverageSpeedKmh' => round($speed, 1),
            'locationMaxAgeSeconds' => $staleSec,
        ];
    }
}

