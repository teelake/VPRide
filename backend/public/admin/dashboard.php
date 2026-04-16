<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/AnalyticsRepository.php';
require_once $backendRoot . '/src/RegionRepository.php';
require_once $backendRoot . '/src/RideRepository.php';
require_once $backendRoot . '/src/RiderUserRepository.php';

use VprideBackend\AnalyticsRepository;
use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\RegionRepository;
use VprideBackend\RideRepository;
use VprideBackend\RiderUserRepository;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('dashboard.view');

$admin = Auth::currentAdmin();
$pdo = Database::pdo();
$riderRepo = new RiderUserRepository($pdo);
$rideRepo = new RideRepository($pdo);
$regionRepo = new RegionRepository($pdo);
$analytics = new AnalyticsRepository($pdo);

$riderCount = $riderRepo->countAll();
$rideCount = $rideRepo->countAll();
$configs = $regionRepo->listConfigs();
$liveLabel = '—';
foreach ($configs as $c) {
    if ((int) $c['is_active'] === 1) {
        $liveLabel = (string) $c['label'];
        break;
    }
}
$draftCount = 0;
foreach ($configs as $c) {
    if ((int) $c['is_active'] !== 1) {
        $draftCount++;
    }
}

$recentRides = Auth::can('rides.view') ? $rideRepo->listRecent(6) : [];

$rides24h = $analytics->ridesCountLastHours(24);
$rides7d = $analytics->ridesNewLastDays(7);
$riders7d = $analytics->ridersNewLastDays(7);
$ridesByDay = $analytics->ridesPerDayLastDays(7);
$ridersByDay = $analytics->ridersPerDayLastDays(7);
$statusRows = $analytics->ridesByStatus();
$statusMap = [];
$statusTotal = 0;
foreach ($statusRows as $row) {
    $st = (string) $row['status'];
    $n = (int) $row['c'];
    $statusMap[$st] = $n;
    $statusTotal += $n;
}
$completedN = $statusMap['completed'] ?? 0;
$completionPct = $statusTotal > 0 ? (int) round(100 * $completedN / $statusTotal) : 0;

$peakRideDay = null;
$peakRideCount = 0;
foreach ($ridesByDay as $pt) {
    if ((int) $pt['c'] > $peakRideCount) {
        $peakRideCount = (int) $pt['c'];
        $peakRideDay = $pt['d'];
    }
}

$insights = [];
if ($peakRideDay !== null && $peakRideCount > 0) {
    $insights[] = 'Busiest day for new rides (last 7 days): ' . date('l j M', strtotime((string) $peakRideDay)) . ' (' . number_format($peakRideCount) . ').';
}
if ($riders7d > 0 && $rides7d > 0) {
    $ratio = $rides7d / $riders7d;
    $insights[] = 'Roughly ' . number_format($ratio, 1) . ' new rides per new rider sign-up this week (directional only).';
}
if ($rides24h === 0 && $rideCount > 0) {
    $insights[] = 'No rides in the last 24 hours — check demand or app availability.';
}

$sparkMaxRides = 1;
foreach ($ridesByDay as $pt) {
    $sparkMaxRides = max($sparkMaxRides, (int) $pt['c']);
}
$sparkMaxRiders = 1;
foreach ($ridersByDay as $pt) {
    $sparkMaxRiders = max($sparkMaxRiders, (int) $pt['c']);
}

$csrf = Auth::csrfToken();

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Overview · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'overview';
$vpTopbarTitle = 'Overview';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<header class="vp-page-hero">
  <h1 class="vp-page-title">Operations overview</h1>
  <p class="vp-page-desc">Performance signals, live routing, and quick paths into VP Ride operations.</p>
</header>

<?php vp_schema_migration_alerts($pdo); ?>

