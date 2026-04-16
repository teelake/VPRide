<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Auth.php';

use VprideBackend\Auth;
use VprideBackend\Config;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('rides.view');

$admin = Auth::currentAdmin();
$csrf = Auth::csrfToken();

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Fleet · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'fleet';
$vpTopbarTitle = 'Car management';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<header class="vp-page-hero">
  <h1 class="vp-page-title">Car management</h1>
  <p class="vp-page-desc">Vehicle registry, capacity, and compliance checks. There is no fleet table in the database yet—this screen reserves the navigation slot from your reference design.</p>
</header>

<section class="vp-card vp-card--note" aria-labelledby="fleet-note-h">
  <div class="vp-card__pad">
    <h2 id="fleet-note-h" class="vp-section-title">Next steps</h2>
    <p class="vp-page-desc" style="margin:0;">Define vehicles (VIN or internal IDs), link drivers, and surface maintenance status. We can add a migration and CRUD when you confirm the fields you need.</p>
  </div>
</section>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
