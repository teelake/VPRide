<?php

declare(strict_types=1);

/** @var array{0:int,1:string,2:string} $admin */
/** @var string $csrf */
/**
 * @var string|null $vpNavActive
 *   dashboard | bookings | schedule | fleet | users | reports | settings | help |
 *   regions | region_new | rbac | account | region_edit
 */
/** @var string|null $vpTopbarTitle Optional short label for the top bar */

use VprideBackend\Auth;

$vpNavActive = $vpNavActive ?? '';
$vpTopbarTitle = isset($vpTopbarTitle) && $vpTopbarTitle !== ''
    ? $vpTopbarTitle
    : 'Dashboard';
$initials = vp_admin_initials($admin[1]);

?>
<div class="vp-app" data-vp-app>
  <a class="vp-skip-link" href="#vp-main-content">Skip to content</a>
  <div class="vp-app__backdrop" data-vp-sidebar-backdrop hidden aria-hidden="true"></div>
  <aside class="vp-sidebar" id="vp-sidebar" data-vp-sidebar aria-label="Main navigation">
    <div class="vp-sidebar__head">
      <a href="<?= vp_url('/admin/dashboard') ?>" class="vp-sidebar__brand" aria-label="VP Ride — Admin home">
        <img
          class="vp-brand-logo"
          src="<?= vp_url('/admin/assets/brand/logo_horizontal_light_bg.png') ?>"
          width="168"
          height="40"
          alt="VP Ride"
          decoding="async"
          loading="lazy"
        >
        <span class="vp-sidebar__brand-tag vp-sidebar__brand-tag--below">Console</span>
      </a>
    </div>
    <nav class="vp-sidebar__nav" aria-label="Sections">
      <ul class="vp-sidebar__list">
        <?php if (Auth::can('dashboard.view')) { ?>
          <li>
            <a href="<?= vp_url('/admin/dashboard') ?>" class="vp-nav-item<?= $vpNavActive === 'overview' || $vpNavActive === 'dashboard' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'overview' || $vpNavActive === 'dashboard' ? ' aria-current="page"' : '' ?>>
              <span class="vp-nav-item__icon" aria-hidden="true"><?= vp_nav_icon_overview() ?></span>
              <span class="vp-nav-item__text">Dashboard</span>
            </a>
          </li>
        <?php } ?>
        <?php if (Auth::can('rides.view')) { ?>
          <li>
            <a href="<?= vp_url('/admin/rides') ?>" class="vp-nav-item<?= $vpNavActive === 'rides' || $vpNavActive === 'bookings' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'rides' || $vpNavActive === 'bookings' ? ' aria-current="page"' : '' ?>>
              <span class="vp-nav-item__icon" aria-hidden="true"><?= vp_nav_icon_rides() ?></span>
              <span class="vp-nav-item__text">Bookings</span>
            </a>
          </li>
          <li>
            <a href="<?= vp_url('/admin/schedule') ?>" class="vp-nav-item<?= $vpNavActive === 'schedule' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'schedule' ? ' aria-current="page"' : '' ?>>
              <span class="vp-nav-item__icon" aria-hidden="true"><?= vp_nav_icon_schedule() ?></span>
              <span class="vp-nav-item__text">Schedule</span>
            </a>
          </li>
          <li>
            <a href="<?= vp_url('/admin/fleet') ?>" class="vp-nav-item<?= $vpNavActive === 'fleet' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'fleet' ? ' aria-current="page"' : '' ?>>
              <span class="vp-nav-item__icon" aria-hidden="true"><?= vp_nav_icon_fleet() ?></span>
              <span class="vp-nav-item__text">Car management</span>
            </a>
          </li>
        <?php } ?>
        <?php if (Auth::can('riders.view') || Auth::can('team.view')) { ?>
          <li>
            <a href="<?= vp_url('/admin/users') ?>" class="vp-nav-item<?= $vpNavActive === 'users' || $vpNavActive === 'riders' || $vpNavActive === 'team' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'users' || $vpNavActive === 'riders' || $vpNavActive === 'team' ? ' aria-current="page"' : '' ?>>
              <span class="vp-nav-item__icon" aria-hidden="true"><?= vp_nav_icon_users_hub() ?></span>
              <span class="vp-nav-item__text">Users</span>
            </a>
          </li>
        <?php } ?>
        <?php if (Auth::can('reports.view')) { ?>
          <li>
            <a href="<?= vp_url('/admin/reports/rides') ?>" class="vp-nav-item<?= $vpNavActive === 'reports' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'reports' ? ' aria-current="page"' : '' ?>>
              <span class="vp-nav-item__icon" aria-hidden="true"><?= vp_nav_icon_reports() ?></span>
              <span class="vp-nav-item__text">Reports</span>
            </a>
          </li>
        <?php } ?>
        <?php if (Auth::can('settings.manage')) { ?>
          <li>
            <a href="<?= vp_url('/admin/settings') ?>" class="vp-nav-item<?= $vpNavActive === 'settings' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'settings' ? ' aria-current="page"' : '' ?>>
              <span class="vp-nav-item__icon" aria-hidden="true"><?= vp_nav_icon_settings() ?></span>
              <span class="vp-nav-item__text">Settings</span>
            </a>
          </li>
        <?php } ?>
        <li>
          <a href="<?= vp_url('/admin/help') ?>" class="vp-nav-item<?= $vpNavActive === 'help' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'help' ? ' aria-current="page"' : '' ?>>
            <span class="vp-nav-item__icon" aria-hidden="true"><?= vp_nav_icon_help() ?></span>
            <span class="vp-nav-item__text">Help &amp; support</span>
          </a>
        </li>
      </ul>

      <?php if (Auth::can('regions.view') || Auth::can('regions.manage')) { ?>
        <p class="vp-sidebar__section-label">Regions &amp; coverage</p>
        <ul class="vp-sidebar__list">
          <?php if (Auth::can('regions.view')) { ?>
            <li>
              <a href="<?= vp_url('/admin/regions') ?>" class="vp-nav-item<?= $vpNavActive === 'regions' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'regions' ? ' aria-current="page"' : '' ?>>
                <span class="vp-nav-item__icon" aria-hidden="true"><?= vp_nav_icon_regions() ?></span>
                <span class="vp-nav-item__text">Regions</span>
              </a>
            </li>
          <?php } ?>
          <?php if (Auth::can('regions.manage')) { ?>
            <li>
              <a href="<?= vp_url('/admin/region/new') ?>" class="vp-nav-item<?= $vpNavActive === 'region_new' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'region_new' ? ' aria-current="page"' : '' ?>>
                <span class="vp-nav-item__icon" aria-hidden="true"><?= vp_nav_icon_plus() ?></span>
                <span class="vp-nav-item__text">New draft</span>
              </a>
            </li>
          <?php } ?>
        </ul>
      <?php } ?>

      <?php if (Auth::can('rbac.manage')) { ?>
        <p class="vp-sidebar__section-label">Administration</p>
        <ul class="vp-sidebar__list">
          <li>
            <a href="<?= vp_url('/admin/rbac') ?>" class="vp-nav-item<?= $vpNavActive === 'rbac' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'rbac' ? ' aria-current="page"' : '' ?>>
              <span class="vp-nav-item__icon" aria-hidden="true"><?= vp_nav_icon_rbac() ?></span>
              <span class="vp-nav-item__text">Roles &amp; access</span>
            </a>
          </li>
        </ul>
      <?php } ?>
    </nav>
    <div class="vp-sidebar__foot vp-sidebar__foot--minimal" aria-hidden="true"></div>
  </aside>

  <div class="vp-app__main">
    <header class="vp-topbar">
      <div class="vp-topbar__left">
        <button type="button" class="vp-icon-btn vp-sidebar-toggle" data-vp-sidebar-open aria-expanded="false" aria-controls="vp-sidebar" title="Open menu">
          <span class="vp-sr-only">Open navigation menu</span>
          <?= vp_nav_icon_menu() ?>
        </button>
        <div class="vp-topbar__titles">
          <span class="vp-topbar__kicker">VP Ride</span>
          <span class="vp-topbar__title"><?= vp_h($vpTopbarTitle) ?></span>
        </div>
      </div>
      <div class="vp-topbar__right">
        <details class="vp-profile" data-vp-profile>
          <summary class="vp-profile__summary">
            <span class="vp-profile__avatar" aria-hidden="true"><?= vp_h($initials) ?></span>
            <span class="vp-profile__meta">
              <span class="vp-profile__email"><?= vp_h($admin[1]) ?></span>
              <span class="vp-profile__role"><?= vp_h(str_replace('_', ' ', $admin[2])) ?></span>
            </span>
            <span class="vp-profile__chev" aria-hidden="true"><?= vp_nav_icon_chevron() ?></span>
          </summary>
          <div class="vp-profile__panel">
            <div class="vp-profile__panel-head">
              <span class="vp-profile__panel-email"><?= vp_h($admin[1]) ?></span>
              <span class="vp-pill vp-pill--dark"><?= vp_h($admin[2]) ?></span>
            </div>
            <div class="vp-profile__actions">
              <a class="vp-profile__account-link" href="<?= vp_url('/admin/account') ?>">Account settings</a>
            </div>
            <form method="post" action="<?= vp_url('/admin/logout') ?>" class="vp-profile__logout">
              <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
              <button type="submit" class="vp-btn vp-btn--ghost vp-btn--block">Log out</button>
            </form>
          </div>
        </details>
      </div>
    </header>

    <main class="vp-main vp-main--shell" id="vp-main-content" tabindex="-1">
      <div class="vp-container vp-container--main">
