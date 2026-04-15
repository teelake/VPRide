<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/RideRepository.php';

use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\RideRepository;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('rides.view');

$admin = Auth::currentAdmin();
$rows = (new RideRepository(Database::pdo()))->listRecent(200);
$csrf = Auth::csrfToken();

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Rides · Pride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'rides';
$vpTopbarTitle = 'Rides';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<header class="vp-page-hero">
  <h1 class="vp-page-title">Rides</h1>
  <p class="vp-page-desc">Latest ride requests submitted from rider devices.</p>
</header>

<section class="vp-card" aria-labelledby="rides-heading">
  <div class="vp-card__pad">
    <h2 id="rides-heading" class="vp-section-title">Recent activity</h2>
    <?php if ($rows === []) { ?>
      <p class="vp-page-desc" style="margin-bottom:0;">No rides yet.</p>
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
