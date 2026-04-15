<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/RegionRepository.php';
require_once $backendRoot . '/src/RegionFormPayload.php';

use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\RegionFormPayload;
use VprideBackend\RegionRepository;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('regions.manage');

$repo = new RegionRepository(Database::pdo());
$admin = Auth::currentAdmin();
$csrf = Auth::csrfToken();

$isNew = ! empty($_ROUTE_REGION_NEW);
$id = $isNew ? 0 : (int) ($_ROUTE_REGION_ID ?? 0);

$label = '';
$error = '';
$message = '';
$model = [];

if ($isNew) {
    $label = 'New configuration';
} else {
    $row = $repo->getConfigRow($id);
    if ($row === null) {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        $pageTitle = 'Not found · VP Ride Console';
        $bodyClass = 'vp-body vp-body--app';
        $vpNavActive = '';
        $vpTopbarTitle = 'Not found';
        require __DIR__ . '/includes/head.php';
        require __DIR__ . '/includes/app_shell_start.php';
        ?>
        <div class="vp-card"><div class="vp-card__pad">
          <h1 class="vp-page-title">Configuration not found</h1>
          <p class="vp-page-desc"><a class="vp-back" href="<?= vp_url('/admin/regions') ?>"><span class="vp-back__arrow">←</span> Back to regions</a></p>
        </div></div>
        <?php
        require __DIR__ . '/includes/app_shell_end.php';
        exit;
    }
    $label = $row['label'];
    try {
        $model = json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        $error = 'Stored configuration is not valid JSON. Contact support or recreate this draft.';
        $model = defaultWorldwideTemplate();
    }
}

if ($isNew && $model === []) {
    $model = defaultWorldwideTemplate();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session.';
    } else {
        $label = trim((string) ($_POST['label'] ?? ''));
        if ($label === '') {
            $error = 'Internal label is required.';
        } else {
            try {
                $payload = RegionFormPayload::buildFromPost($_POST);
                $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                if ($isNew) {
                    $newId = $repo->createDraft($label, $json, $admin[0]);
                    header('Location: ' . Config::url('/admin/region/' . $newId));
                    exit;
                }
                $repo->updateConfig($id, $label, $json, $admin[0]);
                $message = 'Saved. Open Regions and tap “Go live” on this version to publish to apps.';
                $model = $payload;
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            } catch (Throwable $e) {
                $error = 'Could not save: ' . $e->getMessage();
            }
        }
    }
}

$usePost = $_SERVER['REQUEST_METHOD'] === 'POST' && $error !== '';

if ($usePost && isset($_POST['countries']) && is_array($_POST['countries'])) {
    ksort($_POST['countries'], SORT_NUMERIC);
    $countriesForm = $_POST['countries'];
} else {
    $countriesForm = $model['countries'] ?? [];
}

if ($countriesForm === []) {
    $countriesForm = [
        [
            'code' => '',
            'name' => '',
            'currencyCode' => '',
            'distanceUnit' => 'km',
            'cities' => [['id' => '', 'name' => '', 'subdivision' => '', 'latitude' => '', 'longitude' => '', 'isActive' => true]],
        ],
    ];
}

$versionVal = $usePost
    ? max(1, (int) ($_POST['version'] ?? 1))
    : (int) ($model['version'] ?? 1);

$brandVal = $usePost
    ? trim((string) ($_POST['branding']['serviceAreaLabel'] ?? ''))
    : (string) ($model['branding']['serviceAreaLabel'] ?? '');

$defLocaleVal = $usePost
    ? trim((string) ($_POST['localization']['defaultLocale'] ?? 'en_CA'))
    : (string) ($model['localization']['defaultLocale'] ?? 'en_CA');

$extraLocalesVal = '';
if ($usePost) {
    $extraLocalesVal = trim((string) ($_POST['localization']['extraLocales'] ?? ''));
} else {
    $known = array_flip(RegionFormPayload::KNOWN_LOCALES);
    $extra = [];
    foreach ($model['localization']['supportedLocales'] ?? [] as $s) {
        if (! isset($known[$s])) {
            $extra[] = $s;
        }
    }
    $extraLocalesVal = implode(', ', $extra);
}

$defCountryVal = $usePost
    ? strtoupper(trim((string) ($_POST['defaults']['countryCode'] ?? '')))
    : (string) ($model['defaults']['countryCode'] ?? '');

