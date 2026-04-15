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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session.';
    } else {
        $slug = trim((string) ($_POST['slug'] ?? ''));
        $plabel = trim((string) ($_POST['plabel'] ?? ''));
        $cat = trim((string) ($_POST['category'] ?? 'general'));
        if ($slug === '' || $plabel === '') {
            $error = 'Key and label are required.';
        } else {
            try {
                $rbac->createPermission($slug, $plabel, $cat);
                $message = 'Permission added. Assign it to roles from each role’s edit screen.';
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$perms = $rbac->listPermissions();

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Permission catalog · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'rbac';
$vpTopbarTitle = 'Permissions';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<header class="vp-page-hero vp-page-hero--editor">
  <a class="vp-back" href="<?= vp_url('/admin/rbac') ?>"><span class="vp-back__arrow">←</span> Roles</a>
  <h1 class="vp-page-title">Permission catalog</h1>
  <p class="vp-page-desc">New keys appear in role editors immediately. The mobile app and public APIs do not use these — they only gate the console.</p>
</header>

<?php if ($message !== '') { ?>
  <div class="vp-alert vp-alert--success" role="status"><?= vp_h($message) ?></div>
<?php } ?>
<?php if ($error !== '') { ?>
  <div class="vp-alert vp-alert--error" role="alert"><?= vp_h($error) ?></div>
<?php } ?>

<section class="vp-card">
  <div class="vp-card__pad">
    <h2 class="vp-section-title">Add permission</h2>
    <form method="post" action="<?= vp_url('/admin/rbac/permissions') ?>" class="vp-stack-form" style="max-width:36rem;">
      <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
      <div class="vp-field">
        <label class="vp-label" for="slug">Key</label>
        <input class="vp-input vp-input--mono" id="slug" name="slug" required placeholder="e.g. billing.view" autocomplete="off">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="plabel">Label</label>
        <input class="vp-input" id="plabel" name="plabel" required placeholder="Human-readable title" autocomplete="off">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="category">Category</label>
        <input class="vp-input" id="category" name="category" value="general" placeholder="Group in UI" autocomplete="off">
      </div>
      <button type="submit" class="vp-btn vp-btn--primary">Add permission</button>
    </form>
  </div>
</section>

<section class="vp-card vp-card--note">
  <div class="vp-card__pad">
    <h2 class="vp-section-title">All keys</h2>
    <div class="vp-table-wrap">
      <table class="vp-table">
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
