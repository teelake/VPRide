<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/RegionRepository.php';
require_once $backendRoot . '/src/RideRepository.php';
require_once $backendRoot . '/src/RiderUserRepository.php';

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
$draftCount = count(array_filter($configs, static fn ($c) => (int) $c['is_active'] !== 1));

$recentRides = Auth::can('rides.view') ? $rideRepo->listRecent(6) : [];

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
  <p class="vp-page-desc">Live metrics and shortcuts across riders, rides, and region routing for VP Ride.</p>
</header>

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

<?php if (Auth::can('rides.view') && $recentRides !== []) { ?>
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
    </div>
  </section>
<?php } ?>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
