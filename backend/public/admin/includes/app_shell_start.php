<?php

declare(strict_types=1);

/** @var array{0:int,1:string,2:string} $admin */
/** @var string $csrf */
/** @var string|null $vpNavActive Optional: 'dashboard' | 'region_new' | 'region_edit' */
/** @var string|null $vpTopbarTitle Optional short label for the top bar */

$vpNavActive = $vpNavActive ?? '';
$vpTopbarTitle = isset($vpTopbarTitle) && $vpTopbarTitle !== ''
    ? $vpTopbarTitle
    : 'Overview';
$isSystemAdmin = $admin[2] === 'system_admin';
$publicBase = getenv('PUBLIC_BASE_URL') ?: '';
$apiPath = '/api/v1/config/regions';
$initials = vp_admin_initials($admin[1]);

?>
<div class="vp-app" data-vp-app>
  <div class="vp-app__backdrop" data-vp-sidebar-backdrop hidden aria-hidden="true"></div>
  <aside class="vp-sidebar" id="vp-sidebar" data-vp-sidebar aria-label="Main navigation">
    <div class="vp-sidebar__head">
      <a href="<?= vp_url('/admin/dashboard') ?>" class="vp-sidebar__brand" aria-label="Pride — Admin home">
        <img
          class="vp-brand-logo"
          src="<?= vp_url('/admin/assets/brand/logo_wordmark_white_on_black.png') ?>"
          width="168"
          height="40"
          alt="Pride"
          decoding="async"
          loading="lazy"
        >
        <span class="vp-sidebar__brand-tag vp-sidebar__brand-tag--below">Console</span>
      </a>
    </div>
    <nav class="vp-sidebar__nav" aria-label="Sections">
      <p class="vp-sidebar__section-label">Operations</p>
      <ul class="vp-sidebar__list">
        <li>
          <a href="<?= vp_url('/admin/dashboard') ?>" class="vp-nav-item<?= $vpNavActive === 'dashboard' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'dashboard' ? ' aria-current="page"' : '' ?>>
            <span class="vp-nav-item__icon" aria-hidden="true"><?= vp_nav_icon_grid() ?></span>
            <span class="vp-nav-item__text">Region profiles</span>
          </a>
        </li>
        <?php if ($isSystemAdmin) { ?>
          <li>
            <a href="<?= vp_url('/admin/region/new') ?>" class="vp-nav-item<?= $vpNavActive === 'region_new' ? ' vp-nav-item--active' : '' ?>"<?= $vpNavActive === 'region_new' ? ' aria-current="page"' : '' ?>>
              <span class="vp-nav-item__icon" aria-hidden="true"><?= vp_nav_icon_plus() ?></span>
              <span class="vp-nav-item__text">New draft</span>
            </a>
          </li>
        <?php } ?>
      </ul>
    </nav>
    <div class="vp-sidebar__foot">
      <?php if ($publicBase !== '') { ?>
        <p class="vp-sidebar__api-hint">
          <span class="vp-sidebar__api-label">Public API</span>
          <code class="vp-sidebar__api-code"><?= vp_h(rtrim($publicBase, '/') . $apiPath) ?></code>
        </p>
      <?php } else { ?>
        <p class="vp-sidebar__api-hint vp-sidebar__api-hint--muted">Set <code>PUBLIC_BASE_URL</code> in <code>.env</code> to show the live config URL here.</p>
      <?php } ?>
    </div>
  </aside>

  <div class="vp-app__main">
    <header class="vp-topbar">
      <div class="vp-topbar__left">
        <button type="button" class="vp-icon-btn vp-sidebar-toggle" data-vp-sidebar-open aria-expanded="false" aria-controls="vp-sidebar" title="Open menu">
          <span class="vp-sr-only">Open navigation menu</span>
          <?= vp_nav_icon_menu() ?>
        </button>
        <div class="vp-topbar__titles">
          <span class="vp-topbar__kicker">Pride</span>
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
