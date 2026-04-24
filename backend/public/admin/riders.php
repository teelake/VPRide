<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/RiderUserRepository.php';
require_once $backendRoot . '/src/SchemaInspector.php';

use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\RiderUserRepository;
use VprideBackend\SchemaInspector;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('riders.view');

$admin = Auth::currentAdmin();
$csrf = Auth::csrfToken();
$pdo = Database::pdo();
$repo = new RiderUserRepository($pdo);

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = max(10, min(100, (int) ($_GET['per_page'] ?? 30)));
$qParam = $q === '' ? null : $q;
$total = $repo->countFiltered($qParam);
$pages = max(1, (int) ceil($total / $perPage));
if ($page > $pages) {
    $page = $pages;
}
$offset = ($page - 1) * $perPage;
$rows = $repo->listFiltered($qParam, $perPage, $offset);

$qBase = ['q' => $q, 'per_page' => (string) $perPage];
$buildPageUrl = static function (int $p) use ($qBase): string {
    $qq = array_merge($qBase, ['page' => (string) $p]);
    $qq = array_filter($qq, static fn ($v) => $v !== '' && $v !== null);

    return \VprideBackend\Config::url('/riders?' . http_build_query($qq));
};

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Riders · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'riders';
$vpTopbarTitle = 'Riders';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<?php vp_schema_single_table_alert($pdo, 'rider_users', 'migration_rider_auth.sql', 'Rider directory'); ?>

<header class="vp-page-hero">
  <h1 class="vp-page-title">Riders</h1>
  <p class="vp-page-desc">Passenger app accounts (excludes driver-only fleet logins — <code>driver_account_only</code>). Phone is shown when the <code>rider_users.phone</code> column exists (run <code>migration_client_brief_2026.sql</code> for SMS / console-registered riders).</p>
</header>

<div class="vp-toolbar vp-toolbar--split">
  <div class="vp-toolbar__left"></div>
  <div class="vp-toolbar__actions">
    <?php if (Auth::can('reports.view')) { ?>
      <a class="vp-btn vp-btn--primary" href="<?= vp_url('/reports/riders') ?>">Reports &amp; export</a>
    <?php } ?>
    <?php if (Auth::can('settings.manage')) { ?>
      <a class="vp-btn vp-btn--ghost" href="<?= vp_url('/settings') ?>">Mobile features</a>
    <?php } ?>
  </div>
</div>

<section class="vp-card" aria-labelledby="riders-heading">
  <div class="vp-card__pad">
    <div class="vp-card__head-row">
      <h2 id="riders-heading" class="vp-section-title" style="margin:0;">Directory</h2>
      <form method="get" action="<?= vp_h(vp_url('/riders')) ?>" class="vp-inline-search">
        <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
        <label class="vp-sr-only" for="rider-q">Search riders</label>
        <input class="vp-input vp-input--search" id="rider-q" name="q" type="search" value="<?= vp_h($q) ?>" placeholder="Search email, name, phone, ID…" autocomplete="off">
        <button type="submit" class="vp-btn vp-btn--primary vp-btn--sm">Search</button>
      </form>
    </div>
    <?php if ($rows === []) { ?>
      <?php if (! \VprideBackend\SchemaInspector::tableExists($pdo, 'rider_users')) { ?>
        <?php
          vp_empty_state(
              'Rider accounts table missing',
              'Run backend/sql/migration_rider_auth.sql (or the full schema) so Google sign-in can create rider_users rows.',
              [],
          );
        ?>
      <?php } elseif ($q !== '') { ?>
        <?php
          $searchActions = [['label' => 'Clear search', 'href' => vp_url('/riders'), 'variant' => 'ghost']];
          if (Auth::can('reports.view')) {
              array_unshift($searchActions, ['label' => 'Rider reports', 'href' => vp_url('/reports/riders'), 'variant' => 'primary']);
          }
          vp_empty_state(
              'No results for this search',
              'Try another keyword, or open reports to scan the full directory with export.',
              $searchActions,
          );
        ?>
      <?php } else { ?>
        <?php
          vp_empty_state(
              'No riders yet',
              'Profiles are created from the app (Google, email/password) or from a console “new rider” booking.',
              Auth::can('reports.view') ? [['label' => 'Reports & export', 'href' => vp_url('/reports/riders'), 'variant' => 'primary']] : [],
          );
        ?>
      <?php } ?>
    <?php } else { ?>
      <p class="vp-muted-inline" style="margin-bottom:1rem;">Showing <?= number_format(count($rows)) ?> of <?= number_format($total) ?></p>
      <div class="vp-table-wrap">
        <table class="vp-table">
          <thead>
            <tr>
              <th scope="col">ID</th>
              <th scope="col">Email</th>
              <th scope="col">Name</th>
              <?php if (SchemaInspector::columnExists($pdo, 'rider_users', 'phone')) { ?>
                <th scope="col">Phone</th>
              <?php } ?>
              <th scope="col">Google sub</th>
              <th scope="col">Joined</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r) { ?>
              <tr>
                <td class="vp-table__id"><?= (int) $r['id'] ?></td>
                <td><?= vp_h((string) $r['email']) ?></td>
                <td><?= vp_h((string) ($r['display_name'] ?? '—')) ?></td>
                <?php if (SchemaInspector::columnExists($pdo, 'rider_users', 'phone')) { ?>
                  <td class="vp-table__mono" style="font-size:0.8125rem;"><?= vp_h((string) ($r['phone'] ?? '—')) ?></td>
                <?php } ?>
                <td class="vp-table__mono vp-table__muted" style="font-size:0.75rem; max-width:12rem; overflow:hidden; text-overflow:ellipsis;"><?= vp_h((string) $r['google_sub']) ?></td>
                <td style="color:var(--vp-muted); font-size:0.8125rem;"><?= vp_h((string) $r['created_at']) ?></td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
      <?php if ($pages > 1) { ?>
        <nav class="vp-pagination" aria-label="Rider list pages">
          <?php if ($page > 1) { ?>
            <a class="vp-btn vp-btn--ghost vp-btn--sm" href="<?= vp_h($buildPageUrl($page - 1)) ?>">Previous</a>
          <?php } ?>
          <span class="vp-pagination__meta">Page <?= (int) $page ?> of <?= (int) $pages ?></span>
          <?php if ($page < $pages) { ?>
            <a class="vp-btn vp-btn--ghost vp-btn--sm" href="<?= vp_h($buildPageUrl($page + 1)) ?>">Next</a>
          <?php } ?>
        </nav>
      <?php } ?>
    <?php } ?>
  </div>
</section>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
