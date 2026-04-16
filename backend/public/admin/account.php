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

$admin = Auth::currentAdmin();
$csrf = Auth::csrfToken();
$userRepo = new AdminUserRepository(Database::pdo());
$profile = $userRepo->getProfile($admin[0]);
if ($profile === null) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    $pageTitle = 'Account · VP Ride Console';
    $bodyClass = 'vp-body vp-body--app';
    $vpNavActive = 'account';
    $vpTopbarTitle = 'Account';
    require __DIR__ . '/includes/head.php';
    require __DIR__ . '/includes/app_shell_start.php';
    echo '<p>Account not found.</p>';
    require __DIR__ . '/includes/app_shell_end.php';
    exit;
}

$message = '';
$error = '';
$emailVal = $profile['email'];
$displayVal = $profile['display_name'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session.';
    } else {
        $action = (string) ($_POST['_action'] ?? '');
        if ($action === 'profile') {
            $emailVal = trim((string) ($_POST['email'] ?? ''));
            $displayVal = trim((string) ($_POST['display_name'] ?? ''));
            try {
                $userRepo->updateProfile($admin[0], $emailVal, $displayVal);
                Auth::setSessionEmail($emailVal);
                $message = 'Profile saved.';
                $profile = $userRepo->getProfile($admin[0]) ?? $profile;
                $emailVal = $profile['email'];
                $displayVal = $profile['display_name'] ?? '';
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        } elseif ($action === 'password') {
            $cur = (string) ($_POST['current_password'] ?? '');
            $p1 = (string) ($_POST['new_password'] ?? '');
            $p2 = (string) ($_POST['new_password2'] ?? '');
            if ($p1 !== $p2) {
                $error = 'New passwords do not match.';
            } else {
                try {
                    $userRepo->changePassword($admin[0], $cur, $p1);
                    $message = 'Password updated.';
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Account · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'account';
$vpTopbarTitle = 'Account';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<header class="vp-page-hero">
  <?php
    vp_breadcrumbs([
        ['label' => 'Overview', 'href' => vp_url('/admin/dashboard')],
        ['label' => 'Account', 'href' => null],
    ]);
?>
  <h1 class="vp-page-title">Your account</h1>
  <p class="vp-page-desc">Update how you appear in the console, your sign-in email, and your password.</p>
</header>

<?php if ($message !== '') { ?>
  <div class="vp-alert vp-alert--success" role="status"><?= vp_h($message) ?></div>
<?php } ?>
<?php if ($error !== '') { ?>
  <div class="vp-alert vp-alert--error" role="alert"><?= vp_h($error) ?></div>
<?php } ?>

<section class="vp-card">
  <div class="vp-card__pad">
    <h2 class="vp-section-title">Profile</h2>
    <p class="vp-page-desc" style="margin-top:-0.25rem;">Role: <strong><?= vp_h($profile['role_label']) ?></strong> <span class="vp-table__muted">(<?= vp_h($profile['role_slug']) ?>)</span></p>
    <form method="post" action="<?= vp_url('/admin/account') ?>" class="vp-stack-form" style="max-width:28rem;">
      <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
      <input type="hidden" name="_action" value="profile">
      <div class="vp-field">
        <label class="vp-label" for="display_name">Display name</label>
        <input class="vp-input" id="display_name" name="display_name" type="text" maxlength="255" autocomplete="name" placeholder="Optional" value="<?= vp_h($displayVal) ?>">
        <p class="vp-field-hint">Shown on the team list and for your own reference.</p>
      </div>
      <div class="vp-field">
        <label class="vp-label" for="email">Email</label>
        <input class="vp-input" id="email" name="email" type="email" required autocomplete="email" value="<?= vp_h($emailVal) ?>">
        <p class="vp-field-hint">This is your sign-in address. It must stay unique across administrators.</p>
      </div>
      <button type="submit" class="vp-btn vp-btn--primary">Save profile</button>
    </form>
  </div>
</section>

<section class="vp-card">
  <div class="vp-card__pad">
    <h2 class="vp-section-title">Change password</h2>
    <form method="post" action="<?= vp_url('/admin/account') ?>" class="vp-stack-form" style="max-width:28rem;">
      <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
      <input type="hidden" name="_action" value="password">
      <div class="vp-field">
        <label class="vp-label" for="current_password">Current password</label>
        <input class="vp-input" id="current_password" name="current_password" type="password" required autocomplete="current-password">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="new_password">New password</label>
        <input class="vp-input" id="new_password" name="new_password" type="password" required autocomplete="new-password" minlength="8" placeholder="At least 8 characters">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="new_password2">Confirm new password</label>
        <input class="vp-input" id="new_password2" name="new_password2" type="password" required autocomplete="new-password" minlength="8">
      </div>
      <button type="submit" class="vp-btn vp-btn--primary">Update password</button>
    </form>
  </div>
</section>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
