<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/RideRepository.php';
require_once $backendRoot . '/src/DriverFleetRepository.php';
require_once $backendRoot . '/src/SchemaInspector.php';

use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\DriverFleetRepository;
use VprideBackend\RideRepository;
use VprideBackend\SchemaInspector;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('rides.dispatch');

$rideId = (int) ($_ROUTE_RIDE_DISPATCH_ID ?? 0);
if ($rideId < 1) {
    http_response_code(404);
    exit('Not found');
}

$pdo = Database::pdo();
$rides = new RideRepository($pdo);
$ride = $rides->findById($rideId);
if ($ride === null) {
    http_response_code(404);
    exit('Ride not found');
}

$csrf = Auth::csrfToken();
$message = '';
$error = '';
$drivers = (new DriverFleetRepository($pdo))->listAssignableForAdmin();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session.';
    } else {
        $drv = (int) ($_POST['driver_rider_user_id'] ?? 0);
        $force = ! empty($_POST['force_accepted']);
        if ($drv < 1) {
            $error = 'Select a driver with a linked app account.';
        } elseif (! SchemaInspector::columnExists($pdo, 'rides', 'driver_rider_user_id')) {
            $error = 'Driver assignment column missing — run SOS / fleet migrations.';
        } else {
            $ok = $rides->adminAssignDriver($rideId, $drv, $force);
            if ($ok) {
                $message = 'Driver assigned.';
                $ride = $rides->findById($rideId);
            } else {
                $error = 'Could not assign (ride must be in a valid state).';
            }
        }
    }
}

$driverLabel = '—';
if (! empty($ride['driver_rider_user_id']) && SchemaInspector::columnExists($pdo, 'rider_users', 'email')) {
    $st = $pdo->prepare('SELECT email, display_name FROM rider_users WHERE id = ? LIMIT 1');
    $st->execute([(int) $ride['driver_rider_user_id']]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if ($u !== false) {
        $driverLabel = trim((string) ($u['display_name'] ?? '')) !== ''
            ? (string) $u['display_name']
            : (string) $u['email'];
    }
}

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Dispatch ride · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'rides';
$vpTopbarTitle = 'Dispatch';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<?php vp_breadcrumbs([
    ['label' => 'Bookings', 'href' => vp_url('/admin/rides')],
    ['label' => 'Ride #' . $rideId, 'href' => null],
]); ?>

<header class="vp-page-hero">
  <h1 class="vp-page-title">Dispatch ride #<?= (int) $rideId ?></h1>
  <p class="vp-page-desc">Manual assignment overrides auto-dispatch. Optional “force accepted” skips the driver accept step.</p>
</header>

<?php if ($message !== '') { ?>
  <p class="vp-banner vp-banner--info" role="status"><?= vp_h($message) ?></p>
<?php } ?>
<?php if ($error !== '') { ?>
  <p class="vp-banner vp-banner--danger" role="alert"><?= vp_h($error) ?></p>
<?php } ?>

<section class="vp-card">
  <div class="vp-card__pad">
    <p><strong>Status:</strong> <?= vp_h((string) $ride['status']) ?> · <strong>Current driver:</strong> <?= vp_h($driverLabel) ?></p>
    <p class="vp-field-hint">Rider pickup: <?= vp_h((string) ($ride['pickup_address'] ?? ($ride['pickup_lat'] . ',' . $ride['pickup_lng']))) ?></p>

    <?php if ($drivers === []) { ?>
      <p class="vp-field-hint">No active drivers with a linked <strong>App user ID</strong>. Edit a fleet driver and set <em>Linked rider user ID</em>.</p>
    <?php } else { ?>
      <form method="post" class="vp-stack-form vp-stack-form--medium" style="margin-top:1rem;">
        <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
        <div class="vp-field">
          <label class="vp-label" for="driver_rider_user_id">Driver (app account)</label>
          <select class="vp-input" id="driver_rider_user_id" name="driver_rider_user_id" required>
            <option value="">— Select —</option>
            <?php foreach ($drivers as $d) { ?>
              <option value="<?= (int) $d['rider_user_id'] ?>">
                <?= vp_h((string) $d['full_name']) ?> · rider #<?= (int) $d['rider_user_id'] ?>
              </option>
            <?php } ?>
          </select>
        </div>
        <label class="vp-toggle" style="display:flex;align-items:flex-start;gap:0.65rem;cursor:pointer;">
          <input type="checkbox" name="force_accepted" value="1" style="margin-top:0.2rem;">
          <span><strong>Force accepted</strong> — set status to accepted immediately (driver does not need to tap Accept).</span>
        </label>
        <button type="submit" class="vp-btn vp-btn--primary">Assign driver</button>
      </form>
    <?php } ?>
    <p style="margin-top:1.5rem;"><a class="vp-btn vp-btn--ghost" href="<?= vp_url('/admin/rides') ?>">← Back to bookings</a></p>
  </div>
</section>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
