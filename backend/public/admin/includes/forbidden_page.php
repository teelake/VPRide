<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$forbiddenTitle = $forbiddenTitle ?? 'Access denied';
$forbiddenMessage = $forbiddenMessage ?? 'You do not have permission to view this page.';
$pageTitle = ($forbiddenTitle) . ' · VP Ride Console';
$bodyClass = 'vp-body vp-body--login';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <meta name="color-scheme" content="light">
  <meta name="theme-color" content="#f2f3f7">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" href="<?= vp_url('/assets/brand/favicon.png') ?>">
  <link rel="apple-touch-icon" href="<?= vp_url('/assets/brand/app_icon_squircle.png') ?>">
  <link rel="stylesheet" href="<?= vp_url('/assets/admin.css') ?>">
  <title><?= vp_h($pageTitle) ?></title>
</head>
<body class="<?= vp_h($bodyClass) ?>">
  <a class="vp-skip-link" href="#login-main">Skip to content</a>
  <div class="vp-login">
    <div class="vp-login__layout">
      <?php require __DIR__ . '/login_aside.php'; ?>
      <div class="vp-login__main" id="login-main" tabindex="-1">
        <div class="vp-login__panel" role="region" aria-labelledby="forbidden-h">
            <p class="vp-login__kicker">Access</p>
            <h1 class="vp-login__title" id="forbidden-h"><?= vp_h($forbiddenTitle) ?></h1>
            <p class="vp-login__lead"><?= vp_h($forbiddenMessage) ?></p>
            <p class="vp-field-hint" style="margin-bottom:1.25rem; color: var(--vp-muted, #6e6e7a);">Try signing in again, or ask an owner to grant access.</p>
            <a class="vp-btn vp-btn--primary vp-login__submit" href="<?= vp_h(vp_url('/dashboard')) ?>">Back to overview</a>
            <a class="vp-btn vp-btn--ghost vp-login__submit vp-login__submit--stack" href="<?= vp_h(vp_url('/login')) ?>">Sign in again</a>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
