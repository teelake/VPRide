<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/SchemaInspector.php';
require_once $backendRoot . '/src/FleetVehicleRepository.php';
require_once $backendRoot . '/src/ConsoleDriverRepository.php';

use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\ConsoleDriverRepository;
use VprideBackend\Database;
use VprideBackend\FleetVehicleRepository;
use VprideBackend\SchemaInspector;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('rides.view');

$admin = Auth::currentAdmin();
$csrf = Auth::csrfToken();
$pdo = Database::pdo();
$vehicleRepo = new FleetVehicleRepository($pdo);
$repo = new ConsoleDriverRepository($pdo, $vehicleRepo);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::can('fleet.manage')) {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token. Refresh the page and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $delId = (int) ($_POST['delete_id'] ?? 0);
        if ($action === 'delete_driver' && $delId > 0) {
            try {
                $repo->delete($delId);
                $message = 'Driver record removed.';
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = max(10, min(100, (int) ($_GET['per_page'] ?? 30)));
$qParam = $q === '' ? null : $q;
$total = $repo->countForAdmin($qParam);
$pages = max(1, (int) ceil($total / $perPage));
if ($page > $pages) {
    $page = $pages;
}
$offset = ($page - 1) * $perPage;
$rows = $repo->listForAdmin($qParam, $perPage, $offset);

$qBase = ['q' => $q, 'per_page' => (string) $perPage];
$buildPageUrl = static function (int $p) use ($qBase): string {
    $qq = array_merge($qBase, ['page' => (string) $p]);
    $qq = array_filter($qq, static fn ($v) => $v !== '' && $v !== null);

    return Config::url('/admin/drivers?' . http_build_query($qq));
};

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Drivers · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'drivers';
$vpTopbarTitle = 'Drivers';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<?php vp_schema_single_table_alert($pdo, 'fleet_drivers', 'migration_fleet_vehicles_drivers.sql', 'Driver directory'); ?>

<header class="vp-page-hero">
  <?php
    vp_breadcrumbs([
        ['label' => 'Dashboard', 'href' => vp_url('/admin/dashboard')],
        ['label' => 'Drivers', 'href' => null],
    ]);
?>
  <h1 class="vp-page-title">Driver directory</h1>
  <p class="vp-page-desc">Onboard drivers here (console-only — not the rider app). <strong>Owner-operators</strong> use their <strong>personal</strong> vehicle record. <strong>Company drivers</strong> use a <strong>company / brand</strong> vehicle from <a href="<?= vp_h(vp_url('/admin/fleet')) ?>">Car management</a>.</p>
</header>

<?php if ($message !== '') { ?>
  <div class="vp-alert vp-alert--success" role="status"><?= vp_h($message) ?></div>
<?php } ?>
<?php if ($error !== '') { ?>
  <div class="vp-alert vp-alert--error" role="alert"><?= vp_h($error) ?></div>
<?php } ?>

<div class="vp-toolbar vp-toolbar--split">
  <div class="vp-toolbar__left">
    <form method="get" action="<?= vp_h(vp_url('/admin/drivers')) ?>" class="vp-inline-search">
      <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
      <label class="vp-sr-only" for="driver-q">Search drivers</label>
      <input class="vp-input vp-input--search" id="driver-q" name="q" type="search" value="<?= vp_h($q) ?>" placeholder="Name, email, phone, plate…" autocomplete="off">
      <button type="submit" class="vp-btn vp-btn--primary vp-btn--sm">Search</button>
    </form>
  </div>
  <div class="vp-toolbar__actions">
    <?php if (Auth::can('fleet.manage')) { ?>
      <a class="vp-btn vp-btn--primary" href="<?= vp_url('/admin/drivers/new') ?>">Add driver</a>
    <?php } ?>
  </div>
</div>

<section class="vp-card" aria-labelledby="drivers-heading">
  <div class="vp-card__pad">
    <h2 id="drivers-heading" class="vp-section-title">Drivers</h2>
    <?php if (! SchemaInspector::tableExists($pdo, 'fleet_drivers')) { ?>
      <?php
        vp_empty_state(
            'Database table missing',
            'Import backend/sql/migration_fleet_vehicles_drivers.sql to create fleet_drivers and fleet_vehicles.',
            [],
        );
      ?>
    <?php } elseif ($rows === []) { ?>
      <?php if ($q !== '') { ?>
        <?php
          vp_empty_state(
              'No drivers match this search',
              'Try another keyword or clear the filter.',
              [['label' => 'Clear search', 'href' => vp_url('/admin/drivers'), 'variant' => 'ghost']],
          );
        ?>
      <?php } else { ?>
        <?php
          $actions = [];
          if (Auth::can('fleet.manage')) {
              $actions[] = ['label' => 'Add driver', 'href' => vp_url('/admin/drivers/new'), 'variant' => 'primary'];
              $actions[] = ['label' => 'Car management', 'href' => vp_url('/admin/fleet'), 'variant' => 'ghost'];
          }
          vp_empty_state(
              'No drivers yet',
              'Add vehicles first, then create driver profiles and assign the correct vehicle type.',
              $actions,
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
              <th scope="col">Name</th>
              <th scope="col">Type</th>
              <th scope="col">Contact</th>
              <th scope="col">Vehicle</th>
              <th scope="col">Status</th>
              <?php if (Auth::can('fleet.manage')) { ?>
                <th scope="col" class="vp-table__actions-col"><span class="vp-sr-only">Actions</span></th>
              <?php } ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r) { ?>
              <tr>
                <td class="vp-table__id"><?= (int) $r['id'] ?></td>
                <td><?= vp_h((string) $r['full_name']) ?></td>
                <td>
                  <?php if (($r['driver_kind'] ?? '') === 'company_driver') { ?>
                    <span class="vp-pill vp-pill--dark">Company driver</span>
                  <?php } else { ?>
                    <span class="vp-pill">Owner-operator</span>
                  <?php } ?>
                </td>
                <td style="font-size:0.8125rem;">
                  <?php if (! empty($r['email'])) { ?>
                    <span class="vp-table__mono"><?= vp_h((string) $r['email']) ?></span><br>
                  <?php } ?>
                  <?= vp_h((string) ($r['phone'] ?? '') ?: '—') ?>
                </td>
                <td style="font-size:0.8125rem;">
                  <?php if (! empty($r['vehicle_plate'])) { ?>
                    <strong class="vp-table__mono"><?= vp_h((string) $r['vehicle_plate']) ?></strong>
                    <?php if (($r['vehicle_ownership'] ?? '') === 'company' && ! empty($r['vehicle_company_label'])) { ?>
                      <br><span class="vp-table__muted"><?= vp_h((string) $r['vehicle_company_label']) ?></span>
                    <?php } ?>
                  <?php } else { ?>
                    <span class="vp-table__muted">Unassigned</span>
                  <?php } ?>
                </td>
                <td><span class="vp-status-dot <?= vp_h(vp_ride_status_dot_class((string) $r['status'])) ?>"></span><?= vp_h((string) $r['status']) ?></td>
                <?php if (Auth::can('fleet.manage')) { ?>
                  <td class="vp-table__actions-col">
                    <?php
                    vp_action_icons_open();
                    vp_action_edit(vp_url('/admin/drivers/' . (int) $r['id']));
                    vp_action_delete_form(
                        vp_url('/admin/drivers'),
                        $csrf,
                        ['action' => 'delete_driver', 'delete_id' => (int) $r['id']],
                        'Delete this driver record?',
                    );
                    vp_action_icons_close();
                    ?>
                  </td>
                <?php } ?>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
      <?php if ($pages > 1) { ?>
        <nav class="vp-pagination" aria-label="Driver list pages">
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

<?php if (! Auth::can('fleet.manage')) { ?>
  <section class="vp-card vp-card--note" aria-labelledby="drivers-ro-h">
    <div class="vp-card__pad">
      <h2 id="drivers-ro-h" class="vp-section-title">View-only</h2>
      <p class="vp-page-desc" style="margin:0;">Your role can view drivers but not add or edit. Ask a system administrator for <strong>Manage fleet vehicles &amp; driver records</strong> to onboard drivers.</p>
    </div>
  </section>
<?php } ?>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
