<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/FleetVehicleRepository.php';
require_once $backendRoot . '/src/ConsoleDriverRepository.php';
require_once $backendRoot . '/src/RiderAuthService.php';
require_once $backendRoot . '/src/Mailer.php';
require_once $backendRoot . '/src/AppSettingsRepository.php';
require_once $backendRoot . '/src/SchemaInspector.php';

use VprideBackend\AppSettingsRepository;
use VprideBackend\SchemaInspector;
use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\ConsoleDriverRepository;
use VprideBackend\Database;
use VprideBackend\FleetVehicleRepository;
use VprideBackend\Mailer;
use VprideBackend\RiderAuthService;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('fleet.manage');

$admin = Auth::currentAdmin();
$pdo = Database::pdo();
$vehicleRepo = new FleetVehicleRepository($pdo);
$repo = new ConsoleDriverRepository($pdo, $vehicleRepo);
$csrf = Auth::csrfToken();

$isNew = ! empty($_ROUTE_DRIVER_NEW);
$id = $isNew ? 0 : (int) ($_ROUTE_DRIVER_ID ?? 0);

$fullName = '';
$driverKind = 'owner_operator';
$phone = '';
$email = '';
$fleetVehicleId = 0;
$licenseNumber = '';
$status = 'pending';
$notes = '';
$earningsPercentOverride = '';
$vehicleAssignmentMode = 'fixed';

$error = '';
$message = '';
if (($_GET['welcome'] ?? '') === '1') {
    $message = 'Driver saved. Login details were sent to the driver&apos;s email.';
} elseif (($_GET['driver_mail'] ?? '') === '0') {
    $message = 'Driver saved, but the login email could not be sent. Check server mail settings or share the temporary password manually.';
}

if (! $isNew) {
    $row = $repo->findById($id);
    if ($row === null) {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        $pageTitle = 'Not found · VP Ride Console';
        $bodyClass = 'vp-body vp-body--app';
        $vpNavActive = 'drivers';
        $vpTopbarTitle = 'Not found';
        require __DIR__ . '/includes/head.php';
        require __DIR__ . '/includes/app_shell_start.php';
        ?>
        <div class="vp-card"><div class="vp-card__pad">
          <?php
            vp_breadcrumbs([
                ['label' => 'Drivers', 'href' => vp_url('/drivers')],
                ['label' => 'Not found', 'href' => null],
            ]);
        ?>
          <h1 class="vp-page-title">Driver not found</h1>
          <p class="vp-page-desc"><a class="vp-back" href="<?= vp_url('/drivers') ?>"><span class="vp-back__arrow">←</span> Back to drivers</a></p>
        </div></div>
        <?php
        require __DIR__ . '/includes/app_shell_end.php';
        exit;
    }
    $fullName = (string) $row['full_name'];
    $driverKind = (string) $row['driver_kind'];
    $phone = (string) ($row['phone'] ?? '');
    $email = (string) ($row['email'] ?? '');
    $fleetVehicleId = isset($row['fleet_vehicle_id']) && $row['fleet_vehicle_id'] !== null
        ? (int) $row['fleet_vehicle_id']
        : 0;
    $licenseNumber = (string) ($row['license_number'] ?? '');
    $status = (string) $row['status'];
    $notes = (string) ($row['notes'] ?? '');
    if (isset($row['earnings_percent_override']) && $row['earnings_percent_override'] !== null && $row['earnings_percent_override'] !== '') {
        $earningsPercentOverride = (string) (float) $row['earnings_percent_override'];
    }
    if (isset($row['vehicle_assignment_mode']) && is_string($row['vehicle_assignment_mode']) && $row['vehicle_assignment_mode'] !== '') {
        $m = strtolower(trim($row['vehicle_assignment_mode']));
        if (in_array($m, ['fixed', 'flexible'], true)) {
            $vehicleAssignmentMode = $m;
        }
    }
}

