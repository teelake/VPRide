<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/AdminUserRepository.php';
require_once $backendRoot . '/src/AdminPasswordResetRepository.php';
require_once $backendRoot . '/src/Mailer.php';

use VprideBackend\AdminPasswordResetRepository;
use VprideBackend\AdminUserRepository;
use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\Mailer;

Config::load($backendRoot . '/.env');
Auth::startSession();

if (Auth::currentAdmin() !== null) {
    header('Location: ' . Config::url('/admin/dashboard'));
    exit;
}

$error = '';
$sent = false;
$csrf = Auth::csrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session. Refresh and try again.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } else {
            try {
                $users = new AdminUserRepository(Database::pdo());
                $resets = new AdminPasswordResetRepository(Database::pdo());
                $adminId = $users->findIdByEmail($email);
                if ($adminId !== null) {
                    $raw = $resets->createTokenForAdmin($adminId);
                    $link = Config::absoluteUrl('/admin/reset-password?token=' . rawurlencode($raw));
                    $body = "Reset your VP Ride console password using this link (valid for one hour):\r\n\r\n"
                        . $link
                        . "\r\n\r\nIf you did not request this, you can ignore this email.\r\n";
                    $ok = Mailer::sendPlain($email, 'VP Ride — reset your console password', $body);
                    if (! $ok) {
                        error_log('VP Ride: password reset email failed for ' . $email);
                    }
                }
                $sent = true;
            } catch (Throwable $e) {
                error_log('VP Ride forgot-password: ' . $e->getMessage());
                $error = 'Password reset is unavailable. Ensure the database migration has been applied and mail is configured.';
            }
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Forgot password · VP Ride Console';
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
      <h1 class="vp-login__title">Reset password</h1>
      <p class="vp-login__lead">We will email you a one-time link if an account exists for that address.</p>

      <?php if ($error !== '') { ?>
        <div class="vp-alert vp-alert--error" role="alert"><?= vp_h($error) ?></div>
      <?php } elseif ($sent) { ?>
        <div class="vp-alert vp-alert--success" role="status">If that email is registered, you will receive instructions shortly. Check spam folders.</div>
      <?php } ?>

      <?php if (! $sent) { ?>
        <form method="post" action="<?= vp_h(Config::url('/admin/forgot-password')) ?>" class="vp-login__form">
          <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
          <div class="vp-field">
            <label class="vp-label" for="email">Email</label>
            <input class="vp-input" id="email" type="email" name="email" required autocomplete="email" placeholder="you@company.com" value="<?= vp_h(trim((string) ($_POST['email'] ?? ''))) ?>">
          </div>
          <button type="submit" class="vp-btn vp-btn--primary" style="width:100%; margin-top:0.25rem;">Send reset link</button>
        </form>
      <?php } ?>

      <p class="vp-login__back" style="margin-top:1.25rem; text-align:center;">
        <a href="<?= vp_h(Config::url('/admin/login')) ?>" class="vp-login__back-link">Back to sign in</a>
      </p>
    </div>
  </div>
  <p class="vp-login__foot">VP Ride operations console</p>
</div>

<?php require __DIR__ . '/includes/foot_login.php'; ?>
