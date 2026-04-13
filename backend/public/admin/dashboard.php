<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/RegionRepository.php';

use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\RegionRepository;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();

$admin = Auth::currentAdmin();
$isSystemAdmin = $admin[2] === 'system_admin';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isSystemAdmin) {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session.';
    } elseif (isset($_POST['activate_id'])) {
        $id = (int) $_POST['activate_id'];
        try {
            $repo = new RegionRepository(Database::pdo());
            $repo->activate($id);
            $message = "Configuration #{$id} is now active. Mobile apps will receive it on next fetch.";
        } catch (Throwable $e) {
            $error = 'Could not activate: ' . $e->getMessage();
        }
    }
}

$repo = new RegionRepository(Database::pdo());
$rows = $repo->listConfigs();
$csrf = Auth::csrfToken();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>VPRide Admin — Regions</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
    table { border-collapse: collapse; width: 100%; margin-top: 1rem; }
    th, td { border: 1px solid #ccc; padding: 0.5rem 0.75rem; text-align: left; }
    th { background: #f5f5f5; }
    .active { font-weight: bold; color: #0a0; }
    .row { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; margin-top: 1rem; }
    .msg { color: #0a0; }
    .err { color: #b00020; }
    button, .btn { padding: 0.4rem 0.75rem; cursor: pointer; text-decoration: none; display: inline-block; color: inherit; }
    code { background: #f0f0f0; padding: 0.1rem 0.3rem; }
  </style>
</head>
<body>
  <h1>Region configuration</h1>
  <p>Signed in as <?= htmlspecialchars($admin[1], ENT_QUOTES, 'UTF-8') ?>
    (<?= htmlspecialchars($admin[2], ENT_QUOTES, 'UTF-8') ?>).
  </p>
  <div class="row">
    <?php if ($isSystemAdmin) { ?>
      <a class="btn" href="/admin/region/new">New draft config</a>
    <?php } ?>
    <form method="post" action="/admin/logout" style="display:inline;">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <button type="submit">Log out</button>
    </form>
  </div>
  <p>Public API: <code>GET <?= htmlspecialchars((getenv('PUBLIC_BASE_URL') ?: 'http://localhost:8080') . '/api/v1/config/regions', ENT_QUOTES, 'UTF-8') ?></code>
    (set <code>PUBLIC_BASE_URL</code> in <code>.env</code> to match your host).</p>

  <?php if ($message !== '') { ?><p class="msg"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php } ?>
  <?php if ($error !== '') { ?><p class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php } ?>

  <table>
    <thead>
      <tr><th>ID</th><th>Label</th><th>Status</th><th>Updated</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r) { ?>
      <tr>
        <td><?= (int) $r['id'] ?></td>
        <td><?= htmlspecialchars((string) $r['label'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= (int) $r['is_active'] === 1 ? '<span class="active">ACTIVE</span>' : 'draft' ?></td>
        <td><?= htmlspecialchars((string) $r['updated_at'], ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <?php if ($isSystemAdmin) { ?>
            <a href="/admin/region/<?= (int) $r['id'] ?>">Edit JSON</a>
            <?php if ((int) $r['is_active'] !== 1) { ?>
              <form method="post" action="/admin/dashboard" style="display:inline;">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="activate_id" value="<?= (int) $r['id'] ?>">
                <button type="submit">Activate</button>
              </form>
            <?php } ?>
          <?php } else { ?>
            <span>View only</span>
          <?php } ?>
        </td>
      </tr>
    <?php } ?>
    </tbody>
  </table>

  <?php if (! $isSystemAdmin) { ?>
    <p><em>Only <strong>system_admin</strong> can edit or switch the active region.</em></p>
  <?php } ?>
</body>
</html>
