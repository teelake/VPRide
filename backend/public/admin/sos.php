<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/SosIncidentRepository.php';
require_once $backendRoot . '/src/SchemaInspector.php';

use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\SchemaInspector;
use VprideBackend\SosIncidentRepository;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('sos.view');

$admin = Auth::currentAdmin();
$pdo = Database::pdo();
$csrf = Auth::csrfToken();
$message = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && Auth::can('sos.manage')) {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $message = 'Invalid session.';
    } else {
        $ack = (int) ($_POST['acknowledge_id'] ?? 0);
        if ($ack > 0 && SosIncidentRepository::tableExists($pdo)) {
            $repo = new SosIncidentRepository($pdo);
            if ($repo->acknowledge($ack, $admin[0])) {
                $message = 'Incident acknowledged.';
            } else {
                $message = 'Could not acknowledge (already handled or missing).';
            }
        }
    }
}

$open = SosIncidentRepository::tableExists($pdo)
    ? (new SosIncidentRepository($pdo))->listByStatus('open', 200)
    : [];
$ackd = SosIncidentRepository::tableExists($pdo)
    ? (new SosIncidentRepository($pdo))->listByStatus('acknowledged', 50)
    : [];

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'SOS incidents · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'sos';
$vpTopbarTitle = 'SOS';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<?php vp_schema_single_table_alert($pdo, 'sos_incidents', 'migration_sos_promos_loyalty.sql', 'SOS'); ?>

<header class="vp-page-hero">
  <h1 class="vp-page-title">SOS incidents</h1>
  <p class="vp-page-desc">Panic alerts from riders and drivers during active trips. Refresh the page to poll for new items.</p>
</header>

<?php if ($message !== '') { ?>
  <p class="vp-banner vp-banner--info" role="status"><?= vp_h($message) ?></p>
<?php } ?>

<section class="vp-card" aria-labelledby="sos-open-heading">
  <div class="vp-card__pad">
    <h2 id="sos-open-heading" class="vp-section-title">Open</h2>
    <?php if (! SchemaInspector::tableExists($pdo, 'sos_incidents')) { ?>
      <p class="vp-field-hint">Run <code class="vp-inline-code">migration_sos_promos_loyalty.sql</code> on the database.</p>
    <?php } elseif ($open === []) { ?>
      <p class="vp-field-hint">No open incidents.</p>
    <?php } else { ?>
      <div class="vp-table-wrap">
        <table class="vp-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Ride</th>
              <th>Role</th>
              <th>Reporter</th>
              <th>Location</th>
              <th>Time</th>
              <?php if (Auth::can('sos.manage')) { ?><th></th><?php } ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($open as $r) { ?>
              <tr>
                <td class="vp-table__id"><?= (int) $r['id'] ?></td>
                <td>#<?= (int) $r['ride_id'] ?> <span class="vp-pill vp-pill--neutral"><?= vp_h((string) $r['ride_status']) ?></span></td>
                <td><?= vp_h((string) $r['reporter_role']) ?></td>
                <td><?= vp_h((string) $r['reporter_email']) ?></td>
                <td class="vp-table__muted"><?= vp_h((string) $r['latitude'] . ', ' . (string) $r['longitude']) ?></td>
                <td style="color:var(--vp-muted);font-size:0.8125rem;"><?= vp_h((string) $r['created_at']) ?></td>
                <?php if (Auth::can('sos.manage')) { ?>
                  <td>
                    <form method="post" style="margin:0;">
                      <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
                      <input type="hidden" name="acknowledge_id" value="<?= (int) $r['id'] ?>">
                      <button type="submit" class="vp-btn vp-btn--primary vp-btn--sm">Acknowledge</button>
                    </form>
                  </td>
                <?php } ?>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    <?php } ?>
  </div>
</section>

<section class="vp-card" style="margin-top:1.5rem;" aria-labelledby="sos-ack-heading">
  <div class="vp-card__pad">
    <h2 id="sos-ack-heading" class="vp-section-title">Recently acknowledged</h2>
    <?php if ($ackd === []) { ?>
      <p class="vp-field-hint">None yet.</p>
    <?php } else { ?>
      <div class="vp-table-wrap">
        <table class="vp-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Ride</th>
              <th>Role</th>
              <th>Reporter</th>
              <th>Acknowledged</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ackd as $r) { ?>
              <tr>
                <td class="vp-table__id"><?= (int) $r['id'] ?></td>
                <td>#<?= (int) $r['ride_id'] ?></td>
                <td><?= vp_h((string) $r['reporter_role']) ?></td>
                <td><?= vp_h((string) $r['reporter_email']) ?></td>
                <td style="color:var(--vp-muted);font-size:0.8125rem;"><?= vp_h((string) ($r['acknowledged_at'] ?? '—')) ?></td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    <?php } ?>
  </div>
</section>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
