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

if (! Auth::can('riders.view') && ! Auth::can('team.view') && ! Auth::can('rides.view')) {
    header('Location: ' . Config::url('/admin/dashboard'));
    exit;
}

$admin = Auth::currentAdmin();
$csrf = Auth::csrfToken();

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'People · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'users';
$vpTopbarTitle = 'People';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<header class="vp-page-hero">
  <h1 class="vp-page-title">People &amp; access</h1>
  <p class="vp-page-desc">Shortcuts to <strong>riders</strong> (app customers), <strong>drivers</strong> (operations), and <strong>console administrators</strong>. The sidebar has the same entries under separate menus.</p>
</header>

<div class="vp-hub-grid" role="list">
  <?php if (Auth::can('riders.view')) { ?>
    <a class="vp-hub-card" href="<?= vp_h(vp_url('/admin/riders')) ?>" role="listitem">
      <span class="vp-hub-card__icon" aria-hidden="true"><?= vp_nav_icon_riders() ?></span>
      <span class="vp-hub-card__body">
        <span class="vp-hub-card__title">Rider directory</span>
        <span class="vp-hub-card__desc">Customers who book rides in the mobile app.</span>
      </span>
      <span class="vp-hub-card__chev" aria-hidden="true"><?= vp_nav_icon_chevron_right() ?></span>
    </a>
  <?php } ?>
  <?php if (Auth::can('rides.view')) { ?>
    <a class="vp-hub-card" href="<?= vp_h(vp_url('/admin/drivers')) ?>" role="listitem">
      <span class="vp-hub-card__icon" aria-hidden="true"><?= vp_nav_icon_drivers() ?></span>
      <span class="vp-hub-card__body">
        <span class="vp-hub-card__title">Driver directory</span>
        <span class="vp-hub-card__desc">Onboard owner-operators and company drivers; assign vehicles here.</span>
      </span>
      <span class="vp-hub-card__chev" aria-hidden="true"><?= vp_nav_icon_chevron_right() ?></span>
    </a>
    <a class="vp-hub-card" href="<?= vp_h(vp_url('/admin/fleet')) ?>" role="listitem">
      <span class="vp-hub-card__icon" aria-hidden="true"><?= vp_nav_icon_fleet() ?></span>
      <span class="vp-hub-card__body">
        <span class="vp-hub-card__title">Car management</span>
        <span class="vp-hub-card__desc">Personal cars and company / brand fleet vehicles.</span>
      </span>
      <span class="vp-hub-card__chev" aria-hidden="true"><?= vp_nav_icon_chevron_right() ?></span>
    </a>
  <?php } ?>
  <?php if (Auth::can('team.view')) { ?>
    <a class="vp-hub-card" href="<?= vp_h(vp_url('/admin/team')) ?>" role="listitem">
      <span class="vp-hub-card__icon" aria-hidden="true"><?= vp_nav_icon_team() ?></span>
      <span class="vp-hub-card__body">
        <span class="vp-hub-card__title">Team &amp; admins</span>
        <span class="vp-hub-card__desc">Who can sign in to this console and their roles.</span>
      </span>
      <span class="vp-hub-card__chev" aria-hidden="true"><?= vp_nav_icon_chevron_right() ?></span>
    </a>
  <?php } ?>
</div>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
