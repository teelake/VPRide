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
$pageTitle = 'Schedule · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'schedule';
$vpTopbarTitle = 'Schedule';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<header class="vp-page-hero">
  <h1 class="vp-page-title">Schedule</h1>
  <p class="vp-page-desc">Plan shifts, recurring availability, and dispatch windows. This area is not wired to live data yet—use <a href="<?= vp_h(vp_url('/admin/rides')) ?>">Bookings</a> for current ride requests.</p>
</header>

<section class="vp-card vp-card--note" aria-labelledby="schedule-note-h">
  <div class="vp-card__pad">
    <h2 id="schedule-note-h" class="vp-section-title">Coming next</h2>
    <p class="vp-page-desc" style="margin:0;">When you are ready to add scheduling, we can connect calendars, driver assignments, and SLA rules here without changing the rest of the console layout.</p>
  </div>
</section>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
