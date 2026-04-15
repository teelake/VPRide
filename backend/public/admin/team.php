<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/AdminUserRepository.php';

use VprideBackend\AdminUserRepository;
use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('team.view');

$admin = Auth::currentAdmin();
$rows = (new AdminUserRepository(Database::pdo()))->listAll();
$csrf = Auth::csrfToken();

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Team · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'team';
$vpTopbarTitle = 'Team & roles';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<header class="vp-page-hero">
  <h1 class="vp-page-title">Team</h1>
  <p class="vp-page-desc">Console operators. Access is driven by roles configured under <em>Roles &amp; access</em> (system administrators only).</p>
</header>

<section class="vp-card" aria-labelledby="team-heading">
  <div class="vp-card__pad">
    <h2 id="team-heading" class="vp-section-title">Administrators</h2>
    <p class="vp-page-desc" style="margin-top:-0.25rem;">Provision accounts via database or seed scripts today; RBAC defines what each role can open in this console.</p>
    <div class="vp-table-wrap">
      <table class="vp-table">
        <thead>
          <tr>
            <th scope="col">ID</th>
            <th scope="col">Email</th>
            <th scope="col">Role</th>
            <th scope="col">Created</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r) { ?>
            <tr>
              <td class="vp-table__id"><?= (int) $r['id'] ?></td>
              <td><?= vp_h((string) $r['email']) ?></td>
              <td>
                <span class="vp-pill vp-pill--dark"><?= vp_h((string) ($r['role_label'] ?? $r['role'])) ?></span>
                <span class="vp-table__mono vp-table__muted" style="font-size:0.6875rem; display:block; margin-top:0.2rem;"><?= vp_h((string) $r['role']) ?></span>
              </td>
              <td style="color:var(--vp-muted); font-size:0.8125rem;"><?= vp_h((string) $r['created_at']) ?></td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
