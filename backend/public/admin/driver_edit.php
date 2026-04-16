<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/FleetVehicleRepository.php';
require_once $backendRoot . '/src/ConsoleDriverRepository.php';

use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\ConsoleDriverRepository;
use VprideBackend\Database;
use VprideBackend\FleetVehicleRepository;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('fleet.manage');

$admin = Auth::currentAdmin();
$pdo = Database::pdo();
$vehicleRepo = new FleetVehicleRepository($pdo);
$repo = new ConsoleDriverRepository($pdo, $vehicleRepo);
$csrf = Auth::csrfToken();

$isNew = ! empty($_ROUTE_DRIVER_NEW);
$id = $isNew ? 0 : (int) ($_ROUTE_DRIVER_ID ?? 0);

$fullName = '';
$driverKind = 'owner_operator';
$phone = '';
$email = '';
$fleetVehicleId = 0;
$licenseNumber = '';
$status = 'pending';
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
        $vpNavActive = 'drivers';
        $vpTopbarTitle = 'Not found';
        require __DIR__ . '/includes/head.php';
        require __DIR__ . '/includes/app_shell_start.php';
        ?>
        <div class="vp-card"><div class="vp-card__pad">
          <?php
            vp_breadcrumbs([
                ['label' => 'Drivers', 'href' => vp_url('/admin/drivers')],
                ['label' => 'Not found', 'href' => null],
            ]);
        ?>
          <h1 class="vp-page-title">Driver not found</h1>
          <p class="vp-page-desc"><a class="vp-back" href="<?= vp_url('/admin/drivers') ?>"><span class="vp-back__arrow">←</span> Back to drivers</a></p>
        </div></div>
        <?php
        require __DIR__ . '/includes/app_shell_end.php';
        exit;
    }
    $fullName = (string) $row['full_name'];
    $driverKind = (string) $row['driver_kind'];
    $phone = (string) ($row['phone'] ?? '');
    $email = (string) ($row['email'] ?? '');
    $fleetVehicleId = isset($row['fleet_vehicle_id']) && $row['fleet_vehicle_id'] !== null
        ? (int) $row['fleet_vehicle_id']
        : 0;
    $licenseNumber = (string) ($row['license_number'] ?? '');
    $status = (string) $row['status'];
    $notes = (string) ($row['notes'] ?? '');
}

