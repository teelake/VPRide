<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';

use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;

Config::load($backendRoot . '/.env');
Auth::startSession();

$error = '';
$notice = '';
if (isset($_GET['reset']) && (string) $_GET['reset'] === '1') {
    $notice = 'Password updated. Sign in with your new password.';
}

if (Auth::currentAdmin() !== null) {
    header('Location: ' . Config::url('/admin/dashboard'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session. Refresh and try again.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($email === '' || $password === '') {
            $error = 'Email and password required.';
        } elseif (Auth::login(Database::pdo(), $email, $password)) {
            header('Location: ' . Config::url('/admin/dashboard'));
            exit;
        } else {
            $error = 'Invalid credentials.';
        }
    }
}

$csrf = Auth::csrfToken();
header('Content-Type: text/html; charset=utf-8');

$pageTitle = 'Sign in · VP Ride Console';
$bodyClass = 'vp-body vp-body--login';
require __DIR__ . '/includes/head.php';
?>

<a class="vp-skip-link" href="#login-main">Skip to sign in</a>
<div class="vp-login">
  <div class="vp-login__layout">
    <?php require __DIR__ . '/includes/login_aside.php'; ?>
    <div class="vp-login__main" id="login-main" tabindex="-1">
      <div class="vp-login__card" role="region" aria-labelledby="login-heading">
        <div class="vp-login__inner">
          <div class="vp-login__brand" aria-hidden="true">
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
          <p class="vp-login__kicker">Sign in</p>
          <h1 class="vp-login__title" id="login-heading">Console access</h1>
          <p class="vp-login__lead">Regions, riders, rides, and operations — in one place.</p>

      <?php if ($notice !== '') { ?>
        <div class="vp-alert vp-alert--success" role="status"><?= vp_h($notice) ?></div>
      <?php } ?>
      <?php if ($error !== '') { ?>
        <div class="vp-alert vp-alert--error" role="alert"><?= vp_h($error) ?></div>
      <?php } ?>

      <form method="post" action="<?= vp_h(Config::url('/admin/login')) ?>" class="vp-login__form">
        <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
        <div class="vp-field">
          <label class="vp-label" for="email">Work email</label>
          <input class="vp-input" id="email" type="email" name="email" required autocomplete="username" inputmode="email" placeholder="you@company.com" autocapitalize="off" autocorrect="off" spellcheck="false">
        </div>
        <div class="vp-field">
          <label class="vp-label" for="password">Password</label>
          <input class="vp-input" id="password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
        </div>
        <button type="submit" class="vp-btn vp-btn--primary vp-login__submit">Sign in</button>
      </form>
      <p class="vp-login__forgot"><a href="<?= vp_h(Config::url('/admin/forgot-password')) ?>">Forgot password?</a></p>
        </div>
      </div>
    </div>
  </div>
  <p class="vp-login__foot">VP Ride operations console</p>
</div>

<?php require __DIR__ . '/includes/foot_login.php'; ?>
