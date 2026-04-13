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

if (Auth::currentAdmin() !== null) {
    header('Location: /admin/dashboard');
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
            header('Location: /admin/dashboard');
            exit;
        } else {
            $error = 'Invalid credentials.';
        }
    }
}

$csrf = Auth::csrfToken();
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>VPRide Admin — Login</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 400px; margin: 2rem auto; padding: 0 1rem; }
    label { display: block; margin-top: 1rem; font-weight: 600; }
    input { width: 100%; padding: 0.5rem; margin-top: 0.25rem; box-sizing: border-box; }
    button { margin-top: 1.25rem; padding: 0.6rem 1rem; cursor: pointer; }
    .err { color: #b00020; margin-top: 1rem; }
  </style>
</head>
<body>
  <h1>VPRide Admin</h1>
  <p>Sign in to manage region configuration.</p>
  <?php if ($error !== '') { ?>
    <p class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
  <?php } ?>
  <form method="post" action="/admin/login">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <label>Email
      <input type="email" name="email" required autocomplete="username">
    </label>
    <label>Password
      <input type="password" name="password" required autocomplete="current-password">
    </label>
    <button type="submit">Sign in</button>
  </form>
</body>
</html>
