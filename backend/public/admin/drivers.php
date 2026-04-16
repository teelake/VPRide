<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Auth.php';

use VprideBackend\Auth;
use VprideBackend\Config;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('rides.view');

$admin = Auth::currentAdmin();
$csrf = Auth::csrfToken();

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Drivers · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'drivers';
$vpTopbarTitle = 'Drivers';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<header class="vp-page-hero">
  <?php
    vp_breadcrumbs([
        ['label' => 'Dashboard', 'href' => vp_url('/admin/dashboard')],
        ['label' => 'Drivers', 'href' => null],
    ]);
?>
  <h1 class="vp-page-title">Driver directory</h1>
  <p class="vp-page-desc"><strong>Drivers</strong> are the people who fulfill rides — separate from <strong>riders</strong>, who book trips in the mobile app. Driver accounts are created here in the console (not via the rider app). A dedicated <code class="vp-inline-code">drivers</code> table and CRUD will land in a future migration; this screen anchors the navigation structure.</p>
</header>

<section class="vp-card vp-card--note" aria-labelledby="drivers-placeholder-h">
  <div class="vp-card__pad">
    <h2 id="drivers-placeholder-h" class="vp-section-title">Coming next</h2>
    <ul class="vp-doc-list">
      <li><strong>Driver profiles:</strong> name, phone, license, status (active / suspended), linked vehicle.</li>
      <li><strong>Onboarding:</strong> invite or create credentials, optional welcome email (same outbound settings as Settings → Email).</li>
      <li><strong>Assignments:</strong> tie drivers to bookings and schedules once dispatch logic is connected.</li>
    </ul>
    <p class="vp-field-hint" style="margin:1rem 0 0;">Until then, use <a href="<?= vp_h(vp_url('/admin/fleet')) ?>">Car management</a> for vehicle placeholders and <a href="<?= vp_h(vp_url('/admin/riders')) ?>">Rider directory</a> for app customers.</p>
  </div>
</section>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
