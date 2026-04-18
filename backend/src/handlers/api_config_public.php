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
    $json = json_encode(
        [
            'googleWebClientId' => $settings['googleWebClientId'],
            'mapsApiKey' => $mapsForApp,
            'minimumAppVersion' => $settings['minimumAppVersion'],
            'welcome' => $settings['welcome'],
            'features' => $settings['features'],
            'operations' => $settings['operations'] ?? ['riderCancellationFeeAmount' => 0.0],
        ],
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    );
    HttpCacheJson::emit($json, 120, 300);
} catch (Throwable $e) {
    error_log('[vpride] GET /api/v1/config/public failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
}
