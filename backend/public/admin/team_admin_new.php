<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/AdminUserRepository.php';
require_once $backendRoot . '/src/RbacRepository.php';

use VprideBackend\AdminUserRepository;
use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\RbacRepository;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('rbac.manage');

$admin = Auth::currentAdmin();
$csrf = Auth::csrfToken();
$rbac = new RbacRepository(Database::pdo());
$userRepo = new AdminUserRepository(Database::pdo());
$roles = $rbac->listRolesWithCounts();

$message = '';
$error = '';
$emailVal = '';
$roleIdVal = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session.';
    } else {
        $emailVal = trim((string) ($_POST['email'] ?? ''));
        $roleIdVal = (int) ($_POST['role_id'] ?? 0);
        $pass = (string) ($_POST['password'] ?? '');
        $pass2 = (string) ($_POST['password2'] ?? '');
        if ($pass !== $pass2) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $userRepo->create($emailVal, $pass, $roleIdVal);
                header('Location: ' . Config::url('/admin/team'));
                exit;
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'New administrator · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'users';
$vpTopbarTitle = 'New administrator';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<header class="vp-page-hero vp-page-hero--editor">
  <?php
    vp_breadcrumbs([
        ['label' => 'Team', 'href' => vp_url('/admin/team')],
        ['label' => 'New administrator', 'href' => null],
    ]);
?>
  <a class="vp-back" href="<?= vp_url('/admin/team') ?>"><span class="vp-back__arrow">←</span> Team</a>
  <h1 class="vp-page-title">New administrator</h1>
  <p class="vp-page-desc">Create a console login. The person should sign in with this email and password.</p>
</header>

<?php if ($error !== '') { ?>
  <div class="vp-alert vp-alert--error" role="alert"><?= vp_h($error) ?></div>
<?php } ?>

<section class="vp-card">
  <div class="vp-card__pad">
    <form method="post" action="<?= vp_url('/admin/team/new') ?>" class="vp-stack-form" style="max-width:28rem;">
      <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">

      <div class="vp-field">
        <label class="vp-label" for="email">Email</label>
        <input class="vp-input" id="email" name="email" type="email" required autocomplete="off" value="<?= vp_h($emailVal) ?>" placeholder="name@company.com">
      </div>

      <div class="vp-field">
        <label class="vp-label" for="role_id">Role</label>
        <select class="vp-input" id="role_id" name="role_id" required>
          <?php foreach ($roles as $r) { ?>
            <option value="<?= (int) $r['id'] ?>"<?= $roleIdVal === (int) $r['id'] ? ' selected' : '' ?>>
              <?= vp_h((string) $r['label']) ?> (<?= vp_h((string) $r['slug']) ?>)
            </option>
          <?php } ?>
        </select>
        <p class="vp-field-hint">Permissions follow the selected role. Edit roles under <em>Roles &amp; access</em>.</p>
      </div>

      <div class="vp-field">
        <label class="vp-label" for="password">Password</label>
        <input class="vp-input" id="password" name="password" type="password" required autocomplete="new-password" minlength="8" placeholder="At least 8 characters">
      </div>

      <div class="vp-field">
        <label class="vp-label" for="password2">Confirm password</label>
        <input class="vp-input" id="password2" name="password2" type="password" required autocomplete="new-password" minlength="8">
      </div>

      <div class="vp-form-actions" style="border:none; padding-top:0; margin-top:0;">
        <button type="submit" class="vp-btn vp-btn--primary">Create administrator</button>
        <a class="vp-btn vp-btn--ghost" href="<?= vp_url('/admin/team') ?>">Cancel</a>
      </div>
    </form>
  </div>
</section>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
