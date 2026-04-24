<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/LegalPageRepository.php';
require_once $backendRoot . '/src/HttpCacheJson.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\Database;
use VprideBackend\HttpCacheJson;
use VprideBackend\LegalPageRepository;

Config::load($backendRoot . '/.env');

ApiMobileCors::sendPreflightIfOptions();
ApiMobileCors::headers();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed'], JSON_THROW_ON_ERROR);
    exit;
}

$slug = $GLOBALS['vpride_legal_page_slug'] ?? '';
$slug = is_string($slug) ? trim($slug) : '';

if (! in_array($slug, LegalPageRepository::allowedSlugs(), true)) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found'], JSON_THROW_ON_ERROR);
    exit;
}

try {
    $row = (new LegalPageRepository(Database::pdo()))->getBySlug($slug);
    if ($row === null) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found'], JSON_THROW_ON_ERROR);
        exit;
    }
    $json = json_encode(
        [
            'slug' => $row['slug'],
            'title' => $row['title'],
            'html' => $row['body_html'],
            'updatedAt' => $row['updated_at'],
        ],
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    );
    HttpCacheJson::emit($json, 60, 120);
} catch (Throwable $e) {
    error_log('[vpride] GET /api/v1/legal-pages failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error'], JSON_THROW_ON_ERROR);
}