$defCityVal = $usePost
    ? trim((string) ($_POST['defaults']['cityId'] ?? ''))
    : (string) ($model['defaults']['cityId'] ?? '');

$localeOptions = array_values(array_unique(array_merge(
    RegionFormPayload::KNOWN_LOCALES,
    $model['localization']['supportedLocales'] ?? [],
    [$defLocaleVal],
)));

/**
 * @return array<string, mixed>
 */
function defaultWorldwideTemplate(): array
{
    return [
        'version' => 1,
        'updatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
        'branding' => [
            'serviceAreaLabel' => 'Modern Canada',
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

/**
 * @param  array<string, mixed>  $post
 */
function region_locale_checked(array $post, array $model, bool $usePost, string $code): bool
{
    if ($usePost) {
        return ! empty($post['localization']['loc'][$code]);
    }

    return in_array($code, $model['localization']['supportedLocales'] ?? [], true);
}

header('Content-Type: text/html; charset=utf-8');
$pageTitle = ($isNew ? 'New region' : 'Edit #' . $id) . ' · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = $isNew ? 'region_new' : 'regions';
$vpTopbarTitle = $isNew ? 'New draft' : 'Edit configuration';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<header class="vp-page-hero vp-page-hero--editor">
  <a class="vp-back" href="<?= vp_url('/admin/regions') ?>"><span class="vp-back__arrow">←</span> Regions</a>
  <h1 class="vp-page-title"><?= $isNew ? 'New configuration' : 'Edit configuration' ?></h1>
  <p class="vp-page-desc">
    Structured editor — no raw JSON required. Changes go live only after you <strong>Go live</strong> on the regions list.
    <?php if (! $isNew) { ?>
      <span class="vp-page-desc__id">#<?= (int) $id ?></span>
    <?php } ?>
  </p>
</header>

<?php if ($error !== '') { ?>
  <div class="vp-alert vp-alert--error" role="alert"><?= vp_h($error) ?></div>
<?php } ?>
<?php if ($message !== '') { ?>
  <div class="vp-alert vp-alert--success" role="status"><?= vp_h($message) ?></div>
<?php } ?>

<form method="post" action="<?= vp_h(vp_url($isNew ? '/admin/region/new' : '/admin/region/' . $id)) ?>" class="vp-region-form">
  <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">

  <section class="vp-card vp-card--region">
    <div class="vp-card__pad">
      <h2 class="vp-section-title">Basics</h2>
      <div class="vp-field-grid">
        <div class="vp-field">
          <label class="vp-label" for="label">Internal label</label>
          <input class="vp-input" id="label" type="text" name="label" value="<?= vp_h($label) ?>" required placeholder="e.g. Canada production">
        </div>
        <div class="vp-field vp-field--narrow">
          <label class="vp-label" for="version">Config version (number)</label>
          <input class="vp-input" id="version" type="number" name="version" min="1" step="1" value="<?= (int) $versionVal ?>" required>
        </div>
      </div>
      <div class="vp-field">
        <label class="vp-label" for="serviceAreaLabel">Service area label (shown in apps)</label>
        <input class="vp-input" id="serviceAreaLabel" type="text" name="branding[serviceAreaLabel]" value="<?= vp_h($brandVal) ?>" required placeholder="e.g. Modern Canada">
      </div>
    </div>
  </section>

  <section class="vp-card vp-card--region">
    <div class="vp-card__pad">
      <h2 class="vp-section-title">Languages</h2>
      <div class="vp-field">
        <label class="vp-label" for="defaultLocale">Default locale</label>
        <select class="vp-input" id="defaultLocale" name="localization[defaultLocale]">
          <?php foreach ($localeOptions as $loc) {
              if ($loc === '') {
                  continue;
              } ?>
            <option value="<?= vp_h($loc) ?>" <?= $defLocaleVal === $loc ? ' selected' : '' ?>><?= vp_h($loc) ?></option>
          <?php } ?>
        </select>
      </div>
      <p class="vp-muted-label">Supported locales</p>
      <div class="vp-locale-grid">
        <?php foreach (RegionFormPayload::KNOWN_LOCALES as $loc) { ?>
          <label class="vp-check">
            <input type="checkbox" name="localization[loc][<?= vp_h($loc) ?>]" value="1" <?= region_locale_checked($_POST, $model, $usePost, $loc) ? ' checked' : '' ?>>
            <?= vp_h($loc) ?>
          </label>
        <?php } ?>
      </div>
      <div class="vp-field">
        <label class="vp-label" for="extraLocales">Extra locales (optional, comma-separated)</label>
        <input class="vp-input" id="extraLocales" type="text" name="localization[extraLocales]" value="<?= vp_h($extraLocalesVal) ?>" placeholder="e.g. de_DE, ar_SA">
      </div>
    </div>
  </section>

  <section class="vp-card vp-card--region">
    <div class="vp-card__pad">
      <div class="vp-region-toolbar">
        <h2 class="vp-section-title" style="margin:0;">Countries &amp; cities</h2>
        <button type="button" class="vp-btn vp-btn--ghost vp-btn--sm" id="add-country">+ Add country</button>
      </div>
      <p class="vp-hint" style="margin-top:0.5rem;">Map center uses latitude / longitude (decimal numbers). Distance unit is <strong>km</strong> or <strong>mi</strong>.</p>

      <div id="countries-root">
        <?php
        $ci = 0;
        foreach ($countriesForm as $c) {
            if (! is_array($c)) {
                continue;
            }
            $citiesRows = $c['cities'] ?? [];
            if (! is_array($citiesRows) || $citiesRows === []) {
                $citiesRows = [['id' => '', 'name' => '', 'subdivision' => '', 'latitude' => '', 'longitude' => '', 'isActive' => true]];
            }
            ?>
        <div class="vp-country-block vp-card vp-card--nested" data-country-index="<?= (int) $ci ?>">
          <div class="vp-card__pad">
            <div class="vp-country-head">
              <strong class="vp-country-title">Country</strong>
              <?php if (count($countriesForm) > 1) { ?>
                <button type="button" class="vp-btn vp-btn--danger-ghost vp-btn--sm vp-remove-country">Remove country</button>
              <?php } ?>
            </div>
            <div class="vp-field-grid vp-field-grid--4">
              <div class="vp-field">
                <label class="vp-label">Code (ISO2)</label>
                <input class="vp-input" type="text" name="countries[<?= (int) $ci ?>][code]" maxlength="2" value="<?= vp_h(strtoupper((string) ($c['code'] ?? ''))) ?>" required placeholder="CA">
              </div>
              <div class="vp-field">
                <label class="vp-label">Name</label>
                <input class="vp-input" type="text" name="countries[<?= (int) $ci ?>][name]" value="<?= vp_h((string) ($c['name'] ?? '')) ?>" required placeholder="Canada">
              </div>
              <div class="vp-field">
                <label class="vp-label">Currency (ISO 3)</label>
                <input class="vp-input" type="text" name="countries[<?= (int) $ci ?>][currencyCode]" maxlength="3" value="<?= vp_h(strtoupper((string) ($c['currencyCode'] ?? ''))) ?>" required placeholder="CAD">
              </div>
              <div class="vp-field">
                <label class="vp-label">Distance</label>
                <select class="vp-input" name="countries[<?= (int) $ci ?>][distanceUnit]">
                  <?php $du = strtolower((string) ($c['distanceUnit'] ?? 'km')); ?>
                  <option value="km" <?= $du === 'km' ? ' selected' : '' ?>>km</option>
                  <option value="mi" <?= $du === 'mi' ? ' selected' : '' ?>>mi</option>
                </select>
              </div>
            </div>
            <p class="vp-muted-label">Cities</p>
            <div class="vp-cities-list">
              <?php
                $ki = 0;
            foreach ($citiesRows as $ct) {
                if (! is_array($ct)) {
                    continue;
                }
                $lat = $ct['center']['latitude'] ?? ($ct['latitude'] ?? '');
                $lng = $ct['center']['longitude'] ?? ($ct['longitude'] ?? '');
                ?>
              <div class="vp-city-row vp-card vp-card--nested">
                <div class="vp-card__pad vp-city-grid">
                  <div class="vp-field">
                    <label class="vp-label">City ID</label>
                    <input class="vp-input" type="text" name="countries[<?= (int) $ci ?>][cities][<?= (int) $ki ?>][id]" value="<?= vp_h((string) ($ct['id'] ?? '')) ?>" required placeholder="yyz">
                  </div>
                  <div class="vp-field">
                    <label class="vp-label">Name</label>
                    <input class="vp-input" type="text" name="countries[<?= (int) $ci ?>][cities][<?= (int) $ki ?>][name]" value="<?= vp_h((string) ($ct['name'] ?? '')) ?>" required>
                  </div>
                  <div class="vp-field">
                    <label class="vp-label">Region / state</label>
                    <input class="vp-input" type="text" name="countries[<?= (int) $ci ?>][cities][<?= (int) $ki ?>][subdivision]" value="<?= vp_h((string) ($ct['subdivision'] ?? '')) ?>" placeholder="ON">
                  </div>
                  <div class="vp-field">
                    <label class="vp-label">Latitude</label>
                    <input class="vp-input" type="number" step="any" name="countries[<?= (int) $ci ?>][cities][<?= (int) $ki ?>][latitude]" value="<?= vp_h((string) $lat) ?>" required>
                  </div>
                  <div class="vp-field">
                    <label class="vp-label">Longitude</label>
                    <input class="vp-input" type="number" step="any" name="countries[<?= (int) $ci ?>][cities][<?= (int) $ki ?>][longitude]" value="<?= vp_h((string) $lng) ?>" required>
                  </div>
                  <div class="vp-field vp-field--check">
                    <label class="vp-check">
                      <input type="hidden" name="countries[<?= (int) $ci ?>][cities][<?= (int) $ki ?>][isActive]" value="0">
                      <input type="checkbox" name="countries[<?= (int) $ci ?>][cities][<?= (int) $ki ?>][isActive]" value="1" <?= ! empty($ct['isActive']) ? ' checked' : '' ?>>
                      Active
                    </label>
                  </div>
                  <div class="vp-field vp-field--row-actions">
                    <?php if (count($citiesRows) > 1) { ?>
                      <button type="button" class="vp-btn vp-btn--danger-ghost vp-btn--sm vp-remove-city">Remove city</button>
                    <?php } ?>
                  </div>
                </div>
              </div>
              <?php
                ++$ki;
            }
            ?>
            </div>
            <button type="button" class="vp-btn vp-btn--inline vp-btn--sm vp-add-city">+ Add city</button>
          </div>
        </div>
        <?php
            ++$ci;
        }
        ?>
      </div>
    </div>
  </section>

  <section class="vp-card vp-card--region">
    <div class="vp-card__pad">
      <h2 class="vp-section-title">Default for new sessions</h2>
      <div class="vp-field-grid">
        <div class="vp-field">
          <label class="vp-label" for="defaults_country">Default country code</label>
          <input class="vp-input" id="defaults_country" type="text" name="defaults[countryCode]" maxlength="2" value="<?= vp_h($defCountryVal) ?>" required placeholder="CA">
        </div>
        <div class="vp-field">
          <label class="vp-label" for="defaults_city">Default city ID</label>
          <select class="vp-input" id="defaults_city" name="defaults[cityId]">
            <?php foreach ($countriesForm as $c) {
                if (! is_array($c)) {
                    continue;
                }
                $ccode = strtoupper((string) ($c['code'] ?? ''));
                $citiesRows = $c['cities'] ?? [];
                if (! is_array($citiesRows)) {
                    continue;
                }
                foreach ($citiesRows as $ct) {
                    if (! is_array($ct)) {
                        continue;
                    }
                    $cid = (string) ($ct['id'] ?? '');
                    if ($cid === '') {
                        continue;
                    }
                    $cname = (string) ($ct['name'] ?? $cid);
                    $sel = ($defCityVal === $cid) ? ' selected' : '';
                    echo '<option value="' . vp_h($cid) . '"' . $sel . '>' . vp_h("{$cname} · {$ccode} · {$cid}") . '</option>';
                }
            } ?>
          </select>
        </div>
      </div>
    </div>
  </section>

  <div class="vp-form-actions vp-form-actions--flush">
    <button type="submit" class="vp-btn vp-btn--primary"><?= $isNew ? 'Create draft' : 'Save changes' ?></button>
    <a class="vp-btn vp-btn--ghost" href="<?= vp_url('/admin/regions') ?>">Cancel</a>
  </div>
</form>

<div id="vp-country-prototype" style="display:none" aria-hidden="true">
<div class="vp-country-block vp-card vp-card--nested" data-country-index="__C__">
  <div class="vp-card__pad">
    <div class="vp-country-head">
      <strong class="vp-country-title">Country</strong>
      <button type="button" class="vp-btn vp-btn--danger-ghost vp-btn--sm vp-remove-country">Remove country</button>
    </div>
    <div class="vp-field-grid vp-field-grid--4">
      <div class="vp-field">
        <label class="vp-label">Code (ISO 2)</label>
        <input class="vp-input" type="text" name="countries[__C__][code]" maxlength="2" required placeholder="CA">
      </div>
      <div class="vp-field">
        <label class="vp-label">Name</label>
        <input class="vp-input" type="text" name="countries[__C__][name]" required>
      </div>
      <div class="vp-field">
        <label class="vp-label">Currency (ISO 3)</label>
        <input class="vp-input" type="text" name="countries[__C__][currencyCode]" maxlength="3" required placeholder="CAD">
      </div>
      <div class="vp-field">
        <label class="vp-label">Distance</label>
        <select class="vp-input" name="countries[__C__][distanceUnit]">
          <option value="km" selected>km</option>
          <option value="mi">mi</option>
        </select>
      </div>
    </div>
    <p class="vp-muted-label">Cities</p>
    <div class="vp-cities-list">
      <div class="vp-city-row vp-card vp-card--nested">
        <div class="vp-card__pad vp-city-grid">
          <div class="vp-field">
            <label class="vp-label">City ID</label>
            <input class="vp-input" type="text" name="countries[__C__][cities][0][id]" required>
          </div>
          <div class="vp-field">
            <label class="vp-label">Name</label>
            <input class="vp-input" type="text" name="countries[__C__][cities][0][name]" required>
          </div>
          <div class="vp-field">
            <label class="vp-label">Region / state</label>
            <input class="vp-input" type="text" name="countries[__C__][cities][0][subdivision]">
          </div>
          <div class="vp-field">
            <label class="vp-label">Latitude</label>
            <input class="vp-input" type="number" step="any" name="countries[__C__][cities][0][latitude]" required>
          </div>
          <div class="vp-field">
            <label class="vp-label">Longitude</label>
            <input class="vp-input" type="number" step="any" name="countries[__C__][cities][0][longitude]" required>
          </div>
          <div class="vp-field vp-field--check">
            <label class="vp-check">
              <input type="hidden" name="countries[__C__][cities][0][isActive]" value="0">
              <input type="checkbox" name="countries[__C__][cities][0][isActive]" value="1" checked>
              Active
            </label>
          </div>
          <div class="vp-field vp-field--row-actions"></div>
        </div>
      </div>
    </div>
    <button type="button" class="vp-btn vp-btn--inline vp-btn--sm vp-add-city">+ Add city</button>
  </div>
</div>
</div>

<div id="vp-city-prototype" style="display:none" aria-hidden="true">
<div class="vp-city-row vp-card vp-card--nested">
  <div class="vp-card__pad vp-city-grid">
    <div class="vp-field">
      <label class="vp-label">City ID</label>
      <input class="vp-input" type="text" name="countries[__C__][cities][__K__][id]" required>
    </div>
    <div class="vp-field">
      <label class="vp-label">Name</label>
      <input class="vp-input" type="text" name="countries[__C__][cities][__K__][name]" required>
    </div>
    <div class="vp-field">
      <label class="vp-label">Region / state</label>
      <input class="vp-input" type="text" name="countries[__C__][cities][__K__][subdivision]">
    </div>
    <div class="vp-field">
      <label class="vp-label">Latitude</label>
      <input class="vp-input" type="number" step="any" name="countries[__C__][cities][__K__][latitude]" required>
    </div>
    <div class="vp-field">
      <label class="vp-label">Longitude</label>
      <input class="vp-input" type="number" step="any" name="countries[__C__][cities][__K__][longitude]" required>
    </div>
    <div class="vp-field vp-field--check">
      <label class="vp-check">
        <input type="hidden" name="countries[__C__][cities][__K__][isActive]" value="0">
        <input type="checkbox" name="countries[__C__][cities][__K__][isActive]" value="1" checked>
        Active
      </label>
    </div>
    <div class="vp-field vp-field--row-actions">
      <button type="button" class="vp-btn vp-btn--danger-ghost vp-btn--sm vp-remove-city">Remove city</button>
    </div>
  </div>
</div>
</div>

<script defer src="<?= vp_url('/admin/assets/region_form.js') ?>"></script>
<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
