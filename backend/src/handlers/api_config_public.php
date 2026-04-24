<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/AppSettingsRepository.php';
require_once $backendRoot . '/src/HttpCacheJson.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\AppSettingsRepository;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\HttpCacheJson;

Config::load($backendRoot . '/.env');

ApiMobileCors::sendPreflightIfOptions();
ApiMobileCors::headers();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed'], JSON_THROW_ON_ERROR);
    exit;
}

try {
    $settings = (new AppSettingsRepository(Database::pdo()))->getPublicSettings();
    $mapsForApp = AppSettingsRepository::mapsApiKeyWithEnvFallback(
        (string) $settings['mapsApiKey'],
    );
    if ($mapsForApp === '') {
        error_log('[vpride] GET /api/v1/config/public: mapsApiKey empty (admin Settings and MAPS_API_KEY / GOOGLE_MAPS_API_KEY in .env); app will show maps placeholder until set.');
    }
    $gServer = (string) $settings['googleWebClientId'];
    $json = json_encode(
        [
            // Server / ID-token audience (Flutter google_sign_in serverClientId). Not a "website" app;
            // Google Cloud still calls this client type "Web application".
            'googleServerClientId' => $gServer,
            'googleWebClientId' => $gServer,
            'mapsApiKey' => $mapsForApp,
            'minimumAppVersion' => $settings['minimumAppVersion'],
            'welcome' => $settings['welcome'],
            'features' => $settings['features'],
            'operations' => $settings['operations'] ?? ['riderCancellationFeeAmount' => 0.0],
            'dispatch' => $settings['dispatch'] ?? [
                'maxAutoDriverAttempts' => 8,
                'maxRiderDriverRejects' => 2,
                'tripConfirmedWhen' => 'driver_accepted',
            ],
        ],
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    );
    // Welcome + feature flags change from admin; avoid long public caching so apps pick up CMS updates quickly.
    HttpCacheJson::emit($json, 0, 0);
} catch (Throwable $e) {
    error_log('[vpride] GET /api/v1/config/public failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
}
