<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$pageTitle = $pageTitle ?? 'VPRide Admin';
$bodyClass = $bodyClass ?? 'vp-body';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#121212">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;1,600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/admin/assets/admin.css">
  <title><?= vp_h($pageTitle) ?></title>
</head>
<body class="<?= vp_h($bodyClass) ?>">
