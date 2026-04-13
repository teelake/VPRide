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
        header('Content-Type: text/html; charset=utf-8');
        $pageTitle = 'Not found · VPRide Console';
        $bodyClass = 'vp-body vp-body--app';
        require __DIR__ . '/includes/head.php';
        require __DIR__ . '/includes/app_shell_start.php';
        ?>
        <div class="vp-card"><div class="vp-card__pad">
          <h1 class="vp-page-title">Configuration not found</h1>
          <p class="vp-page-desc"><a class="vp-back" href="/admin/dashboard"><span class="vp-back__arrow">←</span> Back to regions</a></p>
        </div></div>
        <?php
        require __DIR__ . '/includes/app_shell_end.php';
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
                $message = 'Saved. Open Regions and tap “Go live” on this version to publish to apps.';
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
$pageTitle = ($isNew ? 'New region' : 'Edit #' . $id) . ' · VPRide Console';
$bodyClass = 'vp-body vp-body--app';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<a class="vp-back" href="/admin/dashboard"><span class="vp-back__arrow">←</span> Regions</a>

<h1 class="vp-page-title"><?= $isNew ? 'New configuration' : 'Edit configuration' ?></h1>
<p class="vp-hint">
  <?= $isNew ? 'Start from the template and adjust countries, cities, defaults, and branding. Valid JSON is required.' : 'Update the JSON payload. Changes are not live until you activate this version on the dashboard.' ?>
  <?php if (! $isNew) { ?>
    <strong>#<?= (int) $id ?></strong>
  <?php } ?>
</p>

<?php if ($error !== '') { ?>
  <div class="vp-alert vp-alert--error" role="alert"><?= vp_h($error) ?></div>
<?php } ?>
<?php if ($message !== '') { ?>
  <div class="vp-alert vp-alert--success" role="status"><?= vp_h($message) ?></div>
<?php } ?>

<section class="vp-card">
  <div class="vp-card__pad">
    <form method="post" action="<?= vp_h($isNew ? '/admin/region/new' : '/admin/region/' . $id) ?>">
      <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
      <div class="vp-field">
        <label class="vp-label" for="label">Internal label</label>
        <input class="vp-input" id="label" type="text" name="label" value="<?= vp_h($label) ?>" required placeholder="e.g. Canada production / Nigeria QA">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="payload">Payload JSON</label>
        <textarea class="vp-textarea" id="payload" name="payload" required spellcheck="false"><?= vp_h($jsonText) ?></textarea>
      </div>
      <div class="vp-form-actions">
        <button type="submit" class="vp-btn vp-btn--primary"><?= $isNew ? 'Create draft' : 'Save changes' ?></button>
        <a class="vp-btn vp-btn--ghost" href="/admin/dashboard">Cancel</a>
      </div>
    </form>
  </div>
</section>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
