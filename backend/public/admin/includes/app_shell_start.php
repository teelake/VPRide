<?php

declare(strict_types=1);

/** @var array{0:int,1:string,2:string} $admin */
/** @var string $csrf */
/**
 * @var string|null $vpNavActive
 *   dashboard | bookings | schedule | fleet | riders | drivers | team | users | reports | settings | help |
 *   regions | region_new | rbac | account | region_edit
 */
/** @var string|null $vpTopbarTitle Optional short label for the top bar */

use VprideBackend\Auth;

$vpNavActive = $vpNavActive ?? '';
$vpTopbarTitle = isset($vpTopbarTitle) && $vpTopbarTitle !== ''
    ? $vpTopbarTitle
    : 'Dashboard';
if (! isset($admin) || ! is_array($admin) || count($admin) < 3) {
    $hydrated = Auth::currentAdmin();
    $admin = is_array($hydrated) ? $hydrated : [0, '', ''];
}
$adminEmail = isset($admin[1]) && is_string($admin[1]) ? $admin[1] : '';
$initials = vp_admin_initials($adminEmail);
$profileDisplayName = $adminEmail !== '' ? vp_admin_profile_display_name($adminEmail) : '';
$vpSearchPlaceholder = Auth::can('riders.view')
    ? 'Search riders by email…'
    : (Auth::can('rides.view') ? 'Jump to bookings…' : 'Search…');

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
        <?php if (Auth::can('promotions.manage')) { ?>
          <li>
            <a href="<?= vp_url('/admin/promotions') ?>" class="vp-nav-item<?= $vpNavActive === 'promotions' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'promotions' ? ' aria-current="page"' : '' ?>>
              <span class="vp-nav-item__icon" aria-hidden="true">%</span>
              <span class="vp-nav-item__text">Promotions</span>
            </a>
          </li>
        <?php } ?>
        <?php if (Auth::can('sos.view')) { ?>
          <li>
            <a href="<?= vp_url('/admin/sos') ?>" class="vp-nav-item<?= $vpNavActive === 'sos' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'sos' ? ' aria-current="page"' : '' ?>>
              <span class="vp-nav-item__icon" aria-hidden="true">!</span>
              <span class="vp-nav-item__text">SOS</span>
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

      <?php if (Auth::can('riders.view')) { ?>
        <p class="vp-sidebar__section-label">Riders</p>
        <ul class="vp-sidebar__list">
          <li>
            <a href="<?= vp_url('/admin/riders') ?>" class="vp-nav-item<?= $vpNavActive === 'riders' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'riders' ? ' aria-current="page"' : '' ?>>
              <span class="vp-nav-item__icon" aria-hidden="true"><?= vp_nav_icon_riders() ?></span>
              <span class="vp-nav-item__text">Rider directory</span>
            </a>
          </li>
        </ul>
      <?php } ?>

      <?php if (Auth::can('rides.view')) { ?>
        <p class="vp-sidebar__section-label">Drivers</p>
        <ul class="vp-sidebar__list">
          <li>
            <a href="<?= vp_url('/admin/drivers') ?>" class="vp-nav-item<?= $vpNavActive === 'drivers' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'drivers' ? ' aria-current="page"' : '' ?>>
              <span class="vp-nav-item__icon" aria-hidden="true"><?= vp_nav_icon_drivers() ?></span>
              <span class="vp-nav-item__text">Driver directory</span>
            </a>
          </li>
          <li>
            <a href="<?= vp_url('/admin/fleet') ?>" class="vp-nav-item<?= $vpNavActive === 'fleet' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'fleet' ? ' aria-current="page"' : '' ?>>
              <span class="vp-nav-item__icon" aria-hidden="true"><?= vp_nav_icon_fleet() ?></span>
              <span class="vp-nav-item__text">Car management</span>
            </a>
          </li>
        </ul>
      <?php } ?>

      <?php if (Auth::can('team.view')) { ?>
        <p class="vp-sidebar__section-label">Console team</p>
        <ul class="vp-sidebar__list">
          <li>
            <a href="<?= vp_url('/admin/team') ?>" class="vp-nav-item<?= $vpNavActive === 'team' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'team' ? ' aria-current="page"' : '' ?>>
              <span class="vp-nav-item__icon" aria-hidden="true"><?= vp_nav_icon_team() ?></span>
              <span class="vp-nav-item__text">Administrators</span>
            </a>
          </li>
        </ul>
      <?php } ?>

      <?php if (Auth::can('rides.view')) { ?>
        <div class="vp-sidebar__cta">
          <a class="vp-sidebar__cta-btn vp-btn vp-btn--primary" href="<?= vp_url('/admin/rides') ?>">
            <span class="vp-sidebar__cta-icon" aria-hidden="true"><?= vp_nav_icon_plus() ?></span>
            <span>Bookings queue</span>
          </a>
        </div>
      <?php } ?>

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
      <div class="vp-topbar__center">
        <?php if (Auth::can('riders.view') || Auth::can('rides.view')) { ?>
          <form class="vp-topbar-search" method="get" action="<?= vp_h(vp_url('/admin/search')) ?>" role="search">
            <label class="vp-sr-only" for="vp-global-search">Search console</label>
            <span class="vp-topbar-search__icon" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.3-4.3"/></svg>
            </span>
            <input
              id="vp-global-search"
              class="vp-topbar-search__input"
              type="search"
              name="q"
              placeholder="<?= vp_h($vpSearchPlaceholder) ?>"
              autocomplete="off"
            >
          </form>
        <?php } ?>
      </div>
      <div class="vp-topbar__right">
        <div class="vp-topbar__tools">
          <a class="vp-icon-btn vp-icon-btn--quiet" href="<?= vp_url('/admin/help') ?>" title="Help &amp; support"><?= vp_nav_icon_bell() ?></a>
          <?php if (Auth::can('settings.manage')) { ?>
            <a class="vp-icon-btn vp-icon-btn--quiet" href="<?= vp_url('/admin/settings') ?>" title="Settings"><?= vp_nav_icon_settings() ?></a>
          <?php } ?>
        </div>
        <details class="vp-profile" data-vp-profile>
          <summary class="vp-profile__summary"<?= $adminEmail !== '' ? ' title="' . vp_h($adminEmail) . '"' : '' ?> aria-label="<?= vp_h($profileDisplayName !== '' ? $profileDisplayName . ', ' . str_replace('_', ' ', $admin[2]) : 'Account menu') ?>">
            <span class="vp-profile__avatar" aria-hidden="true"><?= vp_h($initials) ?></span>
            <span class="vp-profile__meta">
              <span class="vp-profile__role"><?= vp_h(str_replace('_', ' ', $admin[2])) ?></span>
            </span>
            <span class="vp-profile__chev" aria-hidden="true"><?= vp_nav_icon_chevron() ?></span>
          </summary>
          <div class="vp-profile__panel">
            <div class="vp-profile__panel-head">
              <div class="vp-profile__panel-user">
                <span class="vp-profile__panel-avatar" aria-hidden="true"><?= vp_h($initials) ?></span>
                <div class="vp-profile__panel-user-text">
                  <span class="vp-profile__panel-name"><?= vp_h($profileDisplayName) ?></span>
                  <span class="vp-pill vp-pill--dark"><?= vp_h(str_replace('_', ' ', $admin[2])) ?></span>
                </div>
              </div>
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
