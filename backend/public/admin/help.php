<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Auth.php';

use VprideBackend\Auth;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();

$admin = Auth::currentAdmin();
$csrf = Auth::csrfToken();

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Help & support · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'help';
$vpTopbarTitle = 'Help';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<header class="vp-page-hero">
  <h1 class="vp-page-title">Help &amp; support</h1>
  <p class="vp-page-desc">Quick orientation for the VP Ride operations console. For product or hosting issues, contact your technical lead.</p>
</header>

<div class="vp-hub-grid vp-hub-grid--stack">
  <section class="vp-card" aria-labelledby="help-console-h">
    <div class="vp-card__pad">
      <h2 id="help-console-h" class="vp-section-title">Using the console</h2>
      <ul class="vp-help-list">
        <li><strong>Bookings</strong> lists ride requests synced from rider devices.</li>
        <li><strong>Users</strong> groups rider directory and admin team links in one place.</li>
        <li><strong>Regions</strong> controls live routing configuration and drafts.</li>
        <li><strong>Reports</strong> offers filtered exports for rides and riders.</li>
      </ul>
    </div>
  </section>
  <section class="vp-card" aria-labelledby="help-account-h">
    <div class="vp-card__pad">
      <h2 id="help-account-h" class="vp-section-title">Your account</h2>
      <p class="vp-page-desc" style="margin:0 0 1rem;">Update email preferences and password from <a href="<?= vp_h(vp_url('/admin/account')) ?>">Account settings</a> (profile menu, top right).</p>
      <p class="vp-page-desc" style="margin:0;">Sign out from the same menu to end your session on this device.</p>
    </div>
  </section>
</div>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
