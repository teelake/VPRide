<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/SchemaInspector.php';
require_once $backendRoot . '/src/FleetVehicleRepository.php';

use VprideBackend\Auth;
use VprideBackend\Config;
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
$repo = new FleetVehicleRepository($pdo);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::can('fleet.manage')) {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token. Refresh the page and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $delId = (int) ($_POST['delete_id'] ?? 0);
        if ($action === 'delete_vehicle' && $delId > 0) {
            try {
                $repo->delete($delId);
                $message = 'Vehicle removed.';
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

    return Config::url('/admin/fleet?' . http_build_query($qq));
};

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Fleet · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'fleet';
$vpTopbarTitle = 'Car management';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<?php vp_schema_single_table_alert($pdo, 'fleet_vehicles', 'migration_fleet_vehicles_drivers.sql', 'Car management'); ?>

<header class="vp-page-hero">
  <?php
    vp_breadcrumbs([
        ['label' => 'Dashboard', 'href' => vp_url('/admin/dashboard')],
        ['label' => 'Car management', 'href' => null],
    ]);
?>
  <h1 class="vp-page-title">Car management</h1>
  <p class="vp-page-desc">Register <strong>personal</strong> vehicles (owner-operators) and <strong>company / brand</strong> fleet cars. Assign vehicles on the <a href="<?= vp_h(vp_url('/admin/drivers')) ?>">Driver directory</a> when onboarding.</p>
</header>

<?php if ($message !== '') { ?>
  <div class="vp-alert vp-alert--success" role="status"><?= vp_h($message) ?></div>
<?php } ?>
<?php if ($error !== '') { ?>
  <div class="vp-alert vp-alert--error" role="alert"><?= vp_h($error) ?></div>
<?php } ?>

<div class="vp-toolbar vp-toolbar--split">
  <div class="vp-toolbar__left">
    <form method="get" action="<?= vp_h(vp_url('/admin/fleet')) ?>" class="vp-inline-search">
      <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
      <label class="vp-sr-only" for="fleet-q">Search vehicles</label>
      <input class="vp-input vp-input--search" id="fleet-q" name="q" type="search" value="<?= vp_h($q) ?>" placeholder="Plate, make, model, VIN…" autocomplete="off">
      <button type="submit" class="vp-btn vp-btn--primary vp-btn--sm">Search</button>
    </form>
  </div>
  <div class="vp-toolbar__actions">
    <?php if (Auth::can('fleet.manage')) { ?>
      <a class="vp-btn vp-btn--primary" href="<?= vp_url('/admin/fleet/new') ?>">Add vehicle</a>
    <?php } ?>
  </div>
</div>

<section class="vp-card" aria-labelledby="fleet-heading">
  <div class="vp-card__pad">
    <h2 id="fleet-heading" class="vp-section-title">Vehicles</h2>
    <?php if (! SchemaInspector::tableExists($pdo, 'fleet_vehicles')) { ?>
      <?php
        vp_empty_state(
            'Database table missing',
            'Import backend/sql/migration_fleet_vehicles_drivers.sql to create fleet_vehicles and fleet_drivers.',
            [],
        );
      ?>
    <?php } elseif ($rows === []) { ?>
      <?php if ($q !== '') { ?>
        <?php
          vp_empty_state(
              'No vehicles match this search',
              'Try another keyword or clear the filter.',
              [['label' => 'Clear search', 'href' => vp_url('/admin/fleet'), 'variant' => 'ghost']],
          );
        ?>
      <?php } else { ?>
        <?php
          $actions = [];
          if (Auth::can('fleet.manage')) {
              $actions[] = ['label' => 'Add vehicle', 'href' => vp_url('/admin/fleet/new'), 'variant' => 'primary'];
          }
          vp_empty_state(
              'No vehicles yet',
              'Add a personal car for an owner-operator or a company vehicle for brand fleet drivers.',
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
              <th scope="col">Type</th>
              <th scope="col">Plate</th>
              <th scope="col">Brand / fleet</th>
              <th scope="col">Make &amp; model</th>
              <th scope="col">Status</th>
              <th scope="col">Drivers</th>
              <?php if (Auth::can('fleet.manage')) { ?>
                <th scope="col"><span class="vp-sr-only">Actions</span></th>
              <?php } ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r) { ?>
              <tr>
                <td class="vp-table__id"><?= (int) $r['id'] ?></td>
                <td>
                  <?php if (($r['ownership'] ?? '') === 'company') { ?>
                    <span class="vp-pill vp-pill--dark">Company</span>
                  <?php } else { ?>
                    <span class="vp-pill">Personal</span>
                  <?php } ?>
                </td>
                <td class="vp-table__mono"><?= vp_h((string) $r['plate_number']) ?></td>
                <td><?= vp_h((string) ($r['company_fleet_label'] ?? '') ?: '—') ?></td>
                <td><?= vp_h(trim(((string) ($r['make'] ?? '')) . ' ' . ((string) ($r['model'] ?? ''))) ?: '—') ?></td>
                <td><span class="vp-status-dot <?= vp_h(vp_ride_status_dot_class((string) $r['status'])) ?>"></span><?= vp_h((string) $r['status']) ?></td>
                <td><?= (int) ($r['driver_count'] ?? 0) ?></td>
                <?php if (Auth::can('fleet.manage')) { ?>
                  <td style="white-space:nowrap;">
                    <a class="vp-btn vp-btn--ghost vp-btn--sm" href="<?= vp_h(vp_url('/admin/fleet/' . (int) $r['id'])) ?>">Edit</a>
                    <form method="post" action="<?= vp_h(vp_url('/admin/fleet')) ?>" style="display:inline;" onsubmit="return confirm(<?= vp_confirm_attr('Delete this vehicle? Drivers must be unassigned first.') ?>);">
                      <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
                      <input type="hidden" name="action" value="delete_vehicle">
                      <input type="hidden" name="delete_id" value="<?= (int) $r['id'] ?>">
                      <button type="submit" class="vp-btn vp-btn--ghost vp-btn--sm" style="color:var(--vp-danger);">Delete</button>
                    </form>
                  </td>
                <?php } ?>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
      <?php if ($pages > 1) { ?>
        <nav class="vp-pagination" aria-label="Fleet pages">
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
  <section class="vp-card vp-card--note" aria-labelledby="fleet-ro-h">
    <div class="vp-card__pad">
      <h2 id="fleet-ro-h" class="vp-section-title">View-only</h2>
      <p class="vp-page-desc" style="margin:0;">Your role can view the fleet list but not add or edit. Ask a system administrator to grant <strong>Manage fleet vehicles &amp; driver records</strong> if you need to onboard vehicles.</p>
    </div>
  </section>
<?php } ?>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
