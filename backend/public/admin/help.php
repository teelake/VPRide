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
        <li><strong>Driver directory</strong> and <strong>Car management</strong> (under <strong>Drivers &amp; fleet</strong> in the sidebar) are where you onboard drivers and register vehicles. Owner-operators use <em>personal</em> vehicle rows; company drivers use <em>company / brand</em> fleet rows — assign the matching vehicle when editing a driver.</li>
        <li>The sidebar groups <strong>Riders</strong>, then <strong>Drivers &amp; fleet</strong>, then <strong>Console team</strong>. The <strong>People &amp; access</strong> hub links to the same rider and driver destinations in one place.</li>
        <li><strong>Regions</strong> controls live routing configuration and drafts.</li>
        <li><strong>Reports</strong> offers filtered exports for rides and riders.</li>
      </ul>
    </div>
  </section>
  <section class="vp-card" aria-labelledby="help-account-h">
    <div class="vp-card__pad">
      <h2 id="help-account-h" class="vp-section-title">Your account</h2>
      <p class="vp-page-desc" style="margin:0 0 1rem;">Update email preferences and password from <a href="<?= vp_h(vp_url('/account')) ?>">Account settings</a> (profile menu, top right).</p>
      <p class="vp-page-desc" style="margin:0;">Sign out from the same menu to end your session on this device.</p>
    </div>
  </section>
  <section class="vp-card" aria-labelledby="help-security-h">
    <div class="vp-card__pad">
      <h2 id="help-security-h" class="vp-section-title">Security &amp; performance</h2>
      <ul class="vp-help-list">
        <li><strong>Session &amp; CSRF:</strong> Console actions use your login session and a hidden CSRF token on forms. Do not share admin links that include tokens; use bookmarks to normal URLs only.</li>
        <li><strong>Secrets:</strong> Prefer storing sensitive API keys in server <code class="vp-inline-code">.env</code> when possible; restrict Google keys in Cloud Console (package, bundle, IP).</li>
        <li><strong>URLs in settings:</strong> Help center and welcome background URLs must be <code class="vp-inline-code">http://</code> or <code class="vp-inline-code">https://</code> only — arbitrary schemes are rejected on save.</li>
        <li><strong>Welcome image uploads:</strong> Files are checked with MIME detection and decoded as real images; oversized dimensions and huge pixel counts are blocked. The server re-encodes to WebP (or JPEG) and may downscale very large sources so riders download a smaller asset without visible loss.</li>
        <li><strong>Hosting:</strong> Serve the admin and API over HTTPS in production; keep PHP and extensions updated.</li>
      </ul>
    </div>
  </section>
</div>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
