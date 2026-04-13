<?php

declare(strict_types=1);

/** @var array{0:int,1:string,2:string} $admin */
/** @var string $csrf */

?>
<header class="vp-header">
  <div class="vp-header__inner vp-container">
    <a href="/admin/dashboard" class="vp-brand" aria-label="VPRide Admin home">
      <span class="vp-brand__mark" aria-hidden="true"></span>
      <span class="vp-brand__text"><span class="vp-brand__vp">VP</span><span class="vp-brand__ride">Ride</span></span>
      <span class="vp-brand__tag">Console</span>
    </a>
    <nav class="vp-header__nav" aria-label="Account">
      <div class="vp-header__user">
        <span class="vp-header__email"><?= vp_h($admin[1]) ?></span>
        <span class="vp-pill vp-pill--muted"><?= vp_h($admin[2]) ?></span>
      </div>
      <form method="post" action="/admin/logout" class="vp-inline-form">
        <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
        <button type="submit" class="vp-btn vp-btn--ghost vp-btn--sm">Log out</button>
      </form>
    </nav>
  </div>
</header>
<main class="vp-main">
<div class="vp-container vp-container--main">
