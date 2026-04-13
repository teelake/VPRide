<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/RegionRepository.php';

use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\RegionRepository;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requireSystemAdmin();

$repo = new RegionRepository(Database::pdo());
$admin = Auth::currentAdmin();
$csrf = Auth::csrfToken();

$isNew = ! empty($_ROUTE_REGION_NEW);
$id = $isNew ? 0 : (int) ($_ROUTE_REGION_ID ?? 0);

$label = '';
$jsonText = '';
$error = '';
$message = '';

if ($isNew) {
    $label = 'New configuration';
    $jsonText = json_encode(defaultWorldwideTemplate(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    $row = $repo->getConfigRow($id);
    if ($row === null) {
        http_response_code(404);
        echo 'Config not found';
        exit;
    }
    $label = $row['label'];
    $jsonText = $row['payload'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session.';
    } else {
        $label = trim((string) ($_POST['label'] ?? ''));
        $jsonText = (string) ($_POST['payload'] ?? '');
        if ($label === '') {
            $error = 'Label required.';
        } else {
            try {
                if ($isNew) {
                    $newId = $repo->createDraft($label, $jsonText, $admin[0]);
                    header('Location: /admin/region/' . $newId);
                    exit;
                }
                $repo->updateConfig($id, $label, $jsonText, $admin[0]);
                $message = 'Saved. Activate this version on the dashboard to push it to apps.';
            } catch (Throwable $e) {
                $error = 'Invalid JSON or database error: ' . $e->getMessage();
            }
        }
    }
}

/**
 * @return array<string, mixed>
 */
function defaultWorldwideTemplate(): array
{
    return [
        'version' => 1,
        'updatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
        'branding' => [
            'serviceAreaLabel' => 'Worldwide',
        ],
        'localization' => [
            'defaultLocale' => 'en_CA',
            'supportedLocales' => ['en_CA', 'fr_CA', 'en_US', 'en_NG'],
        ],
        'countries' => [
            [
                'code' => 'CA',
                'name' => 'Canada',
                'currencyCode' => 'CAD',
                'distanceUnit' => 'km',
                'cities' => [
                    [
                        'id' => 'yyz',
                        'name' => 'Toronto',
                        'subdivision' => 'ON',
                        'isActive' => true,
                        'center' => ['latitude' => 43.6532, 'longitude' => -79.3832],
                    ],
                ],
            ],
            [
                'code' => 'NG',
                'name' => 'Nigeria',
                'currencyCode' => 'NGN',
                'distanceUnit' => 'km',
                'cities' => [
                    [
                        'id' => 'los',
                        'name' => 'Lagos',
                        'subdivision' => 'LA',
                        'isActive' => true,
                        'center' => ['latitude' => 6.5244, 'longitude' => 3.3792],
                    ],
                ],
            ],
        ],
        'defaults' => [
            'countryCode' => 'CA',
            'cityId' => 'yyz',
        ],
    ];
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit region config</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 960px; margin: 2rem auto; padding: 0 1rem; }
    label { display: block; font-weight: 600; margin-top: 1rem; }
    input[type=text] { width: 100%; padding: 0.5rem; box-sizing: border-box; }
    textarea { width: 100%; min-height: 420px; font-family: ui-monospace, monospace; font-size: 13px; padding: 0.5rem; box-sizing: border-box; }
    .row { margin-top: 1rem; display: flex; gap: 1rem; align-items: center; }
    .err { color: #b00020; }
    .msg { color: #0a0; }
  </style>
</head>
<body>
  <h1><?= $isNew ? 'New region config' : 'Edit region config #' . $id ?></h1>
  <p><a href="/admin/dashboard">← Back to dashboard</a></p>
  <?php if ($error !== '') { ?><p class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php } ?>
  <?php if ($message !== '') { ?><p class="msg"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php } ?>

  <form method="post" action="<?= htmlspecialchars($isNew ? '/admin/region/new' : '/admin/region/' . $id, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <label>Label (internal)
      <input type="text" name="label" value="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>" required>
    </label>
    <label>Payload JSON (must match app contract: countries, defaults, branding, localization)
      <textarea name="payload" required><?= htmlspecialchars($jsonText, ENT_QUOTES, 'UTF-8') ?></textarea>
    </label>
    <div class="row">
      <button type="submit"><?= $isNew ? 'Create draft' : 'Save' ?></button>
    </div>
  </form>
</body>
</html>
