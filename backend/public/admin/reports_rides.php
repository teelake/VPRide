<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/RideRepository.php';

use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\RideRepository;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('reports.view');

$admin = Auth::currentAdmin();
$csrf = Auth::csrfToken();
$pdo = Database::pdo();
$repo = new RideRepository($pdo);

$status = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
$from = isset($_GET['from']) ? trim((string) $_GET['from']) : '';
$to = isset($_GET['to']) ? trim((string) $_GET['to']) : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = max(10, min(100, (int) ($_GET['per_page'] ?? 25)));
$export = isset($_GET['export']) && $_GET['export'] === 'csv';

$allowed = ['', 'requested', 'accepted', 'in_progress', 'completed', 'cancelled'];
if (! in_array($status, $allowed, true)) {
    $status = '';
}
$statusParam = $status === '' ? null : $status;
$fromParam = $from === '' ? null : $from;
$toParam = $to === '' ? null : $to;

$total = $repo->countFiltered($statusParam, $fromParam, $toParam);
$pages = max(1, (int) ceil($total / $perPage));
if ($page > $pages) {
    $page = $pages;
}
$offset = ($page - 1) * $perPage;
$rows = $repo->listFiltered($statusParam, $fromParam, $toParam, $perPage, $offset);

if ($export) {
    if (! Auth::can('reports.export')) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
    $fn = 'vpride-rides-' . gmdate('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fn . '"');
    echo vp_csv_line(['id', 'status', 'rider_email', 'pickup_lat', 'pickup_lng', 'pickup_address', 'dropoff_address', 'created_at']);
    $all = $repo->listFiltered($statusParam, $fromParam, $toParam, 10000, 0);
    foreach ($all as $r) {
        echo vp_csv_line([
            (string) $r['id'],
            (string) $r['status'],
            (string) $r['rider_email'],
            (string) $r['pickup_lat'],
            (string) $r['pickup_lng'],
            (string) ($r['pickup_address'] ?? ''),
            (string) ($r['dropoff_address'] ?? ''),
            (string) $r['created_at'],
        ]);
    }
    exit;
}

$qBase = [
    'status' => $status,
    'from' => $from,
    'to' => $to,
    'per_page' => (string) $perPage,
];
$buildUrl = static function (int $p) use ($qBase): string {
    $q = array_merge($qBase, ['page' => (string) $p]);
    $q = array_filter($q, static fn ($v) => $v !== '' && $v !== null);

    return \VprideBackend\Config::url('/admin/reports/rides?' . http_build_query($q));
};

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Reports · Rides · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'reports';
$vpTopbarTitle = 'Reports';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<header class="vp-page-hero">
  <?php
    vp_breadcrumbs([
        ['label' => 'Reports', 'href' => vp_url('/admin/reports/rides')],
        ['label' => 'Rides', 'href' => null],
    ]);
?>
  <h1 class="vp-page-title">Reports</h1>
  <p class="vp-page-desc">Filter ride activity, paginate results, and export CSV for spreadsheets.</p>
</header>

<?php vp_schema_single_table_alert($pdo, 'rides', 'sql/migration_rides.sql', 'Ride reports'); ?>

<?php vp_reports_tabs('rides'); ?>

<section class="vp-card vp-card--flush-top" aria-labelledby="ride-filters">
  <div class="vp-card__pad">
    <h2 id="ride-filters" class="vp-section-title">Filters</h2>
    <form method="get" action="<?= vp_h(vp_url('/admin/reports/rides')) ?>" class="vp-filter-form">
      <div class="vp-filter-grid">
        <div class="vp-field">
          <label class="vp-label" for="status">Status</label>
          <select class="vp-input" id="status" name="status">
            <option value=""<?= $status === '' ? ' selected' : '' ?>>All</option>
            <option value="requested"<?= $status === 'requested' ? ' selected' : '' ?>>Requested</option>
            <option value="accepted"<?= $status === 'accepted' ? ' selected' : '' ?>>Accepted</option>
            <option value="in_progress"<?= $status === 'in_progress' ? ' selected' : '' ?>>In progress</option>
            <option value="completed"<?= $status === 'completed' ? ' selected' : '' ?>>Completed</option>
            <option value="cancelled"<?= $status === 'cancelled' ? ' selected' : '' ?>>Cancelled</option>
          </select>
        </div>
        <div class="vp-field">
          <label class="vp-label" for="from">From date</label>
          <input class="vp-input" type="date" id="from" name="from" value="<?= vp_h($from) ?>">
        </div>
        <div class="vp-field">
          <label class="vp-label" for="to">To date</label>
          <input class="vp-input" type="date" id="to" name="to" value="<?= vp_h($to) ?>">
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
        <button type="submit" class="vp-btn vp-btn--primary">Apply filters</button>
        <?php if (Auth::can('reports.export')) { ?>
          <a class="vp-btn vp-btn--ghost" href="<?= vp_h(vp_url('/admin/reports/rides?' . http_build_query(array_merge($qBase, ['page' => '1', 'export' => 'csv'])))) ?>">Export CSV</a>
        <?php } ?>
      </div>
    </form>
  </div>
</section>

<section class="vp-card" aria-labelledby="ride-results">
  <div class="vp-card__pad">
    <div class="vp-card__head-row">
      <h2 id="ride-results" class="vp-section-title" style="margin:0;">Results</h2>
      <p class="vp-muted-inline"><?= number_format($total) ?> row(s)</p>
    </div>
    <?php if ($rows === []) { ?>
      <?php if (\VprideBackend\SchemaInspector::tableExists($pdo, 'rides')) { ?>
        <?php
          vp_empty_state(
              'No rides match these filters',
              'Widen the date range, clear status, or export an empty template from CSV if you need headers only.',
              [['label' => 'Reset filters', 'href' => vp_url('/admin/reports/rides'), 'variant' => 'primary']],
          );
        ?>
      <?php } else { ?>
        <?php
          vp_empty_state(
              'Rides table missing',
              'Import sql/migration_rides.sql, then return to this report.',
              [['label' => 'Overview', 'href' => vp_url('/admin/dashboard'), 'variant' => 'ghost']],
          );
        ?>
      <?php } ?>
    <?php } else { ?>
      <div class="vp-table-wrap">
        <table class="vp-table vp-table--compact">
          <thead>
            <tr>
              <th scope="col">ID</th>
              <th scope="col">Status</th>
              <th scope="col">Rider</th>
              <th scope="col">Pickup</th>
              <th scope="col">Created</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row) { ?>
              <tr>
                <td class="vp-table__id"><?= (int) $row['id'] ?></td>
                <td><span class="vp-pill vp-pill--neutral"><?= vp_h((string) $row['status']) ?></span></td>
                <td><?= vp_h((string) $row['rider_email']) ?></td>
                <td class="vp-table__muted"><?= vp_h((string) ($row['pickup_address'] ?: (($row['pickup_lat'] ?? '') . ', ' . ($row['pickup_lng'] ?? '')))) ?></td>
                <td class="vp-table__muted" style="font-size:0.8125rem;"><?= vp_h((string) $row['created_at']) ?></td>
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
