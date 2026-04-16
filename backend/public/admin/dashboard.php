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
require_once $backendRoot . '/src/AppSettingsRepository.php';

use VprideBackend\AnalyticsRepository;
use VprideBackend\AppSettingsRepository;
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

$dashSettings = (new AppSettingsRepository($pdo))->getPublicSettings();
$mapsApiKey = (string) ($dashSettings['mapsApiKey'] ?? '');
$featuredRide = (Auth::can('rides.view') && $recentRides !== []) ? $recentRides[0] : null;
$featuredMapUrl = null;
$featuredPins = null;
if ($featuredRide !== null) {
    $plat = (float) $featuredRide['pickup_lat'];
    $plng = (float) $featuredRide['pickup_lng'];
    $dlat = isset($featuredRide['dropoff_lat']) && $featuredRide['dropoff_lat'] !== null && $featuredRide['dropoff_lat'] !== ''
        ? (float) $featuredRide['dropoff_lat']
        : null;
    $dlng = isset($featuredRide['dropoff_lng']) && $featuredRide['dropoff_lng'] !== null && $featuredRide['dropoff_lng'] !== ''
        ? (float) $featuredRide['dropoff_lng']
        : null;
    $featuredMapUrl = vp_google_static_map_booking_url($mapsApiKey, $plat, $plng, $dlat, $dlng);
    $featuredPins = vp_booking_map_pin_positions($plat, $plng, $dlat, $dlng);
}
$tableRides = $recentRides;
if ($featuredRide !== null && count($recentRides) > 1) {
    $tableRides = array_slice($recentRides, 1);
} elseif ($featuredRide !== null && count($recentRides) === 1) {
    $tableRides = [];
}

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
$canceledN = $statusMap['canceled'] ?? $statusMap['cancelled'] ?? 0;
$completionPct = $statusTotal > 0 ? (int) round(100 * $completedN / $statusTotal) : 0;

$dashDateLabel = date('D j M');
$ridersWeekLine = $riders7d > 0
    ? sprintf('+%s new riders this week', number_format($riders7d))
    : 'No new riders this week';
$bookingsWeekLine = $rides7d > 0
    ? sprintf('%s bookings in the last 7 days', number_format($rides7d))
    : 'No bookings in the last 7 days';
$completionLine = $statusTotal > 0
    ? sprintf('%s completed of %s total', number_format($completedN), number_format($statusTotal))
    : 'No ride data yet';
$canceledLine = $canceledN > 0
    ? sprintf('%s canceled (all time)', number_format($canceledN))
    : 'No canceled rides recorded';

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

$activityRides = Auth::can('rides.view') ? $rideRepo->listRecent(8) : [];
$liveRegionOk = $liveLabel !== '—' && $liveLabel !== '';
$configHealthPct = $liveRegionOk ? 100 : 0;
$rides7dAvg = max(0.01, $rides7d / 7.0);
$pulsePct = (int) min(100, max(0, round(100 * $rides24h / $rides7dAvg)));

$csrf = Auth::csrfToken();

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Dashboard · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'dashboard';
$vpTopbarTitle = 'Dashboard';
require __DIR__ . '/includes/head.php';
$systemHealth = vp_system_health($pdo, $liveLabel);
require __DIR__ . '/includes/app_shell_start.php';
?>

<div class="vp-dash-overview">
  <div class="vp-dash-overview__intro">
    <h1 class="vp-dash-overview__title">Operations overview</h1>
    <p class="vp-dash-overview__desc">Routing health, booking flow, and rider demand — backed by live data from this console.</p>
    <p class="vp-dash-overview__meta"><span class="vp-dash-overview__date"><?= vp_h($dashDateLabel) ?></span><span class="vp-dash-overview__sep" aria-hidden="true">·</span><span class="vp-dash-overview__hint">Use the top search for riders; tools link to help and settings.</span></p>
  </div>
  <div class="vp-dash-overview__status">
    <?php vp_system_health_render($systemHealth); ?>
  </div>
</div>

<?php vp_schema_migration_alerts($pdo); ?>