$vehicles = $vehicleRepo->listActiveForSelect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token. Refresh and try again.';
    } else {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $driverKind = trim((string) ($_POST['driver_kind'] ?? 'owner_operator'));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $fleetVehicleId = (int) ($_POST['fleet_vehicle_id'] ?? 0);
        $licenseNumber = trim((string) ($_POST['license_number'] ?? ''));
        $status = trim((string) ($_POST['status'] ?? 'pending'));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $earningsPercentOverride = trim((string) ($_POST['earnings_percent_override'] ?? ''));
        $vam = strtolower(trim((string) ($_POST['vehicle_assignment_mode'] ?? 'fixed')));
        $vehicleAssignmentMode = in_array($vam, ['fixed', 'flexible'], true) ? $vam : 'fixed';

        $existingRiderUserId = null;
        if (! $isNew && $id > 0) {
            $existingRow = $repo->findById($id);
            if ($existingRow !== null && isset($existingRow['rider_user_id']) && $existingRow['rider_user_id'] !== null) {
                $existingRiderUserId = (int) $existingRow['rider_user_id'];
            }
        }

        $hadNoRiderLink = $isNew || $existingRiderUserId === null || $existingRiderUserId < 1;
        $needsProvision = $hadNoRiderLink;

        if ($needsProvision && $email === '') {
            $error = 'Email is required so the system can create the driver&apos;s app account and send a temporary password.';
        } else {
            $payload = [
                'full_name' => $fullName,
                'driver_kind' => $driverKind,
                'phone' => $phone,
                'email' => $email,
                'fleet_vehicle_id' => $fleetVehicleId,
                'license_number' => $licenseNumber,
                'status' => $status,
                'notes' => $notes,
                'rider_user_id' => $needsProvision ? null : $existingRiderUserId,
                'earnings_percent_override' => $earningsPercentOverride === '' ? null : $earningsPercentOverride,
                'vehicle_assignment_mode' => $vehicleAssignmentMode,
            ];
            $generatedPassword = null;
            $provisionEmail = $email;
            $driverMailOk = true;
            try {
                $pdo = Database::pdo();
                if ($needsProvision) {
                    $pdo->beginTransaction();
                    try {
                        $auth = new RiderAuthService($pdo);
                        $prov = $auth->createPasswordUserWithGeneratedPassword($provisionEmail, $fullName);
                        $payload['rider_user_id'] = $prov['userId'];
                        $generatedPassword = $prov['plainPassword'];
                        if ($isNew) {
                            $newId = $repo->insert($payload);
                        } else {
                            $repo->update($id, $payload);
                            $newId = $id;
                        }
                        $pdo->commit();
                    } catch (Throwable $e) {
                        $pdo->rollBack();
                        throw $e;
                    }
                } else {
                    if ($isNew) {
                        $newId = $repo->insert($payload);
                    } else {
                        $repo->update($id, $payload);
                        $newId = $id;
                    }
                }

                if ($generatedPassword !== null) {
                    $cfg = AppSettingsRepository::emailOutboundEffective($pdo);
                    $from = $cfg['mailFrom'] !== '' ? $cfg['mailFrom'] : null;
                    $greeting = $fullName !== '' ? 'Hi ' . $fullName . ",\n\n" : "Hello,\n\n";
                    $vars = [
                        'displayName' => $fullName,
                        'email' => $provisionEmail,
                        'temporaryPassword' => $generatedPassword,
                        'greeting' => $greeting,
                    ];
                    $subj = 'Your VP Ride driver app login';
                    $body = "{greeting}An administrator created your VP Ride account for driver tools.\n\n"
                        . "Sign in with:\n  Email: {email}\n  Temporary password: {temporaryPassword}\n\n"
                        . "Open the VP Ride app, choose email sign-in, then change your password from the profile or forgot-password flow if you like.\n\n— VP Ride";
                    $subj = Mailer::expandTemplate($subj, $vars);
                    $body = Mailer::expandTemplate($body, $vars);
                    $driverMailOk = Mailer::sendPlain($provisionEmail, $subj, $body, $from);
                    if (! $driverMailOk) {
                        error_log('[vpride] driver welcome email failed for ' . $provisionEmail);
                    }
                }

                if ($isNew) {
                    $loc = Config::url('/drivers/' . $newId);
                    if ($generatedPassword !== null) {
                        $loc .= (str_contains($loc, '?') ? '&' : '?') . ($driverMailOk ? 'welcome=1' : 'driver_mail=0');
                    }
                    header('Location: ' . $loc);
                    exit;
                }
                if ($generatedPassword !== null) {
                    $message = $driverMailOk
                        ? 'Driver saved. Login details were sent to the driver&apos;s email.'
                        : 'Driver saved, but the login email could not be sent. Check server mail settings or share the temporary password manually.';
                } else {
                    $message = 'Driver saved.';
                }
            } catch (Throwable $e) {
                $msg = $e->getMessage();
                if ($needsProvision && $msg === 'email_taken') {
                    $error = 'That email already has a VP Ride login. Use a different email for this driver, or resolve the duplicate account in the database.';
                } else {
                    $error = $msg;
                }
            }
        }
    }
}

