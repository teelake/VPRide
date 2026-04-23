<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/AdminUserRepository.php';
require_once $backendRoot . '/src/AdminPasswordResetRepository.php';
require_once $backendRoot . '/src/Mailer.php';
require_once $backendRoot . '/src/AppSettingsRepository.php';

use VprideBackend\AdminPasswordResetRepository;
use VprideBackend\AdminUserRepository;
use VprideBackend\AppSettingsRepository;
use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\Mailer;

Config::load($backendRoot . '/.env');
Auth::startSession();

if (Auth::currentAdmin() !== null) {
    header('Location: ' . Config::url('/dashboard'));
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
                    $link = Config::absoluteUrl('/reset-password?token=' . rawurlencode($raw));
                    $body = "Reset your VP Ride console password using this link (valid for one hour):\r\n\r\n"
                        . $link
                        . "\r\n\r\nIf you did not request this, you can ignore this email.\r\n";
                    $from = AppSettingsRepository::emailOutboundEffective(Database::pdo())['mailFrom'];
                    $ok = Mailer::sendPlain(
                        $email,
                        'VP Ride — reset your console password',
                        $body,
                        $from !== '' ? $from : null,
                    );
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

<a class="vp-skip-link" href="#login-main">Skip to form</a>
<div class="vp-login">
  <div class="vp-login__layout">
    <?php require __DIR__ . '/includes/login_aside.php'; ?>
    <div class="vp-login__main" id="login-main" tabindex="-1">
      <div class="vp-login__panel" role="region" aria-labelledby="forgot-heading">
          <div class="vp-login__brand" aria-hidden="true">
            <img
              class="vp-login__brand-icon"
              src="<?= vp_url('/assets/brand/app_icon_squircle.png') ?>"
              width="72"
              height="72"
              alt=""
              decoding="async"
            >
          </div>
          <p class="vp-login__kicker">Account</p>
          <h1 class="vp-login__title" id="forgot-heading">Reset password</h1>
          <p class="vp-login__lead">We&rsquo;ll email a one-time link if an account exists for that address.</p>

      <?php if ($error !== '') { ?>
        <div class="vp-alert vp-alert--error" role="alert"><?= vp_h($error) ?></div>
      <?php } elseif ($sent) { ?>
        <div class="vp-alert vp-alert--success" role="status">If that email is registered, you will receive instructions shortly. Check spam folders.</div>
      <?php } ?>

      <?php if (! $sent) { ?>
        <form method="post" action="<?= vp_h(Config::url('/forgot-password')) ?>" class="vp-login__form">
          <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
          <div class="vp-field">
            <label class="vp-label" for="email">Work email</label>
            <input class="vp-input" id="email" type="email" name="email" required autocomplete="email" inputmode="email" placeholder="you@company.com" value="<?= vp_h(trim((string) ($_POST['email'] ?? ''))) ?>" autocapitalize="off" autocorrect="off" spellcheck="false">
          </div>
          <button type="submit" class="vp-btn vp-btn--primary vp-login__submit">Send reset link</button>
        </form>
      <?php } ?>

      <p class="vp-login__back">
        <a href="<?= vp_h(Config::url('/login')) ?>" class="vp-login__back-link">Back to sign in</a>
      </p>
      </div>
    </div>
  </div>
  <p class="vp-login__foot">VP Ride operations console</p>
</div>

<?php require __DIR__ . '/includes/foot_login.php'; ?>