<div class="vp-kpi-grid" role="list">
  <article class="vp-kpi-card vp-kpi-card--tone-sand" role="listitem">
    <div class="vp-kpi-card__top">
      <span class="vp-kpi-card__icon" aria-hidden="true"><?= vp_kpi_icon_riders() ?></span>
    </div>
    <p class="vp-kpi-card__label">Registered riders</p>
    <p class="vp-kpi-card__value"><?= number_format($riderCount) ?></p>
    <?php if (Auth::can('riders.view')) { ?>
      <a class="vp-kpi-card__link" href="<?= vp_url('/admin/riders') ?>">View riders</a>
    <?php } ?>
  </article>
  <article class="vp-kpi-card vp-kpi-card--tone-slate" role="listitem">
    <div class="vp-kpi-card__top">
      <span class="vp-kpi-card__icon" aria-hidden="true"><?= vp_kpi_icon_rides() ?></span>
    </div>
    <p class="vp-kpi-card__label">Total rides</p>
    <p class="vp-kpi-card__value"><?= number_format($rideCount) ?></p>
    <?php if (Auth::can('rides.view')) { ?>
      <a class="vp-kpi-card__link" href="<?= vp_url('/admin/rides') ?>">View rides</a>
    <?php } ?>
  </article>
  <article class="vp-kpi-card vp-kpi-card--tone-mint" role="listitem">
    <div class="vp-kpi-card__top">
      <span class="vp-kpi-card__icon" aria-hidden="true"><?= vp_kpi_icon_globe() ?></span>
    </div>
    <p class="vp-kpi-card__label">Live region</p>
    <p class="vp-kpi-card__value vp-kpi-card__value--sm"><?= vp_h($liveLabel) ?></p>
    <?php if (Auth::can('regions.view')) { ?>
      <a class="vp-kpi-card__link" href="<?= vp_url('/admin/regions') ?>">Manage regions</a>
    <?php } ?>
  </article>
  <article class="vp-kpi-card vp-kpi-card--tone-lilac" role="listitem">
    <div class="vp-kpi-card__top">
      <span class="vp-kpi-card__icon" aria-hidden="true"><?= vp_kpi_icon_layers() ?></span>
    </div>
    <p class="vp-kpi-card__label">Draft profiles</p>
    <p class="vp-kpi-card__value"><?= number_format($draftCount) ?></p>
    <?php if (Auth::can('regions.manage')) { ?>
      <a class="vp-kpi-card__link" href="<?= vp_url('/admin/region/new') ?>">New draft</a>
    <?php } elseif (Auth::can('regions.view')) { ?>
      <a class="vp-kpi-card__link" href="<?= vp_url('/admin/regions') ?>">Open regions</a>
    <?php } ?>
  </article>
</div>

<section class="vp-bi-section" aria-labelledby="bi-heading">
  <div class="vp-bi-section__head">
    <h2 id="bi-heading" class="vp-section-title vp-bi-section__title">Intelligence</h2>
    <p class="vp-bi-section__lede">Recent volume and mix — use Reports for full filters and CSV export.</p>
    <div class="vp-bi-section__actions">
      <?php if (Auth::can('reports.view')) { ?>
        <a class="vp-btn vp-btn--ghost vp-btn--sm" href="<?= vp_url('/admin/reports/rides') ?>">Ride reports</a>
        <a class="vp-btn vp-btn--ghost vp-btn--sm" href="<?= vp_url('/admin/reports/riders') ?>">Rider reports</a>
      <?php } ?>
    </div>
  </div>

  <div class="vp-kpi-grid vp-kpi-grid--tight" role="list">
    <article class="vp-kpi-card vp-kpi-card--tone-sand vp-kpi-card--compact" role="listitem">
      <div class="vp-kpi-card__top">
        <span class="vp-kpi-card__icon" aria-hidden="true"><?= vp_kpi_icon_rides() ?></span>
      </div>
      <p class="vp-kpi-card__label">Rides · 24 hours</p>
      <p class="vp-kpi-card__value"><?= number_format($rides24h) ?></p>
    </article>
    <article class="vp-kpi-card vp-kpi-card--tone-slate vp-kpi-card--compact" role="listitem">
      <div class="vp-kpi-card__top">
        <span class="vp-kpi-card__icon" aria-hidden="true"><?= vp_kpi_icon_rides() ?></span>
      </div>
      <p class="vp-kpi-card__label">Rides · 7 days</p>
      <p class="vp-kpi-card__value"><?= number_format($rides7d) ?></p>
    </article>
    <article class="vp-kpi-card vp-kpi-card--tone-mint vp-kpi-card--compact" role="listitem">
      <div class="vp-kpi-card__top">
        <span class="vp-kpi-card__icon" aria-hidden="true"><?= vp_kpi_icon_riders() ?></span>
      </div>
      <p class="vp-kpi-card__label">New riders · 7 days</p>
      <p class="vp-kpi-card__value"><?= number_format($riders7d) ?></p>
    </article>
    <article class="vp-kpi-card vp-kpi-card--tone-lilac vp-kpi-card--compact" role="listitem">
      <div class="vp-kpi-card__top">
        <span class="vp-kpi-card__icon" aria-hidden="true"><?= vp_kpi_icon_layers() ?></span>
      </div>
      <p class="vp-kpi-card__label">Completion rate</p>
      <p class="vp-kpi-card__value"><?= $statusTotal > 0 ? vp_h((string) $completionPct) . '%' : '—' ?></p>
    </article>
  </div>

  <div class="vp-bi-panels">
    <div class="vp-card vp-bi-panel">
      <div class="vp-card__pad">
        <h3 class="vp-bi-panel__title">Daily volume · 7 days</h3>
        <div class="vp-spark-block">
          <p class="vp-spark-block__label">Rides</p>
          <div class="vp-spark" role="img" aria-label="Rides per day, last 7 days">
            <?php foreach ($ridesByDay as $pt) {
                $pct = $sparkMaxRides > 0 ? (int) round(100 * (int) $pt['c'] / $sparkMaxRides) : 0;
                $label = date('D', strtotime((string) $pt['d']));
                ?>
              <div class="vp-spark__cell" title="<?= vp_h((string) $pt['d'] . ': ' . (string) (int) $pt['c']) ?>">
                <span class="vp-spark__bar" style="height: <?= max(4, $pct) ?>%;"></span>
                <span class="vp-spark__dow"><?= vp_h($label) ?></span>
              </div>
            <?php } ?>
          </div>
        </div>
        <div class="vp-spark-block">
          <p class="vp-spark-block__label">New rider sign-ups</p>
          <div class="vp-spark vp-spark--mint" role="img" aria-label="New riders per day, last 7 days">
            <?php foreach ($ridersByDay as $pt) {
                $pct = $sparkMaxRiders > 0 ? (int) round(100 * (int) $pt['c'] / $sparkMaxRiders) : 0;
                $label = date('D', strtotime((string) $pt['d']));
                ?>
              <div class="vp-spark__cell" title="<?= vp_h((string) $pt['d'] . ': ' . (string) (int) $pt['c']) ?>">
                <span class="vp-spark__bar" style="height: <?= max(4, $pct) ?>%;"></span>
                <span class="vp-spark__dow"><?= vp_h($label) ?></span>
              </div>
            <?php } ?>
          </div>
        </div>
      </div>
    </div>

    <div class="vp-card vp-bi-panel">
      <div class="vp-card__pad">
        <h3 class="vp-bi-panel__title">Ride status mix</h3>
        <?php if ($statusRows === []) { ?>
          <p class="vp-page-desc" style="margin:0;">No ride rows in the database yet.</p>
        <?php } else { ?>
          <ul class="vp-status-mix" role="list">
            <?php foreach ($statusRows as $row) {
                $st = (string) $row['status'];
                $n = (int) $row['c'];
                $w = $statusTotal > 0 ? (int) round(100 * $n / $statusTotal) : 0;
                ?>
              <li class="vp-status-mix__row">
                <div class="vp-status-mix__meta">
                  <span class="vp-status-mix__name"><?= vp_h($st) ?></span>
                  <span class="vp-status-mix__count"><?= number_format($n) ?></span>
                </div>
                <div class="vp-status-mix__track" aria-hidden="true">
                  <span class="vp-status-mix__fill" style="width: <?= max(2, $w) ?>%;"></span>
                </div>
              </li>
            <?php } ?>
          </ul>
        <?php } ?>
      </div>
    </div>
  </div>

  <?php if ($insights !== []) { ?>
    <div class="vp-card vp-card--insight">
      <div class="vp-card__pad vp-card__pad--compact">
        <h3 class="vp-bi-panel__title">Signals</h3>
        <ul class="vp-insight-list">
          <?php foreach ($insights as $line) { ?>
            <li><?= vp_h($line) ?></li>
          <?php } ?>
        </ul>
      </div>
    </div>
  <?php } ?>
