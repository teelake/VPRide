<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/FleetVehicleRepository.php';

use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\FleetVehicleRepository;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('fleet.manage');

$repo = new FleetVehicleRepository(Database::pdo());
$csrf = Auth::csrfToken();

$isNew = ! empty($_ROUTE_FLEET_VEHICLE_NEW);
$id = $isNew ? 0 : (int) ($_ROUTE_FLEET_VEHICLE_ID ?? 0);

$ownership = 'personal';
$companyFleetLabel = '';
$plate = '';
$make = '';
$model = '';
$color = '';
$year = '';
$seatCount = '';
$vin = '';
$status = 'active';
$notes = '';

$error = '';
$message = '';

if (! $isNew) {
    $row = $repo->findById($id);
    if ($row === null) {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        $pageTitle = 'Not found · VP Ride Console';
        $bodyClass = 'vp-body vp-body--app';
        $vpNavActive = 'fleet';
        $vpTopbarTitle = 'Not found';
        require __DIR__ . '/includes/head.php';
        require __DIR__ . '/includes/app_shell_start.php';
        ?>
        <div class="vp-card"><div class="vp-card__pad">
          <?php
            vp_breadcrumbs([
                ['label' => 'Car management', 'href' => vp_url('/admin/fleet')],
                ['label' => 'Not found', 'href' => null],
            ]);
        ?>
          <h1 class="vp-page-title">Vehicle not found</h1>
          <p class="vp-page-desc"><a class="vp-back" href="<?= vp_url('/admin/fleet') ?>"><span class="vp-back__arrow">←</span> Back to fleet</a></p>
        </div></div>
        <?php
        require __DIR__ . '/includes/app_shell_end.php';
        exit;
    }
    $ownership = (string) $row['ownership'];
    $companyFleetLabel = (string) ($row['company_fleet_label'] ?? '');
    $plate = (string) $row['plate_number'];
    $make = (string) ($row['make'] ?? '');
    $model = (string) ($row['model'] ?? '');
    $color = (string) ($row['color'] ?? '');
    $year = isset($row['year']) && $row['year'] !== null ? (string) (int) $row['year'] : '';
    $seatCount = isset($row['seat_count']) && $row['seat_count'] !== null ? (string) (int) $row['seat_count'] : '';
    $vin = (string) ($row['vin'] ?? '');
    $status = (string) $row['status'];
    $notes = (string) ($row['notes'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token. Refresh and try again.';
    } else {
        $ownership = trim((string) ($_POST['ownership'] ?? 'personal'));
        $companyFleetLabel = trim((string) ($_POST['company_fleet_label'] ?? ''));
        $plate = trim((string) ($_POST['plate_number'] ?? ''));
        $make = trim((string) ($_POST['make'] ?? ''));
        $model = trim((string) ($_POST['model'] ?? ''));
        $color = trim((string) ($_POST['color'] ?? ''));
        $year = trim((string) ($_POST['year'] ?? ''));
        $seatCount = trim((string) ($_POST['seat_count'] ?? ''));
        $vin = trim((string) ($_POST['vin'] ?? ''));
        $status = trim((string) ($_POST['status'] ?? 'active'));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $payload = [
            'ownership' => $ownership,
            'company_fleet_label' => $companyFleetLabel,
            'plate_number' => $plate,
            'make' => $make,
            'model' => $model,
            'color' => $color,
            'year' => $year === '' ? null : $year,
            'seat_count' => $seatCount === '' ? null : $seatCount,
            'vin' => $vin,
            'status' => $status,
            'notes' => $notes,
        ];
        try {
            if ($isNew) {
                $newId = $repo->insert($payload);
                header('Location: ' . Config::url('/admin/fleet/' . $newId));
                exit;
            }
            $repo->update($id, $payload);
            $message = 'Vehicle saved.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$titleBit = $isNew ? 'New vehicle' : 'Edit vehicle #' . $id;
header('Content-Type: text/html; charset=utf-8');
$pageTitle = $titleBit . ' · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'fleet';
$vpTopbarTitle = $isNew ? 'New vehicle' : 'Edit vehicle';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<header class="vp-page-hero">
  <?php
    vp_breadcrumbs([
        ['label' => 'Car management', 'href' => vp_url('/admin/fleet')],
        ['label' => $titleBit, 'href' => null],
    ]);
?>
  <h1 class="vp-page-title"><?= vp_h($titleBit) ?></h1>
  <p class="vp-page-desc"><strong>Personal</strong> rows are for owner-operators’ own cars. <strong>Company</strong> rows are for brand / fleet vehicles driven by company drivers.</p>
</header>

<?php if ($message !== '') { ?>
  <div class="vp-alert vp-alert--success" role="status"><?= vp_h($message) ?></div>
<?php } ?>
<?php if ($error !== '') { ?>
  <div class="vp-alert vp-alert--error" role="alert"><?= vp_h($error) ?></div>
<?php } ?>

<section class="vp-card">
  <div class="vp-card__pad">
    <form method="post" action="<?= vp_h(vp_url($isNew ? '/admin/fleet/new' : '/admin/fleet/' . $id)) ?>" class="vp-stack-form">
      <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
      <div class="vp-field">
        <label class="vp-label" for="ownership">Vehicle ownership</label>
        <select class="vp-input" id="ownership" name="ownership" required>
          <option value="personal"<?= $ownership === 'personal' ? ' selected' : '' ?>>Personal (driver’s own car)</option>
          <option value="company"<?= $ownership === 'company' ? ' selected' : '' ?>>Company / brand fleet</option>
        </select>
      </div>
      <div class="vp-field" id="company-label-wrap">
        <label class="vp-label" for="company_fleet_label">Brand / fleet line</label>
        <input class="vp-input" id="company_fleet_label" name="company_fleet_label" type="text" value="<?= vp_h($companyFleetLabel) ?>" maxlength="128" placeholder="e.g. VP Ride Executive" autocomplete="off">
        <p class="vp-field-hint">Required for company vehicles (shown in the admin list).</p>
      </div>
      <div class="vp-field">
        <label class="vp-label" for="plate_number">Plate / registration</label>
        <input class="vp-input vp-input--mono" id="plate_number" name="plate_number" type="text" value="<?= vp_h($plate) ?>" maxlength="32" required autocomplete="off">
      </div>
      <div class="vp-field" style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
        <div>
          <label class="vp-label" for="make">Make</label>
          <input class="vp-input" id="make" name="make" type="text" value="<?= vp_h($make) ?>" maxlength="64" autocomplete="off">
        </div>
        <div>
          <label class="vp-label" for="model">Model</label>
          <input class="vp-input" id="model" name="model" type="text" value="<?= vp_h($model) ?>" maxlength="64" autocomplete="off">
        </div>
      </div>
      <div class="vp-field" style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem;">
        <div>
          <label class="vp-label" for="color">Color</label>
          <input class="vp-input" id="color" name="color" type="text" value="<?= vp_h($color) ?>" maxlength="48" autocomplete="off">
        </div>
        <div>
          <label class="vp-label" for="year">Year</label>
          <input class="vp-input" id="year" name="year" type="number" min="1900" max="2100" value="<?= vp_h($year) ?>" placeholder="Optional" autocomplete="off">
        </div>
        <div>
          <label class="vp-label" for="seat_count">Seats</label>
          <input class="vp-input" id="seat_count" name="seat_count" type="number" min="1" max="60" value="<?= vp_h($seatCount) ?>" placeholder="Optional" autocomplete="off">
        </div>
      </div>
      <div class="vp-field">
        <label class="vp-label" for="vin">VIN (optional)</label>
        <input class="vp-input vp-input--mono" id="vin" name="vin" type="text" value="<?= vp_h($vin) ?>" maxlength="32" autocomplete="off">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="status">Status</label>
        <select class="vp-input" id="status" name="status" required>
          <option value="active"<?= $status === 'active' ? ' selected' : '' ?>>Active</option>
          <option value="maintenance"<?= $status === 'maintenance' ? ' selected' : '' ?>>Maintenance</option>
          <option value="retired"<?= $status === 'retired' ? ' selected' : '' ?>>Retired</option>
        </select>
      </div>
      <div class="vp-field">
        <label class="vp-label" for="notes">Internal notes</label>
        <textarea class="vp-input" id="notes" name="notes" rows="3" maxlength="512" autocomplete="off"><?= vp_h($notes) ?></textarea>
      </div>
      <div class="vp-toolbar">
        <button type="submit" class="vp-btn vp-btn--primary">Save</button>
        <a class="vp-btn vp-btn--ghost" href="<?= vp_url('/admin/fleet') ?>">Cancel</a>
      </div>
    </form>
  </div>
</section>

<script>
(function () {
  var sel = document.getElementById('ownership');
  var wrap = document.getElementById('company-label-wrap');
  var input = document.getElementById('company_fleet_label');
  function sync() {
    if (!sel || !wrap) return;
    var isCo = sel.value === 'company';
    wrap.style.display = isCo ? '' : 'none';
    if (!isCo && input) input.removeAttribute('required');
    if (isCo && input) input.setAttribute('required', 'required');
  }
  if (sel) sel.addEventListener('change', sync);
  sync();
})();
</script>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
