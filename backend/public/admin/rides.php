<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/RideRepository.php';
require_once $backendRoot . '/src/SchemaInspector.php';

use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\RideRepository;
use VprideBackend\SchemaInspector;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('rides.view');

$admin = Auth::currentAdmin();
$pdo = Database::pdo();
$rows = SchemaInspector::tableExists($pdo, 'rides')
    ? (new RideRepository($pdo))->listRecent(200)
    : [];
$csrf = Auth::csrfToken();

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Bookings · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'rides';
$vpTopbarTitle = 'Bookings';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<?php vp_schema_single_table_alert($pdo, 'rides', 'migration_rides.sql', 'Rides'); ?>

<header class="vp-page-hero">
  <h1 class="vp-page-title">Bookings</h1>
  <p class="vp-page-desc">Latest ride requests submitted from rider devices.</p>
</header>

<div class="vp-toolbar vp-toolbar--split">
  <div class="vp-toolbar__left"></div>
  <div class="vp-toolbar__actions">
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
              <th scope="col">Pickup</th>
              <th scope="col">Drop-off</th>
              <th scope="col">Created</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r) { ?>
              <tr>
                <td class="vp-table__id"><?= (int) $r['id'] ?></td>
                <td><?= vp_h((string) $r['rider_email']) ?></td>
                <td><span class="vp-pill vp-pill--neutral"><?= vp_h((string) $r['status']) ?></span></td>
                <td class="vp-table__muted"><?= vp_h((string) ($r['pickup_address'] ?: (($r['pickup_lat'] ?? '') . ', ' . ($r['pickup_lng'] ?? '')))) ?></td>
                <td class="vp-table__muted"><?= vp_h((string) ($r['dropoff_address'] ?? '—')) ?></td>
                <td style="color:var(--vp-muted); font-size:0.8125rem;"><?= vp_h((string) $r['created_at']) ?></td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    <?php } ?>
  </div>
</section>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
