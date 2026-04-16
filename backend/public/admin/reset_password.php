<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/AdminUserRepository.php';
require_once $backendRoot . '/src/AdminPasswordResetRepository.php';

use VprideBackend\AdminPasswordResetRepository;
use VprideBackend\AdminUserRepository;
use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;

Config::load($backendRoot . '/.env');
Auth::startSession();

if (Auth::currentAdmin() !== null) {
    header('Location: ' . Config::url('/admin/dashboard'));
    exit;
}

$error = '';
$csrf = Auth::csrfToken();
$tokenIn = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$validRow = null;
try {
    $resets = new AdminPasswordResetRepository(Database::pdo());
    if ($tokenIn !== '') {
        $validRow = $resets->findValidRowByRawToken($tokenIn);
    }
} catch (Throwable $e) {
    error_log('VP Ride reset-password: ' . $e->getMessage());
    $error = 'Password reset is unavailable. Ensure the database migration has been applied.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session. Refresh and try again.';
    } elseif ($validRow === null) {
        $error = 'This reset link is invalid or has expired. Request a new one from the sign-in page.';
    } else {
        $p1 = (string) ($_POST['password'] ?? '');
        $p2 = (string) ($_POST['password2'] ?? '');
        if ($p1 !== $p2) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $users = new AdminUserRepository(Database::pdo());
                $users->setPasswordFromReset($validRow['admin_id'], $p1);
                $resets->markUsed($validRow['id']);
                header('Location: ' . Config::url('/admin/login?reset=1'));
                exit;
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Set new password · VP Ride Console';
$bodyClass = 'vp-body vp-body--login';
require __DIR__ . '/includes/head.php';
?>

<div class="vp-login">
  <div class="vp-login__card">
    <div class="vp-login__accent" aria-hidden="true"></div>
    <div class="vp-login__inner">
      <div class="vp-login__brand">
        <img
          class="vp-login__brand-icon"
          src="<?= vp_url('/admin/assets/brand/app_icon_squircle.png') ?>"
          width="72"
          height="72"
          alt=""
          decoding="async"
        >
        <img
          class="vp-login__brand-wordmark"
          src="<?= vp_url('/admin/assets/brand/logo_horizontal_light_bg.png') ?>"
          width="200"
          height="48"
          alt="VP Ride"
          decoding="async"
        >
      </div>
      <p class="vp-login__kicker">VP Ride</p>
      <h1 class="vp-login__title">Choose a new password</h1>

      <?php if ($error !== '') { ?>
        <div class="vp-alert vp-alert--error" role="alert"><?= vp_h($error) ?></div>
      <?php } ?>

      <?php if ($validRow === null && $error === '' && $_SERVER['REQUEST_METHOD'] !== 'POST') { ?>
        <p class="vp-login__lead" style="margin-bottom:1rem;">This reset link is invalid or has expired.</p>
      <?php } elseif ($validRow !== null) { ?>
        <p class="vp-login__lead" style="margin-bottom:1rem;">Use at least 8 characters.</p>
        <form method="post" action="<?= vp_h(Config::url('/admin/reset-password')) ?>" class="vp-login__form">
          <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
          <input type="hidden" name="token" value="<?= vp_h($tokenIn) ?>">
          <div class="vp-field">
            <label class="vp-label" for="password">New password</label>
            <input class="vp-input" id="password" type="password" name="password" required autocomplete="new-password" minlength="8" placeholder="••••••••">
          </div>
          <div class="vp-field">
            <label class="vp-label" for="password2">Confirm password</label>
            <input class="vp-input" id="password2" type="password" name="password2" required autocomplete="new-password" minlength="8" placeholder="••••••••">
          </div>
          <button type="submit" class="vp-btn vp-btn--primary" style="width:100%; margin-top:0.25rem;">Update password</button>
        </form>
      <?php } ?>

      <p class="vp-login__back" style="margin-top:1.25rem; text-align:center;">
        <a href="<?= vp_h(Config::url('/admin/login')) ?>" class="vp-login__back-link">Back to sign in</a>
        <?php if ($validRow === null) { ?>
          · <a href="<?= vp_h(Config::url('/admin/forgot-password')) ?>" class="vp-login__back-link">Request new link</a>
        <?php } ?>
      </p>
    </div>
  </div>
  <p class="vp-login__foot">VP Ride operations console</p>
</div>

<?php require __DIR__ . '/includes/foot_login.php'; ?>