<div class="vp-kpi-grid vp-kpi-grid--dash" role="list">
  <article class="vp-kpi-card vp-kpi-card--dash" role="listitem">
    <div class="vp-kpi-card__dash-top">
      <span class="vp-kpi-card__bubble vp-kpi-card__bubble--brand-a" aria-hidden="true"><?= vp_kpi_icon_riders() ?></span>
    </div>
    <p class="vp-kpi-card__label">Registered riders</p>
    <p class="vp-kpi-card__value"><?= number_format($riderCount) ?></p>
    <p class="vp-kpi-card__delta<?= $riders7d > 0 ? ' vp-kpi-card__delta--up' : ' vp-kpi-card__delta--muted' ?>"><?= vp_h($ridersWeekLine) ?></p>
    <?php if (Auth::can('riders.view')) { ?>
      <a class="vp-kpi-card__dash-link" href="<?= vp_url('/admin/users') ?>">User management</a>
    <?php } ?>
  </article>
  <article class="vp-kpi-card vp-kpi-card--dash" role="listitem">
    <div class="vp-kpi-card__dash-top">
      <span class="vp-kpi-card__bubble vp-kpi-card__bubble--brand-b" aria-hidden="true"><?= vp_kpi_icon_rides() ?></span>
    </div>
    <p class="vp-kpi-card__label">Total bookings</p>
    <p class="vp-kpi-card__value"><?= number_format($rideCount) ?></p>
    <p class="vp-kpi-card__delta<?= $rides7d > 0 ? ' vp-kpi-card__delta--neutral' : ' vp-kpi-card__delta--muted' ?>"><?= vp_h($bookingsWeekLine) ?></p>
    <?php if (Auth::can('rides.view')) { ?>
      <a class="vp-kpi-card__dash-link" href="<?= vp_url('/admin/rides') ?>">Open bookings</a>
    <?php } ?>
  </article>
  <article class="vp-kpi-card vp-kpi-card--dash" role="listitem">
    <div class="vp-kpi-card__dash-top">
      <span class="vp-kpi-card__bubble vp-kpi-card__bubble--brand-c" aria-hidden="true"><?= vp_kpi_icon_globe() ?></span>
    </div>
    <p class="vp-kpi-card__label">Completion rate</p>
    <p class="vp-kpi-card__value"><?= $statusTotal > 0 ? vp_h((string) $completionPct) . '%' : '—' ?></p>
    <p class="vp-kpi-card__delta<?= $statusTotal > 0 && $completionPct >= 50 ? ' vp-kpi-card__delta--up' : ($statusTotal > 0 ? ' vp-kpi-card__delta--warn' : ' vp-kpi-card__delta--muted') ?>"><?= vp_h($completionLine) ?></p>
    <?php if (Auth::can('reports.view')) { ?>
      <a class="vp-kpi-card__dash-link" href="<?= vp_url('/admin/reports/rides') ?>">Ride reports</a>
    <?php } ?>
  </article>
  <article class="vp-kpi-card vp-kpi-card--dash" role="listitem">
    <div class="vp-kpi-card__dash-top">
      <span class="vp-kpi-card__bubble vp-kpi-card__bubble--brand-d" aria-hidden="true"><?= vp_kpi_icon_layers() ?></span>
    </div>
    <p class="vp-kpi-card__label">Canceled</p>
    <p class="vp-kpi-card__value"><?= number_format($canceledN) ?></p>
    <p class="vp-kpi-card__delta<?= $canceledN > 0 ? ' vp-kpi-card__delta--down' : ' vp-kpi-card__delta--muted' ?>"><?= vp_h($canceledLine) ?></p>
    <?php if (Auth::can('regions.view')) { ?>
      <a class="vp-kpi-card__dash-link" href="<?= vp_url('/admin/regions') ?>">Live: <?= vp_h($liveLabel) ?> · Regions</a>
    <?php } elseif (Auth::can('rides.view')) { ?>
      <a class="vp-kpi-card__dash-link" href="<?= vp_url('/admin/rides') ?>">Review bookings</a>
    <?php } ?>
  </article>
</div>

