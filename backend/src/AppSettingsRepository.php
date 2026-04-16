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
     *   welcome: array{backgroundImageUrl: string, overlayColor: string, overlayOpacity: float},
     *   features: array{
     *     rideBookingEnabled: bool,
     *     promoBannerEnabled: bool,
     *     maintenanceMode: bool,
     *     maintenanceMessage: string,
     *     helpCenterUrl: string,
     *     requireSignInForHome: bool
     *   }
     * }
     *
     * mapsApiKeyWithEnvFallback: DB value first; if empty, MAPS_API_KEY /
     * GOOGLE_MAPS_API_KEY from the environment.
     */
    public static function mapsApiKeyWithEnvFallback(string $fromDatabase): string
    {
        $t = trim($fromDatabase);
        if ($t !== '') {
            return $t;
        }
        foreach (['MAPS_API_KEY', 'GOOGLE_MAPS_API_KEY'] as $envKey) {
            $raw = getenv($envKey);
            if (is_string($raw) && trim($raw) !== '') {
                return trim($raw);
            }
        }

        return '';
    }

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
            'requireSignInForHome' => self::boolish($featIn['requireSignInForHome'] ?? $featDef['requireSignInForHome']),
        ];
        if (strlen($features['maintenanceMessage']) > 2000) {
            $features['maintenanceMessage'] = mb_substr($features['maintenanceMessage'], 0, 2000);
        }
        if (strlen($features['helpCenterUrl']) > 512) {
            $features['helpCenterUrl'] = mb_substr($features['helpCenterUrl'], 0, 512);
        }

        $welcomeIn = is_array($decoded['welcome'] ?? null) ? $decoded['welcome'] : [];

        return [
            'googleWebClientId' => trim((string) ($decoded['googleWebClientId'] ?? $defaults['googleWebClientId'])),
            'mapsApiKey' => trim((string) ($decoded['mapsApiKey'] ?? $defaults['mapsApiKey'])),
            'minimumAppVersion' => trim((string) ($decoded['minimumAppVersion'] ?? $defaults['minimumAppVersion'])),
            'welcome' => self::normalizeWelcome($welcomeIn, $defaults['welcome']),
            'features' => $features,
        ];
    }

    /**
     * @param array{
     *   googleWebClientId?: string,
     *   mapsApiKey?: string,
     *   minimumAppVersion?: string,
     *   welcome?: array{backgroundImageUrl?: string, overlayColor?: string, overlayOpacity?: float|int|string},
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
            'requireSignInForHome' => array_key_exists('requireSignInForHome', $featPatch)
                ? self::boolish($featPatch['requireSignInForHome'])
                : $current['features']['requireSignInForHome'],
        ];
        if (strlen($mergedFeatures['maintenanceMessage']) > 2000) {
            throw new RuntimeException('Maintenance message too long');
        }
        if (strlen($mergedFeatures['helpCenterUrl']) > 512) {
            throw new RuntimeException('Help URL too long');
        }
        $welcomeMerged = $current['welcome'];
        if (isset($patch['welcome']) && is_array($patch['welcome'])) {
            $welcomeMerged = self::normalizeWelcome($patch['welcome'], $current['welcome']);
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
            'welcome' => $welcomeMerged,
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
    /**
     * @param array<string, mixed> $in
     * @param array<string, mixed> $base
     * @return array<string, mixed>
     */
    private static function normalizeWelcome(array $in, array $base): array
    {
        $defWelcome = self::defaultWelcome();
        $s = static function (string $key, int $max, array $in, array $base) use ($defWelcome): string {
            $v = array_key_exists($key, $in) ? trim((string) $in[$key]) : '';
            if ($v === '') {
                $v = trim((string) ($base[$key] ?? $defWelcome[$key] ?? ''));
            }
            if (strlen($v) > $max) {
                $v = mb_substr($v, 0, $max);
            }

            return $v;
        };

        $url = array_key_exists('backgroundImageUrl', $in)
            ? trim((string) $in['backgroundImageUrl'])
            : (string) ($base['backgroundImageUrl'] ?? '');
        if (strlen($url) > 2048) {
            throw new RuntimeException('Welcome background URL too long');
        }
        $color = array_key_exists('overlayColor', $in)
            ? trim((string) $in['overlayColor'])
            : (string) ($base['overlayColor'] ?? '#F0F0F0');
        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $color = (string) ($base['overlayColor'] ?? '#F0F0F0');
        }
        $op = isset($base['overlayOpacity']) ? (float) $base['overlayOpacity'] : 0.78;
        if (array_key_exists('overlayOpacity', $in)) {
            $op = (float) $in['overlayOpacity'];
        }
        if ($op < 0.0) {
            $op = 0.0;
        }
        if ($op > 1.0) {
            $op = 1.0;
        }

        return [
            'backgroundImageUrl' => $url,
            'overlayColor' => $color,
            'overlayOpacity' => $op,
            'brandWordmark' => $s('brandWordmark', 48, $in, $base),
            'headline' => $s('headline', 120, $in, $base),
            'subhead' => $s('subhead', 600, $in, $base),
            'featureLeftTitle' => $s('featureLeftTitle', 64, $in, $base),
            'featureRightTitle' => $s('featureRightTitle', 64, $in, $base),
            'footerTagline' => $s('footerTagline', 80, $in, $base),
            'showFeatureRow' => self::boolish($in['showFeatureRow'] ?? $base['showFeatureRow'] ?? true),
            'showPagerDots' => self::boolish($in['showPagerDots'] ?? $base['showPagerDots'] ?? true),
            'ctaRegister' => $s('ctaRegister', 48, $in, $base),
            'ctaEmailLogin' => $s('ctaEmailLogin', 48, $in, $base),
            'ctaGoogle' => $s('ctaGoogle', 64, $in, $base),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultWelcome(): array
    {
        return [
            'backgroundImageUrl' => '',
            'overlayColor' => '#F0F0F0',
            'overlayOpacity' => 0.78,
            'brandWordmark' => 'VP RIDE',
            'headline' => 'Move with intention',
            'subhead' => 'Book a ride in a few taps, or open the map to choose your pickup. We are built for {{region}}.',
            'featureLeftTitle' => 'Elite Safety',
            'featureRightTitle' => 'On Demand',
            'footerTagline' => 'NAVIGATE THE CITY',
            'showFeatureRow' => true,
            'showPagerDots' => true,
            'ctaRegister' => 'Create account',
            'ctaEmailLogin' => 'Sign in',
            'ctaGoogle' => 'Continue with Google',
        ];
    }

    private static function defaultPayload(): array
    {
        return [
            'googleWebClientId' => '',
            'mapsApiKey' => '',
            'minimumAppVersion' => '1.0.0',
            'welcome' => self::defaultWelcome(),
            'features' => [
                'rideBookingEnabled' => true,
                'promoBannerEnabled' => false,
                'maintenanceMode' => false,
                'maintenanceMessage' => '',
                'helpCenterUrl' => '',
                'requireSignInForHome' => true,
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
