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
Auth::requirePermission('regions.view');

$admin = Auth::currentAdmin();
$canManageRegions = Auth::can('regions.manage');

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManageRegions) {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session.';
    } elseif (isset($_POST['activate_id'])) {
        $id = (int) $_POST['activate_id'];
        try {
            $repo = new RegionRepository(Database::pdo());
            $repo->activate($id);
            $message = "Configuration #{$id} is now live. Mobile apps will pick it up on the next config fetch.";
        } catch (Throwable $e) {
            $error = 'Could not activate: ' . $e->getMessage();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ! $canManageRegions) {
    $error = 'You do not have permission to change the live region.';
}

$repo = new RegionRepository(Database::pdo());
$rows = $repo->listConfigs();
$csrf = Auth::csrfToken();

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Regions · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'regions';
$vpTopbarTitle = 'Region configuration';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<header class="vp-page-hero">
  <h1 class="vp-page-title">Region configuration</h1>
  <p class="vp-page-desc">One active profile is served to all rider apps. Create drafts, refine coverage, then <strong>Go live</strong> when you are ready. Apps pick up the live profile on their next sync.</p>
</header>

<div class="vp-toolbar">
  <?php if ($canManageRegions) { ?>
    <a class="vp-btn vp-btn--primary" href="<?= vp_url('/region/new') ?>">New draft</a>
  <?php } ?>
</div>

<?php if ($message !== '') { ?>
  <div class="vp-alert vp-alert--success" role="status"><?= vp_h($message) ?></div>
<?php } ?>
<?php if ($error !== '') { ?>
  <div class="vp-alert vp-alert--error" role="alert"><?= vp_h($error) ?></div>
<?php } ?>

<section class="vp-card" aria-labelledby="configs-heading">
  <div class="vp-card__pad">
    <h2 id="configs-heading" class="vp-section-title">Configuration versions</h2>
    <?php if ($rows === []) { ?>
      <?php
        vp_empty_state(
            'No region profiles yet',
            'Seed the database or create your first draft. Only one profile can be live at a time; riders pick it up on the next app sync.',
            $canManageRegions
                ? [['label' => 'New draft', 'href' => vp_url('/region/new'), 'variant' => 'primary']]
                : [['label' => 'Back to overview', 'href' => vp_url('/dashboard'), 'variant' => 'ghost']],
        );
?>
      <?php if ($canManageRegions) { ?>
        <p class="vp-field-hint" style="margin-top:1rem; text-align:center;">CLI: <code class="vp-inline-code">php scripts/seed.php</code> (from the backend folder on your machine or CI).</p>
      <?php } ?>
    <?php } else { ?>
      <div class="vp-table-wrap">
        <table class="vp-table">
          <thead>
            <tr>
              <th scope="col">ID</th>
              <th scope="col">Label</th>
              <th scope="col">Status</th>
              <th scope="col">Updated</th>
              <th scope="col">Last edited by</th>
              <th scope="col" class="vp-table__actions-col"><span class="vp-sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r) { ?>
              <tr>
                <td class="vp-table__id"><?= (int) $r['id'] ?></td>
                <td class="vp-table__label"><?= vp_h((string) $r['label']) ?></td>
                <td>
                  <?php if ((int) $r['is_active'] === 1) { ?>
                    <span class="vp-badge-live">Live</span>
                  <?php } else { ?>
                    <span class="vp-badge-draft">Draft</span>
                  <?php } ?>
                </td>
                <td style="color:var(--vp-muted); font-size:0.8125rem;"><?= vp_h((string) $r['updated_at']) ?></td>
                <td class="vp-table__muted" style="font-size:0.8125rem;"><?= vp_h((string) ($r['updated_by_email'] ?? '') ?: '—') ?></td>
                <td class="vp-table__actions-col">
                  <?php if ($canManageRegions) { ?>
                    <?php
                    vp_action_icons_open();
                    vp_action_edit(vp_url('/region/' . (int) $r['id']));
                    if ((int) $r['is_active'] !== 1) {
                        vp_action_publish_form(
                            vp_url('/regions'),
                            $csrf,
                            ['activate_id' => (int) $r['id']],
                            'Make "' . (string) $r['label'] . '" the live region for all rider apps? They will pick this up on the next config sync.',
                            'Go live',
                        );
                    }
                    vp_action_icons_close();
                    ?>
                  <?php } else { ?>
                    <span class="vp-badge-draft">View only</span>
                  <?php } ?>
                </td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    <?php } ?>
  </div>
</section>

<?php if (! $canManageRegions) { ?>
  <div class="vp-readonly-note">
    Only roles with <strong>region management</strong> permission can edit or switch the live profile. Your role is <strong><?= vp_h($admin[2]) ?></strong>.
  </div>
<?php } ?>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
