<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/RiderUserRepository.php';

use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\RiderUserRepository;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('riders.view');

$admin = Auth::currentAdmin();
$rows = (new RiderUserRepository(Database::pdo()))->listRecent(150);
$csrf = Auth::csrfToken();

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Riders · Pride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'riders';
$vpTopbarTitle = 'Riders';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<h1 class="vp-page-title">Riders</h1>
<p class="vp-page-desc">Google-authenticated accounts with an active or past session.</p>

<section class="vp-card" aria-labelledby="riders-heading">
  <div class="vp-card__pad">
    <h2 id="riders-heading" class="vp-section-title">Directory</h2>
    <?php if ($rows === []) { ?>
      <p class="vp-page-desc" style="margin-bottom:0;">No riders yet.</p>
    <?php } else { ?>
      <div class="vp-table-wrap">
        <table class="vp-table">
          <thead>
            <tr>
              <th scope="col">ID</th>
              <th scope="col">Email</th>
              <th scope="col">Name</th>
              <th scope="col">Google sub</th>
              <th scope="col">Joined</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r) { ?>
              <tr>
                <td class="vp-table__id"><?= (int) $r['id'] ?></td>
                <td><?= vp_h((string) $r['email']) ?></td>
                <td><?= vp_h((string) ($r['display_name'] ?? '—')) ?></td>
                <td class="vp-table__mono vp-table__muted" style="font-size:0.75rem; max-width:12rem; overflow:hidden; text-overflow:ellipsis;"><?= vp_h((string) $r['google_sub']) ?></td>
                <td style="color:var(--vp-muted); font-size:0.8125rem;"><?= vp_h((string) $r['created_at']) ?></td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    <?php } ?>
  </div>
</section>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
