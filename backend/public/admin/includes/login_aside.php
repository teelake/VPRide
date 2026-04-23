<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

?>
<aside class="vp-login__aside" aria-hidden="true">
  <div class="vp-login__aside-bg" aria-hidden="true"></div>
  <div class="vp-login__aside-content">
    <img
      class="vp-login__aside-logo"
      src="<?= vp_url('/assets/brand/logo_horizontal_yellow_on_black.png') ?>"
      width="220"
      height="52"
      alt=""
      loading="eager"
      decoding="async"
    >
    <p class="vp-login__aside-eyebrow">VP Ride · Console</p>
    <p class="vp-login__aside-line vp-login__aside-line--long">
      Command center for regions, riders, drivers, and fleet. Authorized staff only.
    </p>
    <!--
      Illustration: inline SVG, original to this file (use freely in this project).
      Free alternatives: undraw.co, Blush (Humaaans), Unsplash, Mixkit, Openclipart, Wikimedia Commons (check each file’s license).
    -->
    <div class="vp-login__illustration" role="presentation">
      <svg viewBox="0 0 400 200" width="100%" height="auto" focusable="false" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <linearGradient id="vp-login-grad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" style="stop-color:#ffc300;stop-opacity:0.5"/>
            <stop offset="100%" style="stop-color:#ffc300;stop-opacity:0.08"/>
          </linearGradient>
        </defs>
        <!-- Map / network — abstract lines, mobility ops -->
        <rect x="0" y="0" width="400" height="200" fill="url(#vp-login-grad)" opacity="0.25" rx="12"/>
        <path d="M40 150 Q120 50 200 100 T360 60" fill="none" stroke="#ffc300" stroke-width="1.2" stroke-opacity="0.45" stroke-linecap="round"/>
        <path d="M50 120 L100 80 L180 100 L250 50 L360 90" fill="none" stroke="rgba(255,255,255,0.12)" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="100" cy="80" r="4" fill="#ffc300" fill-opacity="0.85"/>
        <circle cx="250" cy="50" r="3.5" fill="#fff" fill-opacity="0.35"/>
        <circle cx="180" cy="100" r="3" fill="#ffc300" fill-opacity="0.5"/>
        <rect x="300" y="30" width="64" height="40" rx="6" fill="none" stroke="rgba(255,195,0,0.25)" stroke-width="1"/>
        <rect x="308" y="40" width="20" height="3" rx="1" fill="rgba(255,195,0,0.4)"/>
        <rect x="308" y="48" width="48" height="2" rx="1" fill="rgba(255,255,255,0.1)"/>
        <rect x="308" y="55" width="40" height="2" rx="1" fill="rgba(255,255,255,0.08)"/>
      </svg>
    </div>
  </div>
</aside>
