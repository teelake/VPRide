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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#121212">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,600;0,700;1,600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= vp_url('/admin/assets/admin.css') ?>">
  <title><?= vp_h($pageTitle) ?></title>
</head>
<body class="<?= vp_h($bodyClass) ?>">
  <div class="vp-login">
    <div class="vp-login__card">
      <div class="vp-login__accent" aria-hidden="true"></div>
      <div class="vp-login__inner">
        <p class="vp-login__kicker">VP Ride</p>
        <h1 class="vp-login__title"><?= vp_h($forbiddenTitle) ?></h1>
        <p class="vp-login__lead"><?= vp_h($forbiddenMessage) ?></p>
        <p class="vp-field-hint" style="margin-bottom:1.25rem;">Try signing in again, or ask an owner to grant access.</p>
        <a class="vp-btn vp-btn--primary" style="width:100%; justify-content:center;" href="<?= vp_h(vp_url('/admin/dashboard')) ?>">Back to overview</a>
        <a class="vp-btn vp-btn--ghost" style="width:100%; justify-content:center; margin-top:0.5rem;" href="<?= vp_h(vp_url('/admin/login')) ?>">Sign in again</a>
      </div>
    </div>
  </div>
</body>
</html>