$titleBit = $isNew ? 'New driver' : 'Edit driver #' . $id;
header('Content-Type: text/html; charset=utf-8');
$pageTitle = $titleBit . ' · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'drivers';
$vpTopbarTitle = $isNew ? 'New driver' : 'Edit driver';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<header class="vp-page-hero">
  <?php
    vp_breadcrumbs([
        ['label' => 'Drivers', 'href' => vp_url('/drivers')],
        ['label' => $titleBit, 'href' => null],
    ]);
?>
  <h1 class="vp-page-title"><?= vp_h($titleBit) ?></h1>
  <p class="vp-page-desc">Assign an <strong>active</strong> vehicle: owner-operators need a <strong>personal</strong> car row; company drivers need a <strong>company</strong> car row from <a href="<?= vp_h(vp_url('/fleet')) ?>">Car management</a>.</p>
</header>

<?php if ($message !== '') { ?>
  <div class="vp-alert vp-alert--success" role="status"><?= vp_h($message) ?></div>
<?php } ?>
<?php if ($error !== '') { ?>
  <div class="vp-alert vp-alert--error" role="alert"><?= vp_h($error) ?></div>
<?php } ?>

<section class="vp-card">
  <div class="vp-card__pad">
    <form method="post" action="<?= vp_h(vp_url($isNew ? '/drivers/new' : '/drivers/' . $id)) ?>" class="vp-stack-form vp-stack-form--wide">
      <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
      <div class="vp-field">
        <label class="vp-label" for="full_name">Full name</label>
        <input class="vp-input" id="full_name" name="full_name" type="text" value="<?= vp_h($fullName) ?>" maxlength="120" required autocomplete="name">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="driver_kind">Driver type</label>
        <select class="vp-input" id="driver_kind" name="driver_kind" required>
          <option value="owner_operator"<?= $driverKind === 'owner_operator' ? ' selected' : '' ?>>Owner-operator (uses own car)</option>
          <option value="company_driver"<?= $driverKind === 'company_driver' ? ' selected' : '' ?>>Company driver (brand / fleet vehicle)</option>
        </select>
        <p class="vp-field-hint">This is the <strong>business role</strong> (who owns the car relationship). It is separate from <strong>vehicle assignment mode</strong> below (fixed vs pool / &ldquo;flexible&rdquo;).</p>
      </div>
      <?php if (SchemaInspector::columnExists($pdo, 'fleet_drivers', 'vehicle_assignment_mode')) { ?>
      <div class="vp-field">
        <label class="vp-label" for="vehicle_assignment_mode">Vehicle assignment (VFH)</label>
        <select class="vp-input" id="vehicle_assignment_mode" name="vehicle_assignment_mode" required>
          <option value="fixed"<?= $vehicleAssignmentMode === 'fixed' ? ' selected' : '' ?>>Fixed — one primary vehicle for this driver</option>
          <option value="flexible"<?= $vehicleAssignmentMode === 'flexible' ? ' selected' : '' ?>>Flexible — pool / multi-vehicle (any vehicle you assign per shift; &ldquo;super&rdquo; / non-dedicated in bylaws)</option>
        </select>
        <p class="vp-field-hint">Database values: <code class="vp-inline-code">fixed</code> and <code class="vp-inline-code">flexible</code>. Each completed ride should still be tied to a vehicle when the trip runs; <strong>flexible</strong> allows changing which car is on duty.</p>
      </div>
      <?php } ?>
      <div class="vp-field" style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
        <div>
          <label class="vp-label" for="phone">Phone</label>
          <input class="vp-input" id="phone" name="phone" type="text" value="<?= vp_h($phone) ?>" maxlength="32" autocomplete="tel">
        </div>
        <div>
          <label class="vp-label" for="email">Email</label>
          <input class="vp-input" id="email" name="email" type="email" value="<?= vp_h($email) ?>" maxlength="255" autocomplete="email"<?= $isNew ? ' required' : '' ?>>
        </div>
      </div>
      <div class="vp-field">
        <label class="vp-label" for="earnings_percent_override">Driver earnings % override (optional)</label>
        <input class="vp-input" id="earnings_percent_override" name="earnings_percent_override" type="number" min="0" max="100" step="0.01" value="<?= vp_h($earningsPercentOverride) ?>" placeholder="Leave blank for global default (Settings → Fees &amp; payouts)">
        <p class="vp-field-hint">When set, this driver&apos;s share of each completed trip fare uses this percent instead of the global default. Requires the <code class="vp-inline-code">earnings_percent_override</code> column (run cancellation/earnings migration).</p>
      </div>
      <div class="vp-field">
        <label class="vp-label" for="fleet_vehicle_id">Assigned vehicle</label>
        <select class="vp-input" id="fleet_vehicle_id" name="fleet_vehicle_id">
          <option value="0">— None (assign later) —</option>
          <?php foreach ($vehicles as $v) {
              $vid = (int) $v['id'];
              $plate = (string) $v['plate_number'];
              $mm = trim(((string) ($v['make'] ?? '')) . ' ' . ((string) ($v['model'] ?? '')));
              $own = (string) $v['ownership'];
              $badge = $own === 'company' ? ('Company: ' . (string) ($v['company_fleet_label'] ?? '')) : 'Personal';
              $label = $plate . ($mm !== '' ? ' · ' . $mm : '') . ' · ' . $badge;
              ?>
            <option value="<?= $vid ?>" data-ownership="<?= vp_h($own) ?>"<?= $fleetVehicleId === $vid ? ' selected' : '' ?>><?= vp_h($label) ?></option>
          <?php } ?>
        </select>
        <p class="vp-field-hint">Only <strong>active</strong> vehicles appear here. The server checks that the vehicle ownership matches the driver type when you save. Each vehicle may be assigned to only one <strong>active</strong> or <strong>pending</strong> driver at a time (suspend or unassign others first).</p>
      </div>
      <div class="vp-field">
        <label class="vp-label" for="license_number">License / permit ID (optional)</label>
        <input class="vp-input vp-input--mono" id="license_number" name="license_number" type="text" value="<?= vp_h($licenseNumber) ?>" maxlength="64" autocomplete="off">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="status">Status</label>
        <select class="vp-input" id="status" name="status" required>
          <option value="pending"<?= $status === 'pending' ? ' selected' : '' ?>>Pending</option>
          <option value="active"<?= $status === 'active' ? ' selected' : '' ?>>Active</option>
          <option value="suspended"<?= $status === 'suspended' ? ' selected' : '' ?>>Suspended</option>
        </select>
      </div>
      <div class="vp-field">
        <label class="vp-label" for="notes">Internal notes</label>
        <textarea class="vp-input" id="notes" name="notes" rows="3" maxlength="512" autocomplete="off"><?= vp_h($notes) ?></textarea>
      </div>
      <div class="vp-toolbar">
        <button type="submit" class="vp-btn vp-btn--primary">Save</button>
        <a class="vp-btn vp-btn--ghost" href="<?= vp_url('/drivers') ?>">Cancel</a>
      </div>
    </form>
  </div>
</section>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
