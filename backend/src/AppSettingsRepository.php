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
     * @return array{googleWebClientId: string, mapsApiKey: string, minimumAppVersion: string}
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

        return [
            'googleWebClientId' => trim((string) ($decoded['googleWebClientId'] ?? $defaults['googleWebClientId'])),
            'mapsApiKey' => trim((string) ($decoded['mapsApiKey'] ?? $defaults['mapsApiKey'])),
            'minimumAppVersion' => trim((string) ($decoded['minimumAppVersion'] ?? $defaults['minimumAppVersion'])),
        ];
    }

    /**
     * @param array{googleWebClientId?: string, mapsApiKey?: string, minimumAppVersion?: string} $patch
     */
    public function savePublicSettings(array $patch, int $updatedByAdminId): void
    {
        $current = $this->getPublicSettings();
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
     * @return array{googleWebClientId: string, mapsApiKey: string, minimumAppVersion: string}
     */
    private static function defaultPayload(): array
    {
        return [
            'googleWebClientId' => '',
            'mapsApiKey' => '',
            'minimumAppVersion' => '1.0.0',
        ];
    }
}
