<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/AppSettingsRepository.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\AppSettingsRepository;
use VprideBackend\Config;
use VprideBackend\Database;

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
    echo json_encode(
        [
            'googleWebClientId' => $settings['googleWebClientId'],
            'mapsApiKey' => $settings['mapsApiKey'],
            'minimumAppVersion' => $settings['minimumAppVersion'],
            'features' => $settings['features'],
        ],
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    );
} catch (Throwable) {
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
}
