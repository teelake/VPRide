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

    /**
     * Mobile app and admin "public" fields only — never includes server-only `email` block.
     *
     * @return array{
     *   googleWebClientId: string,
     *   mapsApiKey: string,
     *   minimumAppVersion: string,
     *   welcome: array<string, mixed>,
     *   features: array<string, mixed>
     * }
     */
    public function getPublicSettings(): array
    {
        $p = $this->loadFullPayload();

        return [
            'googleWebClientId' => $p['googleWebClientId'],
            'mapsApiKey' => $p['mapsApiKey'],
            'minimumAppVersion' => $p['minimumAppVersion'],
            'welcome' => $p['welcome'],
            'features' => $p['features'],
        ];
    }

    /**
     * Outbound email settings stored in DB (admin Settings → Email). Not exposed to mobile clients.
     *
     * @return array{
     *   mailFrom: string,
     *   staffNotifyOnRiderSignup: bool,
     *   staffNotifyEmails: string,
     *   staffNotifySubject: string,
     *   staffNotifyBody: string,
     *   riderWelcomeEnabled: bool,
     *   riderWelcomeSubject: string,
     *   riderWelcomeBody: string
     * }
     */
    public function getEmailSettings(): array
    {
        return $this->loadFullPayload()['email'];
    }

    /**
     * Effective outbound config: DB first, then .env fallbacks for From line and staff notify list.
     *
     * @return array{
     *   mailFrom: string,
     *   staffNotifyOnRiderSignup: bool,
     *   staffNotifyEmails: string,
     *   staffNotifySubject: string,
     *   staffNotifyBody: string,
     *   riderWelcomeEnabled: bool,
     *   riderWelcomeSubject: string,
     *   riderWelcomeBody: string
     * }
     */
    public static function emailOutboundEffective(PDO $pdo): array
    {
        $e = (new self($pdo))->loadFullPayload()['email'];
        $mailFrom = trim($e['mailFrom']);
        if ($mailFrom === '') {
            $mailFrom = trim((string) getenv('APP_MAIL_FROM'));
        }
        $staffEmails = trim($e['staffNotifyEmails']);
        if ($staffEmails === '') {
            $staffEmails = trim((string) getenv('RIDER_SIGNUP_NOTIFY_EMAIL'));
        }

        return [
            'mailFrom' => $mailFrom,
            'staffNotifyOnRiderSignup' => $e['staffNotifyOnRiderSignup'],
            'staffNotifyEmails' => $staffEmails,
            'staffNotifySubject' => $e['staffNotifySubject'],
            'staffNotifyBody' => $e['staffNotifyBody'],
            'riderWelcomeEnabled' => $e['riderWelcomeEnabled'],
            'riderWelcomeSubject' => $e['riderWelcomeSubject'],
            'riderWelcomeBody' => $e['riderWelcomeBody'],
        ];
    }

    /**
     * @param array{
     *   googleWebClientId?: string,
     *   mapsApiKey?: string,
     *   minimumAppVersion?: string,
     *   welcome?: array<string, mixed>,
     *   features?: array<string, mixed>,
     *   email?: array<string, mixed>
     * } $patch
     */
    public function savePublicSettings(array $patch, int $updatedByAdminId): void
    {
        $current = $this->loadFullPayload();
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

        $mergedEmail = $current['email'];
        if (isset($patch['email']) && is_array($patch['email'])) {
            $mergedEmail = self::normalizeEmail($patch['email'], $current['email']);
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
            'email' => $mergedEmail,
        ];
        if (strlen($merged['googleWebClientId']) > 512 || strlen($merged['mapsApiKey']) > 512) {
            throw new RuntimeException('Value too long');
        }
        self::validateMinimumAppVersion($merged['minimumAppVersion']);
        self::validateGoogleWebClientId($merged['googleWebClientId']);
        self::validateMapsApiKey($merged['mapsApiKey']);
        self::validateHttpUrlOrEmpty($merged['features']['helpCenterUrl'], 'Help center URL');
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
     * @return array{
     *   googleWebClientId: string,
     *   mapsApiKey: string,
     *   minimumAppVersion: string,
     *   welcome: array<string, mixed>,
     *   features: array<string, mixed>,
     *   email: array<string, mixed>
     * }
     */
    private function loadFullPayload(): array
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
        $emailIn = is_array($decoded['email'] ?? null) ? $decoded['email'] : [];

        return [
            'googleWebClientId' => trim((string) ($decoded['googleWebClientId'] ?? $defaults['googleWebClientId'])),
            'mapsApiKey' => trim((string) ($decoded['mapsApiKey'] ?? $defaults['mapsApiKey'])),
            'minimumAppVersion' => trim((string) ($decoded['minimumAppVersion'] ?? $defaults['minimumAppVersion'])),
            'welcome' => self::normalizeWelcome($welcomeIn, $defaults['welcome']),
            'features' => $features,
            'email' => self::normalizeEmail($emailIn, self::defaultEmail()),
        ];
    }

    /**
     * @param array<string, mixed> $in
     * @param array<string, mixed> $base
     * @return array<string, mixed>
     */
    private static function normalizeEmail(array $in, array $base): array
    {
        $def = self::defaultEmail();
        $base = array_merge($def, $base);

        $mailFrom = array_key_exists('mailFrom', $in)
            ? trim((string) $in['mailFrom'])
            : trim((string) $base['mailFrom']);
        if (strlen($mailFrom) > 255) {
            throw new RuntimeException('Email From line too long');
        }

        $staffNotifyEmails = array_key_exists('staffNotifyEmails', $in)
            ? trim((string) $in['staffNotifyEmails'])
            : trim((string) $base['staffNotifyEmails']);
        if (strlen($staffNotifyEmails) > 2000) {
            throw new RuntimeException('Staff notify list too long');
        }

        $staffNotifySubject = array_key_exists('staffNotifySubject', $in)
            ? trim((string) $in['staffNotifySubject'])
            : trim((string) $base['staffNotifySubject']);
        if ($staffNotifySubject === '') {
            $staffNotifySubject = $def['staffNotifySubject'];
        }
        if (strlen($staffNotifySubject) > 200) {
            throw new RuntimeException('Staff notify subject too long');
        }

        $staffNotifyBody = array_key_exists('staffNotifyBody', $in)
            ? trim((string) $in['staffNotifyBody'])
            : trim((string) $base['staffNotifyBody']);
        if ($staffNotifyBody === '') {
            $staffNotifyBody = $def['staffNotifyBody'];
        }
        if (strlen($staffNotifyBody) > 4000) {
            throw new RuntimeException('Staff notify body too long');
        }

        $riderWelcomeSubject = array_key_exists('riderWelcomeSubject', $in)
            ? trim((string) $in['riderWelcomeSubject'])
            : trim((string) $base['riderWelcomeSubject']);
        if ($riderWelcomeSubject === '') {
            $riderWelcomeSubject = $def['riderWelcomeSubject'];
        }
        if (strlen($riderWelcomeSubject) > 200) {
            throw new RuntimeException('Rider welcome subject too long');
        }

        $riderWelcomeBody = array_key_exists('riderWelcomeBody', $in)
            ? trim((string) $in['riderWelcomeBody'])
            : trim((string) $base['riderWelcomeBody']);
        if ($riderWelcomeBody === '') {
            $riderWelcomeBody = $def['riderWelcomeBody'];
        }
        if (strlen($riderWelcomeBody) > 4000) {
            throw new RuntimeException('Rider welcome body too long');
        }

        return [
            'mailFrom' => $mailFrom,
            'staffNotifyOnRiderSignup' => array_key_exists('staffNotifyOnRiderSignup', $in)
                ? self::boolish($in['staffNotifyOnRiderSignup'])
                : self::boolish($base['staffNotifyOnRiderSignup']),
            'staffNotifyEmails' => $staffNotifyEmails,
            'staffNotifySubject' => $staffNotifySubject,
            'staffNotifyBody' => $staffNotifyBody,
            'riderWelcomeEnabled' => array_key_exists('riderWelcomeEnabled', $in)
                ? self::boolish($in['riderWelcomeEnabled'])
                : self::boolish($base['riderWelcomeEnabled']),
            'riderWelcomeSubject' => $riderWelcomeSubject,
            'riderWelcomeBody' => $riderWelcomeBody,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultEmail(): array
    {
        return [
            'mailFrom' => '',
            'staffNotifyOnRiderSignup' => true,
            'staffNotifyEmails' => '',
            'staffNotifySubject' => 'VP Ride: new rider account',
            'staffNotifyBody' => "A new rider signed up from the mobile app.\n\nEmail: {email}\nDisplay name: {displayName}\nUser ID: {userId}\n",
            'riderWelcomeEnabled' => true,
            'riderWelcomeSubject' => 'Welcome to VP Ride',
            'riderWelcomeBody' => "{greeting}Thanks for creating your rider account. Open the VP Ride app to book a ride.\n\n— VP Ride",
        ];
    }

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
        if ($url !== '') {
            self::validateHttpUrlOrEmpty($url, 'Welcome background URL');
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
            'email' => self::defaultEmail(),
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

    private static function validateMinimumAppVersion(string $v): void
    {
        $v = trim($v);
        if ($v === '') {
            throw new RuntimeException('Minimum app version is required');
        }
        if (strlen($v) > 32) {
            throw new RuntimeException('Minimum app version is too long');
        }
        if (! preg_match('/^\d+(\.\d+){1,2}([a-zA-Z0-9._+-]*)?$/', $v)) {
            throw new RuntimeException('Minimum app version must look like a semantic version (e.g. 1.0.0 or 1.2)');
        }
    }

    private static function validateGoogleWebClientId(string $id): void
    {
        $id = trim($id);
        if ($id === '') {
            return;
        }
        if (strlen($id) > 512) {
            throw new RuntimeException('Google Web client ID is too long');
        }
        if (! preg_match('/^[0-9a-zA-Z_-]+\.apps\.googleusercontent\.com$/', $id)) {
            throw new RuntimeException(
                'Google Web client ID should be the OAuth client ending in .apps.googleusercontent.com',
            );
        }
    }

    private static function validateMapsApiKey(string $key): void
    {
        $key = trim($key);
        if ($key === '') {
            return;
        }
        if (strlen($key) > 512) {
            throw new RuntimeException('Maps API key is too long');
        }
        if (! preg_match('/^[A-Za-z0-9_\-]{30,512}$/', $key)) {
            throw new RuntimeException('Maps API key format looks invalid');
        }
    }

    private static function validateHttpUrlOrEmpty(string $url, string $label): void
    {
        $url = trim($url);
        if ($url === '') {
            return;
        }
        if (strlen($url) > 2048) {
            throw new RuntimeException($label . ' is too long');
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException($label . ' is not a valid URL');
        }
        $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?? ''));
        if (! in_array($scheme, ['https', 'http'], true)) {
            throw new RuntimeException($label . ' must use http or https');
        }
    }
}
