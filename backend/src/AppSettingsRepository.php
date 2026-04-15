<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;
use PDOException;
use RuntimeException;

final class AppSettingsRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return array{
     *   googleWebClientId: string,
     *   mapsApiKey: string,
     *   minimumAppVersion: string,
     *   features: array{
     *     rideBookingEnabled: bool,
     *     promoBannerEnabled: bool,
     *     maintenanceMode: bool,
     *     maintenanceMessage: string,
     *     helpCenterUrl: string
     *   }
     * }
     */
    public function getPublicSettings(): array
    {
        $defaults = self::defaultPayload();
        try {
            $stmt = $this->pdo->query('SELECT payload FROM app_public_settings WHERE id = 1 LIMIT 1');
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        } catch (PDOException) {
            return $defaults;
        }
        if (! $row || ! isset($row['payload'])) {
            return $defaults;
        }
        /** @var mixed $decoded */
        $decoded = json_decode((string) $row['payload'], true);
        if (! is_array($decoded)) {
            return $defaults;
        }

        $featIn = $decoded['features'] ?? [];
        $featDef = $defaults['features'];
        $features = [
            'rideBookingEnabled' => self::boolish($featIn['rideBookingEnabled'] ?? $featDef['rideBookingEnabled']),
            'promoBannerEnabled' => self::boolish($featIn['promoBannerEnabled'] ?? $featDef['promoBannerEnabled']),
            'maintenanceMode' => self::boolish($featIn['maintenanceMode'] ?? $featDef['maintenanceMode']),
            'maintenanceMessage' => trim((string) ($featIn['maintenanceMessage'] ?? $featDef['maintenanceMessage'])),
            'helpCenterUrl' => trim((string) ($featIn['helpCenterUrl'] ?? $featDef['helpCenterUrl'])),
        ];
        if (strlen($features['maintenanceMessage']) > 2000) {
            $features['maintenanceMessage'] = mb_substr($features['maintenanceMessage'], 0, 2000);
        }
        if (strlen($features['helpCenterUrl']) > 512) {
            $features['helpCenterUrl'] = mb_substr($features['helpCenterUrl'], 0, 512);
        }

        return [
            'googleWebClientId' => trim((string) ($decoded['googleWebClientId'] ?? $defaults['googleWebClientId'])),
            'mapsApiKey' => trim((string) ($decoded['mapsApiKey'] ?? $defaults['mapsApiKey'])),
            'minimumAppVersion' => trim((string) ($decoded['minimumAppVersion'] ?? $defaults['minimumAppVersion'])),
            'features' => $features,
        ];
    }

    /**
     * @param array{
     *   googleWebClientId?: string,
     *   mapsApiKey?: string,
     *   minimumAppVersion?: string,
     *   features?: array<string, mixed>
     * } $patch
     */
    public function savePublicSettings(array $patch, int $updatedByAdminId): void
    {
        $current = $this->getPublicSettings();
        $featPatch = isset($patch['features']) && is_array($patch['features']) ? $patch['features'] : [];
        $mergedFeatures = [
            'rideBookingEnabled' => array_key_exists('rideBookingEnabled', $featPatch)
                ? self::boolish($featPatch['rideBookingEnabled'])
                : $current['features']['rideBookingEnabled'],
            'promoBannerEnabled' => array_key_exists('promoBannerEnabled', $featPatch)
                ? self::boolish($featPatch['promoBannerEnabled'])
                : $current['features']['promoBannerEnabled'],
            'maintenanceMode' => array_key_exists('maintenanceMode', $featPatch)
                ? self::boolish($featPatch['maintenanceMode'])
                : $current['features']['maintenanceMode'],
            'maintenanceMessage' => array_key_exists('maintenanceMessage', $featPatch)
                ? trim((string) $featPatch['maintenanceMessage'])
                : $current['features']['maintenanceMessage'],
            'helpCenterUrl' => array_key_exists('helpCenterUrl', $featPatch)
                ? trim((string) $featPatch['helpCenterUrl'])
                : $current['features']['helpCenterUrl'],
        ];
        if (strlen($mergedFeatures['maintenanceMessage']) > 2000) {
            throw new RuntimeException('Maintenance message too long');
        }
        if (strlen($mergedFeatures['helpCenterUrl']) > 512) {
            throw new RuntimeException('Help URL too long');
        }
        $merged = [
            'googleWebClientId' => isset($patch['googleWebClientId'])
                ? trim((string) $patch['googleWebClientId'])
                : $current['googleWebClientId'],
            'mapsApiKey' => isset($patch['mapsApiKey'])
                ? trim((string) $patch['mapsApiKey'])
                : $current['mapsApiKey'],
            'minimumAppVersion' => isset($patch['minimumAppVersion'])
                ? trim((string) $patch['minimumAppVersion'])
                : $current['minimumAppVersion'],
            'features' => $mergedFeatures,
        ];
        if (strlen($merged['googleWebClientId']) > 512 || strlen($merged['mapsApiKey']) > 512) {
            throw new RuntimeException('Value too long');
        }
        $json = json_encode($merged, JSON_THROW_ON_ERROR);
        $stmt = $this->pdo->prepare(
            'INSERT INTO app_public_settings (id, payload, updated_by_admin_id) VALUES (1, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE payload = VALUES(payload), updated_by_admin_id = VALUES(updated_by_admin_id)',
        );
        $stmt->execute([$json, $updatedByAdminId]);
    }

    /**
     * OAuth Web client ID for verifying Google ID tokens: .env wins when non-empty.
     */
    public static function effectiveGoogleOAuthClientId(PDO $pdo): string
    {
        $env = trim(getenv('GOOGLE_OAUTH_CLIENT_ID') ?: '');
        if ($env !== '') {
            return $env;
        }

        return (new self($pdo))->getPublicSettings()['googleWebClientId'];
    }

    /**
     * @return array{googleWebClientId: string, mapsApiKey: string, minimumAppVersion: string, features: array<string, mixed>}
     */
    private static function defaultPayload(): array
    {
        return [
            'googleWebClientId' => '',
            'mapsApiKey' => '',
            'minimumAppVersion' => '1.0.0',
            'features' => [
                'rideBookingEnabled' => true,
                'promoBannerEnabled' => false,
                'maintenanceMode' => false,
                'maintenanceMessage' => '',
                'helpCenterUrl' => '',
            ],
        ];
    }

    private static function boolish(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if (is_int($v) || is_float($v)) {
            return ((int) $v) !== 0;
        }
        $s = strtolower(trim((string) $v));

        return $s === '1' || $s === 'true' || $s === 'yes' || $s === 'on';
    }
}
