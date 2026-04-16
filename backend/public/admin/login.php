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
      <h1 class="vp-login__title">Sign in to console</h1>
      <p class="vp-login__lead">Manage regions, riders, rides, reports, and mobile feature flags for the VP Ride platform.</p>

      <?php if ($notice !== '') { ?>
        <div class="vp-alert vp-alert--success" role="status"><?= vp_h($notice) ?></div>
      <?php } ?>
      <?php if ($error !== '') { ?>
        <div class="vp-alert vp-alert--error" role="alert"><?= vp_h($error) ?></div>
      <?php } ?>

      <form method="post" action="<?= vp_h(Config::url('/admin/login')) ?>" class="vp-login__form">
        <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
        <div class="vp-field">
          <label class="vp-label" for="email">Email</label>
          <input class="vp-input" id="email" type="email" name="email" required autocomplete="username" placeholder="you@company.com">
        </div>
        <div class="vp-field">
          <label class="vp-label" for="password">Password</label>
          <input class="vp-input" id="password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
        </div>
        <button type="submit" class="vp-btn vp-btn--primary" style="width:100%; margin-top:0.25rem;">Sign in</button>
      </form>
      <p class="vp-login__forgot"><a href="<?= vp_h(Config::url('/admin/forgot-password')) ?>">Forgot password?</a></p>
    </div>
  </div>
  <p class="vp-login__foot">VP Ride operations console</p>
</div>

<?php require __DIR__ . '/includes/foot_login.php'; ?>
