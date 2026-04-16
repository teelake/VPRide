<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/RbacRepository.php';

use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\RbacRepository;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('rbac.manage');

$admin = Auth::currentAdmin();
$rbac = new RbacRepository(Database::pdo());
$csrf = Auth::csrfToken();

$isNew = isset($_ROUTE_RBAC_NEW) && $_ROUTE_RBAC_NEW === true;
$roleId = $isNew ? 0 : (int) ($_ROUTE_RBAC_ROLE_ID ?? 0);
$message = '';
$error = '';

$roleRow = $isNew ? null : $rbac->getRoleRow($roleId);
if (! $isNew && $roleRow === null) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    $pageTitle = 'Not found · VP Ride Console';
    $bodyClass = 'vp-body vp-body--app';
    $vpNavActive = 'rbac';
    $vpTopbarTitle = 'Not found';
    require __DIR__ . '/includes/head.php';
    require __DIR__ . '/includes/app_shell_start.php';
    echo '<p class="vp-page-desc"><a class="vp-back" href="' . vp_h(vp_url('/admin/rbac')) . '"><span class="vp-back__arrow">←</span> Roles</a></p>';
    echo '<p>Role not found.</p>';
    require __DIR__ . '/includes/app_shell_end.php';
    exit;
}

$allPerms = $rbac->listPermissions();
$byCat = [];
foreach ($allPerms as $p) {
    $c = (string) $p['category'];
    if (! isset($byCat[$c])) {
        $byCat[$c] = [];
    }
    $byCat[$c][] = $p;
}
$selected = $isNew ? [] : $rbac->permissionIdsForRole($roleId);
$isSuper = $roleRow !== null && (int) $roleRow['is_superuser'] === 1;

$label = $roleRow['label'] ?? '';
$slugInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session.';
    } elseif ($isNew) {
        $label = trim((string) ($_POST['label'] ?? ''));
        $slugInput = trim((string) ($_POST['slug'] ?? ''));
        $slug = $slugInput !== '' ? $rbac->normalizeSlug($slugInput) : $rbac->normalizeSlug($label);
        if ($label === '') {
            $error = 'Label is required.';
        } else {
            try {
                $newId = $rbac->createRole($slug, $label);
                $ids = isset($_POST['perm']) && is_array($_POST['perm']) ? array_map('intval', $_POST['perm']) : [];
                $rbac->setRolePermissions($newId, $ids);
                header('Location: ' . Config::url('/admin/rbac/role/' . $newId));
                exit;
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    } else {
        $label = trim((string) ($_POST['label'] ?? ''));
        if ($label === '') {
            $error = 'Label is required.';
        } else {
            try {
                if ((int) $roleRow['is_system'] === 0) {
                    $rbac->updateRoleLabel($roleId, $label);
                }
                if (! $isSuper) {
                    $ids = isset($_POST['perm']) && is_array($_POST['perm']) ? array_map('intval', $_POST['perm']) : [];
                    $rbac->setRolePermissions($roleId, $ids);
                }
                $message = 'Role saved.';
                $roleRow = $rbac->getRoleRow($roleId);
                $label = (string) ($roleRow['label'] ?? $label);
                $selected = $rbac->permissionIdsForRole($roleId);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$action = $isNew ? vp_url('/admin/rbac/role/new') : vp_url('/admin/rbac/role/' . $roleId);

header('Content-Type: text/html; charset=utf-8');
$pageTitle = ($isNew ? 'New role' : 'Edit role') . ' · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'rbac';
$vpTopbarTitle = $isNew ? 'New role' : 'Edit role';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<header class="vp-page-hero vp-page-hero--editor">
  <a class="vp-back" href="<?= vp_url('/admin/rbac') ?>"><span class="vp-back__arrow">←</span> Roles</a>
  <h1 class="vp-page-title"><?= $isNew ? 'New role' : 'Edit role' ?></h1>
  <p class="vp-page-desc"><?= $isSuper ? 'This role has full console access. Permission checkboxes are not used.' : 'Toggle capabilities for this role. Users must sign out and back in for session changes to apply everywhere.' ?></p>
</header>

<?php if ($message !== '') { ?>
  <div class="vp-alert vp-alert--success" role="status"><?= vp_h($message) ?></div>
<?php } ?>
<?php if ($error !== '') { ?>
  <div class="vp-alert vp-alert--error" role="alert"><?= vp_h($error) ?></div>
<?php } ?>

<form method="post" action="<?= vp_h($action) ?>" class="vp-rbac-form">
  <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">

  <section class="vp-card">
    <div class="vp-card__pad">
      <h2 class="vp-section-title">Basics</h2>
      <div class="vp-field">
        <label class="vp-label" for="label">Display name</label>
        <input class="vp-input" id="label" name="label" required value="<?= vp_h($label) ?>"<?= ((int) ($roleRow['is_system'] ?? 0)) === 1 ? ' readonly' : '' ?>>
        <?php if ((int) ($roleRow['is_system'] ?? 0) === 1) { ?>
          <p class="vp-field-hint">Built-in role names are locked.</p>
        <?php } ?>
      </div>
      <?php if ($isNew) { ?>
        <div class="vp-field">
          <label class="vp-label" for="slug">Key <span class="vp-label-optional">(optional)</span></label>
          <input class="vp-input vp-input--mono" id="slug" name="slug" value="<?= vp_h($slugInput) ?>" placeholder="auto from name" autocomplete="off" pattern="[a-z0-9_]*">
          <p class="vp-field-hint">Lowercase letters, numbers, underscores. Leave blank to derive from the display name.</p>
        </div>
      <?php } else { ?>
        <p class="vp-field-hint">Key: <code class="vp-inline-code"><?= vp_h((string) ($roleRow['slug'] ?? '')) ?></code></p>
      <?php } ?>
    </div>
  </section>

  <?php if (! $isSuper) { ?>
    <section class="vp-card">
      <div class="vp-card__pad">
        <h2 class="vp-section-title">Permissions</h2>
        <?php foreach ($byCat as $cat => $plist) { ?>
          <fieldset class="vp-perm-fieldset">
            <legend class="vp-perm-legend"><?= vp_h($cat) ?></legend>
            <div class="vp-perm-grid">
              <?php foreach ($plist as $p) {
                  $pid = (int) $p['id'];
                  $chk = $isNew ? false : in_array($pid, $selected, true);
                  ?>
                <label class="vp-check-card">
                  <input type="checkbox" name="perm[]" value="<?= $pid ?>"<?= $chk ? ' checked' : '' ?>>
                  <span class="vp-check-card__body">
                    <span class="vp-check-card__title"><?= vp_h((string) $p['label']) ?></span>
                    <span class="vp-check-card__slug"><?= vp_h((string) $p['slug']) ?></span>
                  </span>
                </label>
              <?php } ?>
            </div>
          </fieldset>
        <?php } ?>
      </div>
    </section>
  <?php } ?>

  <div class="vp-form-actions">
    <button type="submit" class="vp-btn vp-btn--primary"><?= $isNew ? 'Create role' : 'Save changes' ?></button>
    <a class="vp-btn vp-btn--ghost" href="<?= vp_url('/admin/rbac') ?>">Cancel</a>
  </div>
</form>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
