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
$hasCancelFeeCol = SchemaInspector::columnExists($pdo, 'rides', 'cancellation_fee_amount');
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
      <a class="vp-btn vp-btn--primary" href="<?= vp_url('/rides/create') ?>">Create booking</a>
    <?php } ?>
    <?php if (Auth::can('reports.view')) { ?>
      <a class="vp-btn vp-btn--primary" href="<?= vp_url('/reports/rides') ?>">Filtered reports</a>
    <?php } ?>
    <?php if (Auth::can('settings.manage')) { ?>
      <a class="vp-btn vp-btn--ghost" href="<?= vp_url('/settings') ?>">Ride booking flags</a>
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
              $rideEmptyActions[] = ['label' => 'Open ride reports', 'href' => vp_url('/reports/rides'), 'variant' => 'primary'];
          }
          if (Auth::can('settings.manage')) {
              $rideEmptyActions[] = ['label' => 'Check booking flags', 'href' => vp_url('/settings'), 'variant' => 'ghost'];
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
      <p class="vp-table-hint">Swipe or scroll horizontally to see every column. Hover a pickup or drop-off cell for the full text.</p>
      <div class="vp-table-wrap vp-table-wrap--readable">
        <table class="vp-table vp-table--readable">
          <thead>
            <tr>
              <th scope="col" class="vp-table__id">ID</th>
              <th scope="col" class="vp-table__col-rider">Rider</th>
              <th scope="col" class="vp-table__col-status">Status</th>
              <?php if ($hasDriverCol) { ?>
                <th scope="col" class="vp-table__col-tight">Driver</th>
              <?php } ?>
              <?php if ($hasPayCol) { ?>
                <th scope="col" class="vp-table__col-tight">Payment</th>
                <th scope="col" class="vp-table__col-tight">Fare</th>
              <?php } ?>
              <?php if ($hasCancelFeeCol) { ?>
                <th scope="col" class="vp-table__col-tight">Cancel fee</th>
              <?php } ?>
              <th scope="col" class="vp-table__col-route">Pickup</th>
              <th scope="col" class="vp-table__col-route">Drop-off</th>
              <th scope="col" class="vp-table__col-when">Created</th>
              <?php if ($hasPayCol || ($hasDriverCol && $canDispatch)) { ?><th scope="col" class="vp-table__actions-col"><span class="vp-sr-only">Actions</span></th><?php } ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r) { ?>
              <tr>
                <td class="vp-table__id"><?= (int) $r['id'] ?></td>
                <td class="vp-table__label vp-table__col-rider"><?= vp_h((string) $r['rider_email']) ?></td>
                <td class="vp-table__col-status"><span class="vp-pill vp-pill--neutral"><?= vp_h(vp_admin_ride_status_label((string) $r['status'])) ?></span></td>
                <?php if ($hasDriverCol) { ?>
                  <td class="vp-table__muted vp-table__col-tight"><?php
                    $du = $r['driver_rider_user_id'] ?? null;
                    echo $du !== null && $du !== '' ? '#' . (int) $du : '—';
                  ?></td>
                <?php } ?>
                <?php if ($hasPayCol) { ?>
                  <td class="vp-table__col-tight"><span class="vp-pill vp-pill--neutral"><?= vp_h(vp_admin_ride_status_label((string) ($r['payment_status'] ?? 'pending'))) ?></span></td>
                  <td class="vp-table__muted vp-table__col-tight"><?php
                    $cur = (string) ($r['fare_currency'] ?? '');
                    $ff = $r['final_fare_amount'] ?? null;
                    echo $ff !== null ? vp_h($cur . ' ' . (string) $ff) : '—';
                  ?></td>
                <?php } ?>
                <?php if ($hasCancelFeeCol) { ?>
                  <td class="vp-table__muted vp-table__col-tight"><?php
                    $cf = $r['cancellation_fee_amount'] ?? null;
                    $cst = (string) ($r['status'] ?? '');
                    if ($cst === 'cancelled' && $cf !== null && $cf !== '' && (float) $cf > 0) {
                        $cur = (string) ($r['fare_currency'] ?? '');
                        echo vp_h($cur . ' ' . (string) $cf);
                    } else {
                        echo '—';
                    }
                  ?></td>
                <?php } ?>
                <?php
                  $pickLine = vp_admin_ride_route_cell_text($r, 'pickup');
                  $dropLine = vp_admin_ride_route_cell_text($r, 'dropoff');
                ?>
                <td class="vp-table__muted vp-table__col-route"><?php if ($pickLine !== '—') { ?>
                  <span class="vp-table__route-line" title="<?= vp_h($pickLine) ?>"><?= vp_h($pickLine) ?></span>
                <?php } else { ?>—<?php } ?></td>
                <td class="vp-table__muted vp-table__col-route"><?php if ($dropLine !== '—') { ?>
                  <span class="vp-table__route-line" title="<?= vp_h($dropLine) ?>"><?= vp_h($dropLine) ?></span>
                <?php } else { ?>—<?php } ?></td>
                <td class="vp-table__col-when"><?= vp_h(vp_admin_booking_datetime((string) ($r['created_at'] ?? ''))) ?></td>
                <?php if ($hasPayCol || ($hasDriverCol && $canDispatch)) { ?>
                  <td class="vp-table__actions-col">
                    <?php
                    $st = (string) ($r['status'] ?? '');
                    $pay = (string) ($r['payment_status'] ?? 'pending');
                    $canMark = $hasPayCol
                        && in_array($st, ['requested', 'accepted', 'in_progress', 'completed'], true)
                        && in_array($pay, ['pending', 'submitted'], true);
                    $showDispatch = $hasDriverCol && $canDispatch;
                    $showSomething = $showDispatch || $canMark;
                    if ($showSomething) {
                        vp_action_icons_open();
                        if ($showDispatch) {
                            vp_action_link_dispatch(vp_url('/rides/' . (int) $r['id'] . '/dispatch'));
                        }
                        if ($canMark) {
                            vp_action_mark_paid_form(
                                vp_url('/rides'),
                                $csrf,
                                ['mark_paid_ride_id' => (int) $r['id']],
                            );
                        }
                        vp_action_icons_close();
                    } else {
                        echo '—';
                    }
                    ?>
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