$vehicles = $vehicleRepo->listActiveForSelect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token. Refresh and try again.';
    } else {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $driverKind = trim((string) ($_POST['driver_kind'] ?? 'owner_operator'));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $fleetVehicleId = (int) ($_POST['fleet_vehicle_id'] ?? 0);
        $licenseNumber = trim((string) ($_POST['license_number'] ?? ''));
        $status = trim((string) ($_POST['status'] ?? 'pending'));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $payload = [
            'full_name' => $fullName,
            'driver_kind' => $driverKind,
            'phone' => $phone,
            'email' => $email,
            'fleet_vehicle_id' => $fleetVehicleId,
            'license_number' => $licenseNumber,
            'status' => $status,
            'notes' => $notes,
        ];
        try {
            if ($isNew) {
                $newId = $repo->insert($payload);
                header('Location: ' . Config::url('/admin/drivers/' . $newId));
                exit;
            }
            $repo->update($id, $payload);
            $message = 'Driver saved.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$titleBit = $isNew ? 'New driver' : 'Edit driver #' . $id;
header('Content-Type: text/html; charset=utf-8');
$pageTitle = $titleBit . ' · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'drivers';
$vpTopbarTitle = $isNew ? 'New driver' : 'Edit driver';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<header class="vp-page-hero">
  <?php
    vp_breadcrumbs([
        ['label' => 'Drivers', 'href' => vp_url('/admin/drivers')],
        ['label' => $titleBit, 'href' => null],
    ]);
?>
  <h1 class="vp-page-title"><?= vp_h($titleBit) ?></h1>
  <p class="vp-page-desc">Assign an <strong>active</strong> vehicle: owner-operators need a <strong>personal</strong> car row; company drivers need a <strong>company</strong> car row from <a href="<?= vp_h(vp_url('/admin/fleet')) ?>">Car management</a>.</p>
</header>

<?php if ($message !== '') { ?>
  <div class="vp-alert vp-alert--success" role="status"><?= vp_h($message) ?></div>
<?php } ?>
<?php if ($error !== '') { ?>
  <div class="vp-alert vp-alert--error" role="alert"><?= vp_h($error) ?></div>
<?php } ?>

<section class="vp-card">
  <div class="vp-card__pad">
    <form method="post" action="<?= vp_h(vp_url($isNew ? '/admin/drivers/new' : '/admin/drivers/' . $id)) ?>" class="vp-stack-form">
      <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
      <div class="vp-field">
        <label class="vp-label" for="full_name">Full name</label>
        <input class="vp-input" id="full_name" name="full_name" type="text" value="<?= vp_h($fullName) ?>" maxlength="120" required autocomplete="name">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="driver_kind">Driver type</label>
        <select class="vp-input" id="driver_kind" name="driver_kind" required>
          <option value="owner_operator"<?= $driverKind === 'owner_operator' ? ' selected' : '' ?>>Owner-operator (uses own car)</option>
          <option value="company_driver"<?= $driverKind === 'company_driver' ? ' selected' : '' ?>>Company driver (brand / fleet vehicle)</option>
        </select>
      </div>
      <div class="vp-field" style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
        <div>
          <label class="vp-label" for="phone">Phone</label>
          <input class="vp-input" id="phone" name="phone" type="text" value="<?= vp_h($phone) ?>" maxlength="32" autocomplete="tel">
        </div>
        <div>
          <label class="vp-label" for="email">Email</label>
          <input class="vp-input" id="email" name="email" type="email" value="<?= vp_h($email) ?>" maxlength="255" autocomplete="email">
        </div>
      </div>
      <div class="vp-field">
        <label class="vp-label" for="fleet_vehicle_id">Assigned vehicle</label>
        <select class="vp-input" id="fleet_vehicle_id" name="fleet_vehicle_id">
          <option value="0">— None (assign later) —</option>
          <?php foreach ($vehicles as $v) {
              $vid = (int) $v['id'];
              $plate = (string) $v['plate_number'];
              $mm = trim(((string) ($v['make'] ?? '')) . ' ' . ((string) ($v['model'] ?? '')));
              $own = (string) $v['ownership'];
              $badge = $own === 'company' ? ('Company: ' . (string) ($v['company_fleet_label'] ?? '')) : 'Personal';
              $label = $plate . ($mm !== '' ? ' · ' . $mm : '') . ' · ' . $badge;
              ?>
            <option value="<?= $vid ?>" data-ownership="<?= vp_h($own) ?>"<?= $fleetVehicleId === $vid ? ' selected' : '' ?>><?= vp_h($label) ?></option>
          <?php } ?>
        </select>
        <p class="vp-field-hint">Only <strong>active</strong> vehicles appear here. The server checks that the vehicle ownership matches the driver type when you save.</p>
      </div>
      <div class="vp-field">
        <label class="vp-label" for="license_number">License / permit ID (optional)</label>
        <input class="vp-input vp-input--mono" id="license_number" name="license_number" type="text" value="<?= vp_h($licenseNumber) ?>" maxlength="64" autocomplete="off">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="status">Status</label>
        <select class="vp-input" id="status" name="status" required>
          <option value="pending"<?= $status === 'pending' ? ' selected' : '' ?>>Pending</option>
          <option value="active"<?= $status === 'active' ? ' selected' : '' ?>>Active</option>
          <option value="suspended"<?= $status === 'suspended' ? ' selected' : '' ?>>Suspended</option>
        </select>
      </div>
      <div class="vp-field">
        <label class="vp-label" for="notes">Internal notes</label>
        <textarea class="vp-input" id="notes" name="notes" rows="3" maxlength="512" autocomplete="off"><?= vp_h($notes) ?></textarea>
      </div>
      <div class="vp-toolbar">
        <button type="submit" class="vp-btn vp-btn--primary">Save</button>
        <a class="vp-btn vp-btn--ghost" href="<?= vp_url('/admin/drivers') ?>">Cancel</a>
      </div>
    </form>
  </div>
</section>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