</section>

<?php if (Auth::can('rides.view')) { ?>
  <section class="vp-card vp-card--flush-top" aria-labelledby="recent-rides-heading">
    <div class="vp-card__pad">
      <div class="vp-card__head-row">
        <h2 id="recent-rides-heading" class="vp-section-title" style="margin:0;">Recent rides</h2>
        <div class="vp-card__head-actions">
          <?php if (Auth::can('reports.view')) { ?>
            <a class="vp-btn vp-btn--ghost vp-btn--sm" href="<?= vp_url('/admin/reports/rides') ?>">Reports</a>
          <?php } ?>
          <a class="vp-btn vp-btn--ghost vp-btn--sm" href="<?= vp_url('/admin/rides') ?>">All rides</a>
        </div>
      </div>
      <?php if ($recentRides !== []) { ?>
        <div class="vp-table-wrap">
          <table class="vp-table vp-table--compact">
            <thead>
              <tr>
                <th scope="col">ID</th>
                <th scope="col">Rider</th>
                <th scope="col">Status</th>
                <th scope="col">Pickup</th>
                <th scope="col">Created</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentRides as $row) { ?>
                <tr>
                  <td class="vp-table__id"><?= (int) $row['id'] ?></td>
                  <td><?= vp_h((string) $row['rider_email']) ?></td>
                  <td><span class="vp-pill vp-pill--neutral"><?= vp_h((string) $row['status']) ?></span></td>
                  <td class="vp-table__muted"><?= vp_h((string) ($row['pickup_address'] ?: (($row['pickup_lat'] ?? '') . ', ' . ($row['pickup_lng'] ?? '')))) ?></td>
                  <td class="vp-table__muted" style="font-size:0.8125rem;"><?= vp_h((string) $row['created_at']) ?></td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      <?php } elseif (\VprideBackend\SchemaInspector::tableExists($pdo, 'rides')) { ?>
        <?php
          vp_empty_state(
              'No rides to show yet',
              'Latest requests from the app will appear here once riders start booking.',
              [['label' => 'View all rides', 'href' => vp_url('/admin/rides'), 'variant' => 'primary']],
          );
        ?>
      <?php } else { ?>
        <?php
          vp_empty_state(
              'Rides not set up',
              'Import backend/sql/migration_rides.sql on this database to enable ride history.',
              [],
          );
        ?>
      <?php } ?>
    </div>
  </section>
<?php } ?>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
