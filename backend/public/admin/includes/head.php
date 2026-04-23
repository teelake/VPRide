<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$pageTitle = $pageTitle ?? 'VP Ride Admin';
$bodyClass = $bodyClass ?? 'vp-body';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <meta name="theme-color" content="#f2f3f7">
  <meta name="color-scheme" content="light">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,600&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" href="<?= vp_url('/assets/brand/favicon.png') ?>" sizes="32x32">
  <link rel="apple-touch-icon" href="<?= vp_url('/assets/brand/app_icon_squircle.png') ?>" sizes="180x180">
  <link rel="stylesheet" href="<?= vp_url('/assets/admin.css') ?>">
  <title><?= vp_h($pageTitle) ?></title>
</head>
<body class="<?= vp_h($bodyClass) ?>">
