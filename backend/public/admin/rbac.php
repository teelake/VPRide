<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/RbacRepository.php';

use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\RbacRepository;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('rbac.manage');

$admin = Auth::currentAdmin();
$rbac = new RbacRepository(Database::pdo());
$csrf = Auth::csrfToken();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_role_id'])) {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session.';
    } else {
        try {
            $rbac->deleteRole((int) $_POST['delete_role_id']);
            $message = 'Role removed.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$roles = $rbac->listRolesWithCounts();
$perms = $rbac->listPermissions();

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Roles & permissions · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'rbac';
$vpTopbarTitle = 'Access control';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<header class="vp-page-hero">
  <h1 class="vp-page-title">Roles & permissions</h1>
  <p class="vp-page-desc">Define who can open each area of the console. Built-in roles are fixed; add custom roles for your organization.</p>
</header>

<?php if ($message !== '') { ?>
  <div class="vp-alert vp-alert--success" role="status"><?= vp_h($message) ?></div>
<?php } ?>
<?php if ($error !== '') { ?>
  <div class="vp-alert vp-alert--error" role="alert"><?= vp_h($error) ?></div>
<?php } ?>

<div class="vp-toolbar">
  <a class="vp-btn vp-btn--primary" href="<?= vp_url('/admin/rbac/role/new') ?>">New role</a>
  <a class="vp-btn vp-btn--ghost" href="<?= vp_url('/admin/rbac/permissions') ?>">Permission catalog</a>
</div>

<section class="vp-card" aria-labelledby="roles-heading">
  <div class="vp-card__pad">
    <h2 id="roles-heading" class="vp-section-title">Roles</h2>
    <div class="vp-table-wrap">
      <table class="vp-table">
        <thead>
          <tr>
            <th scope="col">Label</th>
            <th scope="col">Key</th>
            <th scope="col">Admins</th>
            <th scope="col">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($roles as $r) { ?>
            <tr>
              <td>
                <strong><?= vp_h((string) $r['label']) ?></strong>
                <?php if ((int) $r['is_superuser'] === 1) { ?>
                  <span class="vp-pill vp-pill--neutral" style="margin-left:0.35rem;">Superuser</span>
                <?php } ?>
              </td>
              <td class="vp-table__mono vp-table__muted"><?= vp_h((string) $r['slug']) ?></td>
              <td><?= (int) $r['admin_count'] ?></td>
              <td>
                <div class="vp-table__actions">
                  <a class="vp-btn vp-btn--inline" href="<?= vp_url('/admin/rbac/role/' . (int) $r['id']) ?>">Edit</a>
                  <?php if ((int) $r['is_system'] !== 1 && (int) $r['admin_count'] === 0) { ?>
                    <form method="post" action="<?= vp_url('/admin/rbac') ?>" class="vp-inline-form" onsubmit="return confirm(<?= vp_confirm_attr('Permanently delete role “' . (string) $r['label'] . '” (' . (string) $r['slug'] . ')? Reassign any admins first.') ?>);">
                      <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
                      <input type="hidden" name="delete_role_id" value="<?= (int) $r['id'] ?>">
                      <button type="submit" class="vp-btn vp-btn--danger-ghost vp-btn--sm">Delete</button>
                    </form>
                  <?php } ?>
                </div>
              </td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<section class="vp-card vp-card--note" id="catalog" aria-labelledby="perm-preview">
  <div class="vp-card__pad">
    <h2 id="perm-preview" class="vp-section-title">Permission catalog (read-only)</h2>
    <p class="vp-page-desc" style="margin-top:-0.5rem;">Add new capability keys on the <a href="<?= vp_h(vp_url('/admin/rbac/permissions')) ?>">permission catalog</a> page. Assign them to roles when editing a role.</p>
    <div class="vp-table-wrap">
      <table class="vp-table vp-table--compact">
        <thead>
          <tr>
            <th scope="col">Key</th>
            <th scope="col">Label</th>
            <th scope="col">Category</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($perms as $p) { ?>
            <tr>
              <td class="vp-table__mono" style="font-size:0.8125rem;"><?= vp_h((string) $p['slug']) ?></td>
              <td><?= vp_h((string) $p['label']) ?></td>
              <td class="vp-table__muted"><?= vp_h((string) $p['category']) ?></td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
