<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/RiderAuthService.php';
require_once $backendRoot . '/src/RiderPasswordResetRepository.php';

use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\RiderAuthService;
use VprideBackend\RiderPasswordResetRepository;

Config::load($backendRoot . '/.env');

$error = '';
$tokenIn = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$validRow = null;
try {
    $resets = new RiderPasswordResetRepository(Database::pdo());
    if ($tokenIn !== '') {
        $validRow = $resets->findValidRowByRawToken($tokenIn);
    }
} catch (Throwable $e) {
    error_log('[vpride] rider reset-password page: ' . $e->getMessage());
    $error = 'Password reset is unavailable. Ensure the database migration has been applied.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    if ($validRow === null) {
        $error = 'This reset link is invalid or has expired. Request a new one from the app.';
    } else {
        $p1 = (string) ($_POST['password'] ?? '');
        $p2 = (string) ($_POST['password2'] ?? '');
        if ($p1 !== $p2) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $auth = new RiderAuthService(Database::pdo());
                $auth->setPasswordFromReset($validRow['rider_user_id'], $p1);
                $resets->markUsed($validRow['id']);
                header('Location: ' . Config::absoluteUrl('/rider/reset-password?done=1'));
                exit;
            } catch (RuntimeException $e) {
                if ($e->getMessage() === 'password_too_short') {
                    $error = 'Password must be at least 8 characters.';
                } else {
                    $error = 'Could not update password. Try again.';
                }
            } catch (Throwable $e) {
                error_log('[vpride] rider reset-password POST: ' . $e->getMessage());
                $error = 'Could not update password. Try again.';
            }
        }
    }
}

$done = isset($_GET['done']) && (string) $_GET['done'] === '1';

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Set new password · VP Ride';
$bodyClass = 'vp-body vp-body--login';
require $backendRoot . '/public/admin/includes/head.php';
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

      <?php if ($done) { ?>
        <div class="vp-alert vp-alert--success" role="status">Your password was updated. Open the VP Ride app and sign in.</div>
      <?php } elseif ($error !== '') { ?>
        <div class="vp-alert vp-alert--error" role="alert"><?= vp_h($error) ?></div>
      <?php } ?>

      <?php if (! $done && $validRow === null && $error === '' && $_SERVER['REQUEST_METHOD'] !== 'POST') { ?>
        <p class="vp-login__lead" style="margin-bottom:1rem;">This reset link is invalid or has expired.</p>
      <?php } elseif (! $done && $validRow !== null) { ?>
        <p class="vp-login__lead" style="margin-bottom:1rem;">Use at least 8 characters.</p>
        <form method="post" action="<?= vp_h(Config::url('/rider/reset-password')) ?>" class="vp-login__form">
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
        <?php if (! $done && $validRow === null) { ?>
          <span class="vp-login__back-link" style="color: var(--vp-muted, #5c6478);">Use <strong>Forgot password</strong> in the VP Ride app to request a new link.</span>
        <?php } ?>
      </p>
    </div>
  </div>
  <p class="vp-login__foot">VP Ride rider account</p>
</div>

<?php require $backendRoot . '/public/admin/includes/foot_login.php'; ?>
