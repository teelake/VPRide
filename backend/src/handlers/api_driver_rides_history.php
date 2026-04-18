<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/ApiMobileCors.php';
require_once $backendRoot . '/src/DriverApiContext.php';
require_once $backendRoot . '/src/RideRepository.php';
require_once $backendRoot . '/src/RideJsonPresenter.php';

use VprideBackend\ApiMobileCors;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\DriverApiContext;
use VprideBackend\RideJsonPresenter;
use VprideBackend\RideRepository;

Config::load($backendRoot . '/.env');

ApiMobileCors::sendPreflightIfOptions();
ApiMobileCors::headers();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed'], JSON_THROW_ON_ERROR);
    exit;
}

$pdo = Database::pdo();
$ctx = DriverApiContext::requireFleetDriver($pdo);
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 60;
$limit = max(1, min(100, $limit));
$beforeId = isset($_GET['before_id']) ? (int) $_GET['before_id'] : 0;
$beforeId = $beforeId > 0 ? $beforeId : null;
$rows = (new RideRepository($pdo))->listHistoryForDriver($ctx['riderUserId'], $limit, $beforeId);
$rides = [];
foreach ($rows as $row) {
    $rides[] = RideJsonPresenter::toPublicArray($row);
}
$nextBeforeId = null;
if (count($rows) === $limit && $rows !== []) {
    $last = $rows[array_key_last($rows)];
    $nextBeforeId = isset($last['id']) ? (int) $last['id'] : null;
}
echo json_encode(
    [
        'rides' => $rides,
        'nextBeforeId' => $nextBeforeId,
    ],
    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
);