<div class="vp-dash-layout">
  <div class="vp-dash-layout__main">
    <?php if (Auth::can('rides.view')) { ?>
      <div class="vp-dash-ops" aria-labelledby="ops-monitor-heading">
        <div class="vp-dash-ops__monitor">
          <div class="vp-dash-ops__monitor-head">
            <h2 id="ops-monitor-heading" class="vp-dash-ops__monitor-title">Live booking monitor</h2>
            <?php if (Auth::can('regions.view')) { ?>
              <span class="vp-dash-ops__tag"><?= vp_h($liveRegionOk ? $liveLabel : 'No live region') ?></span>
            <?php } ?>
          </div>
          <?php if ($featuredRide !== null && $featuredPins !== null) { ?>
            <section class="vp-card vp-card--dash-surface vp-dash-featured vp-dash-featured--embedded" aria-labelledby="featured-booking-heading">
              <div class="vp-card__pad vp-dash-featured__grid">
                <div class="vp-dash-featured__copy">
                  <p class="vp-dash-featured__eyebrow">Ride #<?= (int) $featuredRide['id'] ?></p>
                  <h3 id="featured-booking-heading" class="vp-dash-featured__h">
                    <span class="vp-dash-featured__rider"><?= vp_h((string) $featuredRide['rider_email']) ?></span>
                  </h3>
                  <div class="vp-dash-featured__chips">
                    <span class="vp-status-cell">
                      <span class="vp-status-dot <?= vp_h(vp_ride_status_dot_class((string) $featuredRide['status'])) ?>" aria-hidden="true"></span>
                      <span class="vp-status-cell__text"><?= vp_h((string) $featuredRide['status']) ?></span>
                    </span>
                    <span class="vp-dash-featured__time"><?= vp_h(vp_relative_time((string) $featuredRide['created_at'])) ?></span>
                  </div>
                  <dl class="vp-dash-featured__route">
                    <div>
                      <dt>Pickup</dt>
                      <dd><?= vp_h((string) ($featuredRide['pickup_address'] !== '' && $featuredRide['pickup_address'] !== null
                          ? $featuredRide['pickup_address']
                          : number_format((float) $featuredRide['pickup_lat'], 5) . ', ' . number_format((float) $featuredRide['pickup_lng'], 5))) ?></dd>
                    </div>
                    <div>
                      <dt>Drop-off</dt>
                      <dd><?= vp_h((string) (($featuredRide['dropoff_address'] ?? '') !== ''
                          ? $featuredRide['dropoff_address']
                          : (($featuredRide['dropoff_lat'] ?? null) !== null && ($featuredRide['dropoff_lat'] ?? '') !== ''
                              ? number_format((float) $featuredRide['dropoff_lat'], 5) . ', ' . number_format((float) $featuredRide['dropoff_lng'], 5)
                              : '—'))) ?></dd>
                    </div>
                  </dl>
                  <a class="vp-btn vp-btn--soft vp-btn--sm" href="<?= vp_url('/admin/rides') ?>">All bookings</a>
                </div>
                <div class="vp-dash-featured__visual" aria-hidden="true">
                  <div class="vp-dash-featured__car" title="Booking preview">
                    <svg class="vp-dash-featured__car-svg" width="140" height="72" viewBox="0 0 140 72" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <path d="M8 44h18l8-16h56l14 16h30v8H8v-8z" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="1.5"/>
                      <path d="M34 28h48l10 16H38l-4-16z" fill="#e2e8f0" stroke="#cbd5e1" stroke-width="1.2"/>
                      <circle cx="36" cy="48" r="7" fill="#334155" stroke="#1e293b" stroke-width="1.5"/>
                      <circle cx="102" cy="48" r="7" fill="#334155" stroke="#1e293b" stroke-width="1.5"/>
                      <path d="M118 36h12l6 8h-10l-8-8z" fill="#fef3c7" stroke="#f59e0b" stroke-width="1"/>
                    </svg>
                  </div>
                  <div class="vp-dash-featured__map">
                    <?php if ($featuredMapUrl !== null) { ?>
                      <?php
                        $featuredMapAlt = 'Map preview: pickup'
                            . ($featuredPins['drop'] !== null ? ' and drop-off' : '')
                            . ' for booking #' . (int) $featuredRide['id'] . '.';
                        ?>
                      <img
                        class="vp-dash-featured__map-img"
                        src="<?= vp_h($featuredMapUrl) ?>"
                        width="480"
                        height="240"
                        alt="<?= vp_h($featuredMapAlt) ?>"
                        loading="lazy"
                        decoding="async"
                      >
                    <?php } else { ?>
                      <div class="vp-dash-featured__map-fake">
                        <svg class="vp-dash-featured__map-bg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                          <rect width="100" height="100" fill="#eef1f6"/>
                          <path d="M0 62 Q25 58 50 62 T100 62 V100 H0Z" fill="#e2e8f0"/>
                          <path d="M38 0 L42 100" stroke="#d8dee9" stroke-width="0.35" vector-effect="non-scaling-stroke"/>
                          <path d="M72 0 L68 100" stroke="#d8dee9" stroke-width="0.35" vector-effect="non-scaling-stroke"/>
                          <path d="M0 38 H100" stroke="#d8dee9" stroke-width="0.35" vector-effect="non-scaling-stroke"/>
                        </svg>
                        <span
                          class="vp-dash-featured__pin vp-dash-featured__pin--pick"
                          style="left: <?= (string) round($featuredPins['pick']['x'], 2) ?>%;top: <?= (string) round($featuredPins['pick']['y'], 2) ?>%;"
                        ></span>
                        <?php if ($featuredPins['drop'] !== null) { ?>
                          <span
                            class="vp-dash-featured__pin vp-dash-featured__pin--drop"
                            style="left: <?= (string) round($featuredPins['drop']['x'], 2) ?>%;top: <?= (string) round($featuredPins['drop']['y'], 2) ?>%;"
                          ></span>
                        <?php } ?>
                        <?php if ($mapsApiKey === '') { ?>
                          <p class="vp-dash-featured__map-hint">Add a Maps API key in Settings for a live map tile.</p>
                        <?php } ?>
                      </div>
                    <?php } ?>
                  </div>
                </div>
              </div>
            </section>
          <?php } else { ?>
            <div class="vp-card vp-card--dash-surface vp-dash-ops__empty">
              <div class="vp-card__pad">
                <p class="vp-page-desc" style="margin:0;">No bookings yet — the map and route preview will appear when riders request trips.</p>
              </div>
            </div>
          <?php } ?>
        </div>
        <div class="vp-card vp-card--dash-surface vp-dash-ops__feed">
          <div class="vp-card__pad">
            <div class="vp-dash-feed__head">
              <h2 class="vp-dash-feed__title">Recent activity</h2>
              <a class="vp-dash-feed__all" href="<?= vp_url('/admin/rides') ?>">View all</a>
            </div>
            <?php if ($activityRides !== []) { ?>
              <ul class="vp-activity-feed">
                <?php foreach ($activityRides as $r) { ?>
                  <li class="vp-activity-feed__item">
                    <span class="vp-activity-feed__avatar" aria-hidden="true"><?= vp_h(vp_admin_initials((string) $r['rider_email'])) ?></span>
                    <div class="vp-activity-feed__body">
                      <div class="vp-activity-feed__top">
                        <span class="vp-activity-feed__name"><?= vp_h((string) $r['rider_email']) ?></span>
                        <span class="vp-activity-badge <?= vp_h(vp_ride_activity_badge_mod((string) $r['status'])) ?>"><?= vp_h(strtoupper(str_replace('_', ' ', (string) $r['status']))) ?></span>
                      </div>
                      <p class="vp-activity-feed__route"><?= vp_h(vp_ride_route_summary($r)) ?></p>
                      <time class="vp-activity-feed__time" datetime="<?= vp_h((string) $r['created_at']) ?>"><?= vp_h(vp_relative_time((string) $r['created_at'])) ?> · #<?= (int) $r['id'] ?></time>
                    </div>
                  </li>
                <?php } ?>
              </ul>
            <?php } else { ?>
              <p class="vp-page-desc" style="margin:0;">No ride activity to show yet.</p>
            <?php } ?>
          </div>
        </div>
      </div>
    <?php } ?>

    <section class="vp-bi-section vp-bi-section--dash" aria-labelledby="bi-heading">
      <div class="vp-bi-section__head">
        <div>
          <h2 id="bi-heading" class="vp-bi-section__title">Volume &amp; mix</h2>
          <p class="vp-bi-section__lede">Seven-day volume and status distribution. Export detail from Reports.</p>
        </div>
        <div class="vp-bi-section__actions">
          <?php if (Auth::can('reports.view')) { ?>
            <a class="vp-btn vp-btn--soft vp-btn--sm" href="<?= vp_url('/admin/reports/rides') ?>">Ride reports</a>
            <a class="vp-btn vp-btn--soft vp-btn--sm" href="<?= vp_url('/admin/reports/riders') ?>">Rider reports</a>
          <?php } ?>
        </div>
      </div>

      <div class="vp-dash-mini-stats" role="list">
        <div class="vp-dash-mini-stat" role="listitem">
          <span class="vp-dash-mini-stat__label">Last 24 hours</span>
          <span class="vp-dash-mini-stat__value"><?= number_format($rides24h) ?></span>
          <span class="vp-dash-mini-stat__hint">bookings</span>
        </div>
        <div class="vp-dash-mini-stat" role="listitem">
          <span class="vp-dash-mini-stat__label">Draft regions</span>
          <span class="vp-dash-mini-stat__value"><?= number_format($draftCount) ?></span>
          <span class="vp-dash-mini-stat__hint">not live</span>
        </div>
        <div class="vp-dash-mini-stat" role="listitem">
          <span class="vp-dash-mini-stat__label">New riders · 7d</span>
          <span class="vp-dash-mini-stat__value"><?= number_format($riders7d) ?></span>
          <span class="vp-dash-mini-stat__hint">sign-ups</span>
        </div>
      </div>

      <div class="vp-bi-panels">
        <div class="vp-card vp-bi-panel vp-card--dash-surface">
          <div class="vp-card__pad">
            <h3 class="vp-bi-panel__title">Daily volume · 7 days</h3>
            <div class="vp-spark-block">
              <p class="vp-spark-block__label">Bookings</p>
              <div class="vp-spark vp-spark--dash" role="img" aria-label="Bookings per day, last 7 days">
                <?php foreach ($ridesByDay as $pt) {
                    $pct = $sparkMaxRides > 0 ? (int) round(100 * (int) $pt['c'] / $sparkMaxRides) : 0;
                    $label = date('D', strtotime((string) $pt['d']));
                    ?>
                  <div class="vp-spark__cell" title="<?= vp_h((string) $pt['d'] . ': ' . (string) (int) $pt['c']) ?>">
                    <span class="vp-spark__bar vp-spark__bar--dash" style="height: <?= max(4, $pct) ?>%;"></span>
                    <span class="vp-spark__dow"><?= vp_h($label) ?></span>
                  </div>
                <?php } ?>
              </div>
            </div>
            <div class="vp-spark-block">
              <p class="vp-spark-block__label">New rider sign-ups</p>
              <div class="vp-spark vp-spark--dash-mint" role="img" aria-label="New riders per day, last 7 days">
                <?php foreach ($ridersByDay as $pt) {
                    $pct = $sparkMaxRiders > 0 ? (int) round(100 * (int) $pt['c'] / $sparkMaxRiders) : 0;
                    $label = date('D', strtotime((string) $pt['d']));
                    ?>
                  <div class="vp-spark__cell" title="<?= vp_h((string) $pt['d'] . ': ' . (string) (int) $pt['c']) ?>">
                    <span class="vp-spark__bar vp-spark__bar--dash-mint" style="height: <?= max(4, $pct) ?>%;"></span>
                    <span class="vp-spark__dow"><?= vp_h($label) ?></span>
                  </div>
                <?php } ?>
              </div>
            </div>
          </div>
        </div>

        <div class="vp-card vp-bi-panel vp-card--dash-surface">
          <div class="vp-card__pad">
            <h3 class="vp-bi-panel__title">Status mix</h3>
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
                      <span class="vp-status-mix__name"><span class="vp-status-dot <?= vp_h(vp_ride_status_dot_class($st)) ?>" aria-hidden="true"></span><?= vp_h($st) ?></span>
                      <span class="vp-status-mix__count"><?= number_format($n) ?></span>
                    </div>
                    <div class="vp-status-mix__track" aria-hidden="true">
                      <span class="vp-status-mix__fill vp-status-mix__fill--soft" style="width: <?= max(2, $w) ?>%;"></span>
                    </div>
                  </li>
                <?php } ?>
              </ul>
            <?php } ?>
          </div>
        </div>
      </div>
    </section>

    <?php if ($statusTotal > 0 && $statusRows !== []) { ?>
      <section class="vp-card vp-card--dash-surface vp-dash-distribution" aria-labelledby="dist-heading">
        <div class="vp-card__pad">
          <h2 id="dist-heading" class="vp-dash-distribution__title">Booking status mix</h2>
          <p class="vp-dash-distribution__lede">Share of rides by status (all time in this database).</p>
          <div class="vp-dist-stack" role="img" aria-label="Status share of all rides">
            <?php foreach ($statusRows as $row) {
                $st = (string) $row['status'];
                $w = $statusTotal > 0 ? max(1, (int) round(100 * (int) $row['c'] / $statusTotal)) : 0;
                $stKey = preg_replace('/[^a-z0-9_]/', '_', strtolower($st));
                $stKey = trim($stKey, '_') ?: 'other';
                $segClass = 'vp-dist-stack__seg--' . $stKey;
                ?>
              <span class="vp-dist-stack__seg <?= vp_h($segClass) ?>" style="width: <?= (string) $w ?>%;" title="<?= vp_h($st . ': ' . (string) (int) $row['c']) ?>"></span>
            <?php } ?>
          </div>
          <ul class="vp-dist-legend">
            <?php foreach ($statusRows as $row) {
                $st = (string) $row['status'];
                $pct = $statusTotal > 0 ? (int) round(100 * (int) $row['c'] / $statusTotal) : 0;
                ?>
              <li class="vp-dist-legend__item">
                <span class="vp-status-dot <?= vp_h(vp_ride_status_dot_class($st)) ?>" aria-hidden="true"></span>
                <span class="vp-dist-legend__name"><?= vp_h($st) ?></span>
                <span class="vp-dist-legend__pct"><?= (string) $pct ?>%</span>
              </li>
            <?php } ?>
          </ul>
          <div class="vp-health-meters" role="list">
            <div class="vp-health-meter" role="listitem">
              <div class="vp-health-meter__head">
                <span class="vp-health-meter__label">Live configuration</span>
                <span class="vp-health-meter__value"><?= (string) $configHealthPct ?>%</span>
              </div>
              <div class="vp-health-meter__track" aria-hidden="true">
                <span class="vp-health-meter__fill" style="width: <?= (string) $configHealthPct ?>%;"></span>
              </div>
              <p class="vp-health-meter__hint"><?= $liveRegionOk ? 'A region is live for rider routing.' : 'Promote a region so apps receive live config.' ?></p>
            </div>
            <div class="vp-health-meter" role="listitem">
              <div class="vp-health-meter__head">
                <span class="vp-health-meter__label">Completion pipeline</span>
                <span class="vp-health-meter__value"><?= $statusTotal > 0 ? vp_h((string) $completionPct) . '%' : '—' ?></span>
              </div>
              <div class="vp-health-meter__track" aria-hidden="true">
                <span class="vp-health-meter__fill vp-health-meter__fill--success" style="width: <?= $statusTotal > 0 ? (string) $completionPct : '0'; ?>%;"></span>
              </div>
              <p class="vp-health-meter__hint">Completed rides vs all recorded statuses.</p>
            </div>
            <div class="vp-health-meter" role="listitem">
              <div class="vp-health-meter__head">
                <span class="vp-health-meter__label">Demand pulse (24h vs 7d avg)</span>
                <span class="vp-health-meter__value"><?= (string) $pulsePct ?>%</span>
              </div>
              <div class="vp-health-meter__track" aria-hidden="true">
                <span class="vp-health-meter__fill vp-health-meter__fill--pulse" style="width: <?= (string) $pulsePct ?>%;"></span>
              </div>
              <p class="vp-health-meter__hint">100% means today’s last-24h volume matches the daily average over the past week.</p>
            </div>
          </div>
        </div>
      </section>
    <?php } ?>

    <?php if (Auth::can('rides.view')) { ?>
      <section class="vp-card vp-card--dash-surface vp-card--flush-top" aria-labelledby="recent-rides-heading">
        <div class="vp-card__pad">
          <div class="vp-card__head-row">
            <h2 id="recent-rides-heading" class="vp-dash-table-title">Recent bookings</h2>
            <div class="vp-card__head-actions">
              <?php if (Auth::can('reports.view')) { ?>
                <a class="vp-btn vp-btn--soft vp-btn--sm" href="<?= vp_url('/admin/reports/rides') ?>">Reports</a>
              <?php } ?>
              <a class="vp-btn vp-btn--soft vp-btn--sm" href="<?= vp_url('/admin/rides') ?>">All bookings</a>
            </div>
          </div>
          <?php if ($tableRides !== []) { ?>
            <div class="vp-table-wrap vp-table-wrap--dash">
              <table class="vp-table vp-table--compact vp-table--dash">
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
                  <?php foreach ($tableRides as $row) { ?>
                    <tr>
                      <td class="vp-table__id"><?= (int) $row['id'] ?></td>
                      <td class="vp-table__label"><?= vp_h((string) $row['rider_email']) ?></td>
                      <td>
                        <span class="vp-status-cell">
                          <span class="vp-status-dot <?= vp_h(vp_ride_status_dot_class((string) $row['status'])) ?>" aria-hidden="true"></span>
                          <span class="vp-status-cell__text"><?= vp_h((string) $row['status']) ?></span>
                        </span>
                      </td>
                      <td class="vp-table__muted"><?= vp_h((string) ($row['pickup_address'] ?: (($row['pickup_lat'] ?? '') . ', ' . ($row['pickup_lng'] ?? '')))) ?></td>
                      <td class="vp-table__muted vp-table__nowrap"><?= vp_h((string) $row['created_at']) ?></td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          <?php } elseif ($featuredRide !== null) { ?>
            <p class="vp-dash-table-note" role="status">Only one booking in this snapshot — details are in the card above.</p>
          <?php } elseif (\VprideBackend\SchemaInspector::tableExists($pdo, 'rides')) { ?>
            <?php
              vp_empty_state(
                  'No bookings yet',
                  'Latest requests from the app will appear here once riders start booking.',
                  [['label' => 'View all bookings', 'href' => vp_url('/admin/rides'), 'variant' => 'primary']],
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
  </div>

  <aside class="vp-dash-layout__aside" aria-label="Highlights">
    <div class="vp-card vp-card--dash-surface">
      <div class="vp-card__pad">
        <h3 class="vp-dash-aside-title">Today</h3>
        <ul class="vp-dash-aside-list">
          <li><span class="vp-dash-aside-k"><?= number_format($rides24h) ?></span> bookings last 24h</li>
          <li><span class="vp-dash-aside-k"><?= vp_h($liveLabel) ?></span> live region</li>
          <li><span class="vp-dash-aside-k"><?= number_format($draftCount) ?></span> draft profiles</li>
        </ul>
      </div>
    </div>
    <?php if ($insights !== []) { ?>
      <div class="vp-card vp-card--dash-surface">
        <div class="vp-card__pad">
          <h3 class="vp-dash-aside-title">Signals</h3>
          <ul class="vp-dash-timeline">
            <?php foreach ($insights as $line) { ?>
              <li class="vp-dash-timeline__item">
                <span class="vp-dash-timeline__dot" aria-hidden="true"></span>
                <span class="vp-dash-timeline__text"><?= vp_h($line) ?></span>
              </li>
            <?php } ?>
          </ul>
        </div>
      </div>
    <?php } ?>
    <div class="vp-card vp-card--dash-surface">
      <div class="vp-card__pad">
        <h3 class="vp-dash-aside-title">Shortcuts</h3>
        <nav class="vp-dash-shortcuts" aria-label="Quick links">
          <?php if (Auth::can('rides.view')) { ?>
            <a class="vp-dash-shortcut" href="<?= vp_url('/admin/rides') ?>">Bookings</a>
          <?php } ?>
          <?php if (Auth::can('riders.view')) { ?>
            <a class="vp-dash-shortcut" href="<?= vp_url('/admin/riders') ?>">Riders</a>
          <?php } ?>
          <?php if (Auth::can('regions.view')) { ?>
            <a class="vp-dash-shortcut" href="<?= vp_url('/admin/regions') ?>">Regions</a>
          <?php } ?>
          <?php if (Auth::can('settings.manage')) { ?>
            <a class="vp-dash-shortcut" href="<?= vp_url('/admin/settings') ?>">Settings</a>
          <?php } ?>
          <a class="vp-dash-shortcut" href="<?= vp_url('/admin/help') ?>">Help</a>
        </nav>
      </div>
    </div>
  </aside>
</div>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
