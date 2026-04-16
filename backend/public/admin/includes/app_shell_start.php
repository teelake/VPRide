<?php

declare(strict_types=1);

/** @var array{0:int,1:string,2:string} $admin */
/** @var string $csrf */
/** @var string|null $vpNavActive Optional: overview | regions | rides | riders | team | settings | reports | rbac | region_new | region_edit */
/** @var string|null $vpTopbarTitle Optional short label for the top bar */

use VprideBackend\Auth;

$vpNavActive = $vpNavActive ?? '';
$vpTopbarTitle = isset($vpTopbarTitle) && $vpTopbarTitle !== ''
    ? $vpTopbarTitle
    : 'Overview';
$initials = vp_admin_initials($admin[1]);

?>
<div class="vp-app" data-vp-app>
  <div class="vp-app__backdrop" data-vp-sidebar-backdrop hidden aria-hidden="true"></div>
  <aside class="vp-sidebar" id="vp-sidebar" data-vp-sidebar aria-label="Main navigation">
    <div class="vp-sidebar__head">
      <a href="<?= vp_url('/admin/dashboard') ?>" class="vp-sidebar__brand" aria-label="VP Ride — Admin home">
        <img
          class="vp-brand-logo"
          src="<?= vp_url('/admin/assets/brand/logo_wordmark_white_on_black.png') ?>"
          width="168"
          height="40"
          alt="VP Ride"
          decoding="async"
          loading="lazy"
        >
        <span class="vp-sidebar__brand-tag vp-sidebar__brand-tag--below">Operations</span>
      </a>
    </div>
    <nav class="vp-sidebar__nav" aria-label="Sections">
      <p class="vp-sidebar__section-label">Operations</p>
      <ul class="vp-sidebar__list">
        <?php if (Auth::can('dashboard.view')) { ?>
          <li>
            <a href="<?= vp_url('/admin/dashboard') ?>" class="vp-nav-item<?= $vpNavActive === 'overview' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'overview' ? ' aria-current="page"' : '' ?>>
              <span class="vp-nav-item__icon" aria-hidden="true"><?= vp_nav_icon_overview() ?></span>
              <span class="vp-nav-item__text">Overview</span>
            </a>
          </li>
        <?php } ?>
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
        <?php if (Auth::can('rides.view')) { ?>
          <li>
            <a href="<?= vp_url('/admin/rides') ?>" class="vp-nav-item<?= $vpNavActive === 'rides' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'rides' ? ' aria-current="page"' : '' ?>>
              <span class="vp-nav-item__icon" aria-hidden="true"><?= vp_nav_icon_rides() ?></span>
              <span class="vp-nav-item__text">Rides</span>
            </a>
          </li>
        <?php } ?>
        <?php if (Auth::can('riders.view')) { ?>
          <li>
            <a href="<?= vp_url('/admin/riders') ?>" class="vp-nav-item<?= $vpNavActive === 'riders' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'riders' ? ' aria-current="page"' : '' ?>>
              <span class="vp-nav-item__icon" aria-hidden="true"><?= vp_nav_icon_riders() ?></span>
              <span class="vp-nav-item__text">Riders</span>
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
        <?php if (Auth::can('team.view')) { ?>
          <li>
            <a href="<?= vp_url('/admin/team') ?>" class="vp-nav-item<?= $vpNavActive === 'team' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'team' ? ' aria-current="page"' : '' ?>>
              <span class="vp-nav-item__icon" aria-hidden="true"><?= vp_nav_icon_team() ?></span>
              <span class="vp-nav-item__text">Team</span>
            </a>
          </li>
        <?php } ?>
      </ul>
      <?php if (Auth::can('settings.manage') || Auth::can('rbac.manage')) { ?>
        <p class="vp-sidebar__section-label">Platform</p>
        <ul class="vp-sidebar__list">
          <?php if (Auth::can('settings.manage')) { ?>
            <li>
              <a href="<?= vp_url('/admin/settings') ?>" class="vp-nav-item<?= $vpNavActive === 'settings' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'settings' ? ' aria-current="page"' : '' ?>>
                <span class="vp-nav-item__icon" aria-hidden="true"><?= vp_nav_icon_settings() ?></span>
                <span class="vp-nav-item__text">App settings</span>
              </a>
            </li>
          <?php } ?>
          <?php if (Auth::can('rbac.manage')) { ?>
            <li>
              <a href="<?= vp_url('/admin/rbac') ?>" class="vp-nav-item<?= $vpNavActive === 'rbac' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'rbac' ? ' aria-current="page"' : '' ?>>
                <span class="vp-nav-item__icon" aria-hidden="true"><?= vp_nav_icon_rbac() ?></span>
                <span class="vp-nav-item__text">Roles &amp; access</span>
              </a>
            </li>
          <?php } ?>
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
            <form method="post" action="<?= vp_url('/admin/logout') ?>" class="vp-profile__logout">
              <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
              <button type="submit" class="vp-btn vp-btn--ghost vp-btn--block">Log out</button>
            </form>
          </div>
        </details>
      </div>
    </header>

    <main class="vp-main vp-main--shell">
      <div class="vp-container vp-container--main">
