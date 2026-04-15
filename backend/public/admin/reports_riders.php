<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/RiderUserRepository.php';

use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\RiderUserRepository;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('reports.view');

$admin = Auth::currentAdmin();
$csrf = Auth::csrfToken();
$pdo = Database::pdo();
$repo = new RiderUserRepository($pdo);

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = max(10, min(100, (int) ($_GET['per_page'] ?? 25)));
$export = isset($_GET['export']) && $_GET['export'] === 'csv';

$qParam = $q === '' ? null : $q;
$total = $repo->countFiltered($qParam);
$pages = max(1, (int) ceil($total / $perPage));
if ($page > $pages) {
    $page = $pages;
}
$offset = ($page - 1) * $perPage;
$rows = $repo->listFiltered($qParam, $perPage, $offset);

if ($export) {
    if (! Auth::can('reports.export')) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
    $fn = 'vpride-riders-' . gmdate('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fn . '"');
    echo vp_csv_line(['id', 'email', 'display_name', 'google_sub', 'created_at', 'updated_at']);
    $all = $repo->listFiltered($qParam, 10000, 0);
    foreach ($all as $r) {
        echo vp_csv_line([
            (string) $r['id'],
            (string) $r['email'],
            (string) ($r['display_name'] ?? ''),
            (string) $r['google_sub'],
            (string) $r['created_at'],
            (string) $r['updated_at'],
        ]);
    }
    exit;
}

$qBase = ['q' => $q, 'per_page' => (string) $perPage];
$buildUrl = static function (int $p) use ($qBase): string {
    $qq = array_merge($qBase, ['page' => (string) $p]);
    $qq = array_filter($qq, static fn ($v) => $v !== '' && $v !== null);

    return \VprideBackend\Config::url('/admin/reports/riders?' . http_build_query($qq));
};

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Reports · Riders · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'reports';
$vpTopbarTitle = 'Reports';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<header class="vp-page-hero">
  <h1 class="vp-page-title">Reports</h1>
  <p class="vp-page-desc">Search the rider directory and export CSV for audits or support workflows.</p>
</header>

<?php vp_reports_tabs('riders'); ?>

<section class="vp-card vp-card--flush-top" aria-labelledby="rider-filters">
  <div class="vp-card__pad">
    <h2 id="rider-filters" class="vp-section-title">Search</h2>
    <form method="get" action="<?= vp_h(vp_url('/admin/reports/riders')) ?>" class="vp-filter-form">
      <div class="vp-filter-grid">
        <div class="vp-field vp-field--grow">
          <label class="vp-label" for="q">Keyword</label>
          <input class="vp-input" id="q" name="q" type="search" value="<?= vp_h($q) ?>" placeholder="Email, name, Google subject, or ID" autocomplete="off">
        </div>
        <div class="vp-field">
          <label class="vp-label" for="per_page">Rows per page</label>
          <select class="vp-input" id="per_page" name="per_page">
            <?php foreach ([25, 50, 100] as $n) { ?>
              <option value="<?= $n ?>"<?= $perPage === $n ? ' selected' : '' ?>><?= $n ?></option>
            <?php } ?>
          </select>
        </div>
      </div>
      <div class="vp-filter-actions">
        <button type="submit" class="vp-btn vp-btn--primary">Search</button>
        <?php if (Auth::can('reports.export')) { ?>
          <a class="vp-btn vp-btn--ghost" href="<?= vp_h(vp_url('/admin/reports/riders?' . http_build_query(array_merge($qBase, ['page' => '1', 'export' => 'csv'])))) ?>">Export CSV</a>
        <?php } ?>
      </div>
    </form>
  </div>
</section>

<section class="vp-card" aria-labelledby="rider-results">
  <div class="vp-card__pad">
    <div class="vp-card__head-row">
      <h2 id="rider-results" class="vp-section-title" style="margin:0;">Results</h2>
      <p class="vp-muted-inline"><?= number_format($total) ?> row(s)</p>
    </div>
    <?php if ($rows === []) { ?>
      <p class="vp-page-desc" style="margin-bottom:0;">No riders match this search.</p>
    <?php } else { ?>
      <div class="vp-table-wrap">
        <table class="vp-table vp-table--compact">
          <thead>
            <tr>
              <th scope="col">ID</th>
              <th scope="col">Email</th>
              <th scope="col">Name</th>
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
                <td class="vp-table__mono vp-table__muted" style="font-size:0.75rem; max-width:10rem;"><?= vp_h((string) $r['google_sub']) ?></td>
                <td class="vp-table__muted" style="font-size:0.8125rem;"><?= vp_h((string) $r['created_at']) ?></td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
      <?php if ($pages > 1) { ?>
        <nav class="vp-pagination" aria-label="Results pages">
          <?php if ($page > 1) { ?>
            <a class="vp-btn vp-btn--ghost vp-btn--sm" href="<?= vp_h($buildUrl($page - 1)) ?>">Previous</a>
          <?php } ?>
          <span class="vp-pagination__meta">Page <?= (int) $page ?> of <?= (int) $pages ?></span>
          <?php if ($page < $pages) { ?>
            <a class="vp-btn vp-btn--ghost vp-btn--sm" href="<?= vp_h($buildUrl($page + 1)) ?>">Next</a>
          <?php } ?>
        </nav>
      <?php } ?>
    <?php } ?>
  </div>
</section>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
