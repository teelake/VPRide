<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/RideRepository.php';
require_once $backendRoot . '/src/SchemaInspector.php';
require_once $backendRoot . '/src/LoyaltyRewardService.php';

use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\LoyaltyRewardService;
use VprideBackend\RideRepository;
use VprideBackend\SchemaInspector;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('rides.view');

$admin = Auth::currentAdmin();
$pdo = Database::pdo();
$flash = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['mark_paid_ride_id'])) {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $flash = 'Invalid session.';
    } else {
        $rid = (int) $_POST['mark_paid_ride_id'];
        if ($rid > 0) {
            $repo = new RideRepository($pdo);
            $res = $repo->markPaid($rid);
            if ($res !== null) {
                (new LoyaltyRewardService($pdo))->onRideMarkedPaid($rid, $res['rider_user_id']);
                $flash = 'Ride #' . $rid . ' marked paid.';
            } else {
                $flash = 'Could not mark paid (must be completed and still pending).';
            }
        }
    }
}

$rows = SchemaInspector::tableExists($pdo, 'rides')
    ? (new RideRepository($pdo))->listRecent(200)
    : [];
$csrf = Auth::csrfToken();
$hasPayCol = SchemaInspector::columnExists($pdo, 'rides', 'payment_status');
$hasDriverCol = SchemaInspector::columnExists($pdo, 'rides', 'driver_rider_user_id');
$canDispatch = Auth::can('rides.dispatch');

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Bookings · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'rides';
$vpTopbarTitle = 'Bookings';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<?php vp_schema_single_table_alert($pdo, 'rides', 'migration_rides.sql', 'Rides'); ?>

<?php if ($flash !== '') { ?>
  <p class="vp-banner vp-banner--info" role="status"><?= vp_h($flash) ?></p>
<?php } ?>

<header class="vp-page-hero">
  <h1 class="vp-page-title">Bookings</h1>
  <p class="vp-page-desc">Latest ride requests submitted from rider devices.</p>
</header>

<div class="vp-toolbar vp-toolbar--split">
  <div class="vp-toolbar__left"></div>
  <div class="vp-toolbar__actions">
    <?php if (Auth::can('rides.create_manual')) { ?>
      <a class="vp-btn vp-btn--primary" href="<?= vp_url('/admin/rides/create') ?>">Create booking</a>
    <?php } ?>
    <?php if (Auth::can('reports.view')) { ?>
      <a class="vp-btn vp-btn--primary" href="<?= vp_url('/admin/reports/rides') ?>">Filtered reports</a>
    <?php } ?>
    <?php if (Auth::can('settings.manage')) { ?>
      <a class="vp-btn vp-btn--ghost" href="<?= vp_url('/admin/settings') ?>">Ride booking flags</a>
    <?php } ?>
  </div>
</div>

<section class="vp-card" aria-labelledby="rides-heading">
  <div class="vp-card__pad">
    <h2 id="rides-heading" class="vp-section-title">Recent activity</h2>
    <?php if ($rows === []) { ?>
      <?php if (SchemaInspector::tableExists($pdo, 'rides')) { ?>
        <?php
          $rideEmptyActions = [];
          if (Auth::can('reports.view')) {
              $rideEmptyActions[] = ['label' => 'Open ride reports', 'href' => vp_url('/admin/reports/rides'), 'variant' => 'primary'];
          }
          if (Auth::can('settings.manage')) {
              $rideEmptyActions[] = ['label' => 'Check booking flags', 'href' => vp_url('/admin/settings'), 'variant' => 'ghost'];
          }
          vp_empty_state(
              'No rides in the system yet',
              'When riders request trips from the mobile app, they will show up here and in reports.',
              $rideEmptyActions,
          );
        ?>
      <?php } else { ?>
        <?php
          vp_empty_state(
              'Rides table not installed',
              'Import backend/sql/migration_rides.sql on this database, then refresh.',
              [],
          );
        ?>
      <?php } ?>
    <?php } else { ?>
      <div class="vp-table-wrap">
        <table class="vp-table">
          <thead>
            <tr>
              <th scope="col">ID</th>
              <th scope="col">Rider</th>
              <th scope="col">Status</th>
              <?php if ($hasDriverCol) { ?>
                <th scope="col">Driver</th>
              <?php } ?>
              <?php if ($hasPayCol) { ?>
                <th scope="col">Payment</th>
                <th scope="col">Fare</th>
              <?php } ?>
              <th scope="col">Pickup</th>
              <th scope="col">Drop-off</th>
              <th scope="col">Created</th>
              <?php if ($hasPayCol || ($hasDriverCol && $canDispatch)) { ?><th scope="col"></th><?php } ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r) { ?>
              <tr>
                <td class="vp-table__id"><?= (int) $r['id'] ?></td>
                <td><?= vp_h((string) $r['rider_email']) ?></td>
                <td><span class="vp-pill vp-pill--neutral"><?= vp_h((string) $r['status']) ?></span></td>
                <?php if ($hasDriverCol) { ?>
                  <td class="vp-table__muted"><?php
                    $du = $r['driver_rider_user_id'] ?? null;
                    echo $du !== null && $du !== '' ? '#' . (int) $du : '—';
                  ?></td>
                <?php } ?>
                <?php if ($hasPayCol) { ?>
                  <td><span class="vp-pill vp-pill--neutral"><?= vp_h((string) ($r['payment_status'] ?? '—')) ?></span></td>
                  <td class="vp-table__muted"><?php
                    $cur = (string) ($r['fare_currency'] ?? '');
                    $ff = $r['final_fare_amount'] ?? null;
                    echo $ff !== null ? vp_h($cur . ' ' . (string) $ff) : '—';
                  ?></td>
                <?php } ?>
                <td class="vp-table__muted"><?= vp_h((string) ($r['pickup_address'] ?: (($r['pickup_lat'] ?? '') . ', ' . ($r['pickup_lng'] ?? '')))) ?></td>
                <td class="vp-table__muted"><?= vp_h((string) ($r['dropoff_address'] ?? '—')) ?></td>
                <td style="color:var(--vp-muted); font-size:0.8125rem;"><?= vp_h((string) $r['created_at']) ?></td>
                <?php if ($hasPayCol || ($hasDriverCol && $canDispatch)) { ?>
                  <td>
                    <?php if ($hasDriverCol && $canDispatch) { ?>
                      <a class="vp-btn vp-btn--sm vp-btn--ghost" href="<?= vp_url('/admin/rides/' . (int) $r['id'] . '/dispatch') ?>">Dispatch</a>
                    <?php } ?>
                    <?php if ($hasPayCol) { ?>
                      <?php
                        $st = (string) ($r['status'] ?? '');
                        $pay = (string) ($r['payment_status'] ?? 'pending');
                        $canMark = in_array($st, ['requested', 'accepted', 'in_progress', 'completed'], true)
                            && in_array($pay, ['pending', 'submitted'], true);
                      ?>
                      <?php if ($canMark) { ?>
                        <form method="post" style="margin:0;display:inline;">
                          <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
                          <input type="hidden" name="mark_paid_ride_id" value="<?= (int) $r['id'] ?>">
                          <button type="submit" class="vp-btn vp-btn--sm vp-btn--ghost">Mark paid</button>
                        </form>
                      <?php } elseif (! $canDispatch || ! $hasDriverCol) { ?>
                        —
                      <?php } ?>
                    <?php } ?>
                  </td>
                <?php } ?>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    <?php } ?>
  </div>
</section>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
