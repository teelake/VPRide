<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/RideRepository.php';
require_once $backendRoot . '/src/PlatformPromoSettingsRepository.php';
require_once $backendRoot . '/src/FarePromoService.php';
require_once $backendRoot . '/src/FixedPricingService.php';
require_once $backendRoot . '/src/DispatchService.php';
require_once $backendRoot . '/src/SchemaInspector.php';
require_once $backendRoot . '/src/ConsoleRiderService.php';
require_once $backendRoot . '/src/RideRequestNotifier.php';
use VprideBackend\ConsoleRiderService;
use VprideBackend\DispatchService;
use VprideBackend\FarePromoService;
use VprideBackend\FixedPricingService;
use VprideBackend\PlatformPromoSettingsRepository;
use VprideBackend\RideRequestNotifier;
use VprideBackend\RideRepository;
use VprideBackend\SchemaInspector;

use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('rides.create_manual');

$pdo = Database::pdo();
$csrf = Auth::csrfToken();
$error = '';

$riderMode = 'existing';
$riderEmail = '';
$newDisplayName = '';
$newPhone = '';
$newEmailOpt = '';
$plat = '';
$plng = '';
$pickAddr = '';
$dlat = '';
$dlng = '';
$dropAddr = '';
$roundTrip = false;
$scheduledLocal = '';
$skipAuto = false;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session.';
    } else {
        $riderMode = in_array(($_POST['rider_mode'] ?? ''), ['existing', 'new'], true)
            ? (string) $_POST['rider_mode']
            : 'existing';
        $riderEmail = trim(strtolower((string) ($_POST['rider_email'] ?? '')));
        $newDisplayName = trim((string) ($_POST['new_display_name'] ?? ''));
        $newPhone = (string) ($_POST['new_phone'] ?? '');
        $newEmailOpt = trim(strtolower((string) ($_POST['new_email'] ?? '')));
        $plat = (float) ($_POST['pickup_lat'] ?? 0);
        $plng = (float) ($_POST['pickup_lng'] ?? 0);
        $pickAddr = trim((string) ($_POST['pickup_address'] ?? ''));
        $dlat = trim((string) ($_POST['dropoff_lat'] ?? ''));
        $dlng = trim((string) ($_POST['dropoff_lng'] ?? ''));
        $dropAddr = trim((string) ($_POST['dropoff_address'] ?? ''));
        $roundTrip = ! empty($_POST['round_trip']);
        $scheduledLocal = trim((string) ($_POST['scheduled_pickup_local'] ?? ''));
        $skipAuto = ! empty($_POST['skip_auto_dispatch']);

        if (($plat === 0.0 && $plng === 0.0) || $plat < -90 || $plat > 90 || $plng < -180 || $plng > 180) {
            $error = 'Invalid pickup coordinates.';
        } else {
            $distanceKm = null;
            $dlatF = $dlat !== '' ? (float) $dlat : null;
            $dlngF = $dlng !== '' ? (float) $dlng : null;
            if ($dlatF !== null && $dlngF !== null && ! ($dlatF === 0.0 && $dlngF === 0.0)) {
                if ($dlatF < -90 || $dlatF > 90 || $dlngF < -180 || $dlngF > 180) {
                    $error = 'Invalid drop-off coordinates.';
                } else {
                    $distanceKm = round(FixedPricingService::haversineKm($plat, $plng, $dlatF, $dlngF), 5);
                }
            }
            if ($roundTrip && $distanceKm === null) {
                $error = 'Round trip needs a valid drop-off (not empty).';
            }

            $riderUserId = 0;
            if ($error === '') {
                $console = new ConsoleRiderService($pdo);
                $res = $console->resolveRiderForConsoleBooking(
                    $riderMode === 'new',
                    $riderEmail,
                    $newDisplayName,
                    $newPhone,
                    $newEmailOpt !== '' ? $newEmailOpt : null,
                );
                if (! $res['ok']) {
                    $error = $res['message'];
                } else {
                    $riderUserId = $res['riderUserId'];
                }
            }

            $settings = PlatformPromoSettingsRepository::tableExists($pdo)
                ? (new PlatformPromoSettingsRepository($pdo))->getSettings()
                : null;

            $scheduledUtc = null;
            if ($error === '' && $scheduledLocal !== '') {
                if ($settings === null) {
                    $error = 'Platform pricing settings are required for scheduled pickups.';
                } else {
                    try {
                        $dt = new DateTimeImmutable($scheduledLocal);
                    } catch (Throwable) {
                        $error = 'Invalid scheduled pickup time.';
                        $dt = null;
                    }
                    if ($error === '' && isset($dt)) {
                        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
                        $dtUtc = $dt->setTimezone(new DateTimeZone('UTC'));
                        if ($dtUtc < $nowUtc->modify('+5 minutes')) {
                            $error = 'Scheduled pickup must be at least 5 minutes from now (UTC / browser local).';
                        } else {
                            $maxDays = (int) ($settings['advance_booking_max_days'] ?? 30);
                            $maxUtc = $nowUtc->modify('+' . max(1, min(365, $maxDays)) . ' days');
                            if ($dtUtc > $maxUtc) {
                                $error = 'Scheduled pickup is too far in the future for platform rules.';
                            } else {
                                $scheduledUtc = $dtUtc->format('Y-m-d H:i:s');
                            }
                        }
                    }
                }
            }

            if ($error === '' && $scheduledUtc !== null) {
                $ridesCheck = new RideRepository($pdo);
                if ($ridesCheck->countFutureScheduledBookingsForRider($riderUserId) > 0) {
                    $error = 'That rider already has a future scheduled booking.';
                }
            }

            if ($error === '' && $distanceKm !== null && $settings !== null) {
                $baseFare = FixedPricingService::fareBeforePromosFromDistance($settings, $distanceKm);
            } else {
                $baseFare = null;
            }

            if ($error === '') {
                $pricing = PlatformPromoSettingsRepository::tableExists($pdo)
                    ? (new FarePromoService($pdo))->computeForNewRide($riderUserId, null, $baseFare)
                    : [
                        'estimated_fare' => 1500.0,
                        'promo_discount' => 0.0,
                        'final_fare' => 1500.0,
                        'currency' => 'NGN',
                        'decimal_places' => 2,
                        'applied_promotion_id' => null,
                        'promo_code_used' => null,
                        'reward_grant_id' => null,
                    ];
                $pricingReturn = null;
                if ($roundTrip && $distanceKm !== null && PlatformPromoSettingsRepository::tableExists($pdo) && $settings !== null) {
                    $baseRet = FixedPricingService::fareBeforePromosFromDistance($settings, $distanceKm);
                    $pricingReturn = (new FarePromoService($pdo))->computeForNewRide(
                        $riderUserId,
                        null,
                        $baseRet,
                        true,
                    );
                }
                $pricingPayload = static function (array $p): array {
                    return [
                        'estimated_fare' => $p['estimated_fare'],
                        'promo_discount' => $p['promo_discount'],
                        'final_fare' => $p['final_fare'],
                        'currency' => $p['currency'],
                        'applied_promotion_id' => $p['applied_promotion_id'] ?? null,
                        'promo_code_used' => $p['promo_code_used'] ?? null,
                        'reward_grant_id' => null,
                    ];
                };
                $metaBase = [
                    'dropoff_lat' => $dlatF,
                    'dropoff_lng' => $dlngF,
                    'dropoff_address' => $dropAddr !== '' ? $dropAddr : null,
                    'scheduled_pickup_at' => $scheduledUtc,
                    'distance_km' => $distanceKm,
                ];
                $rides = new RideRepository($pdo);
                $idsForRedirect = [];
                try {
                    if (! $roundTrip || $pricingReturn === null) {
                        $newId = $rides->createRequested(
                            $riderUserId,
                            $plat,
                            $plng,
                            $pickAddr !== '' ? $pickAddr : null,
                            $pricingPayload($pricing),
                            array_merge($metaBase, ['trip_leg' => 'single']),
                        );
                        $idsForRedirect[] = $newId;
                    } else {
                        $pdo->beginTransaction();
                        try {
                            $idOut = $rides->createRequested(
                                $riderUserId,
                                $plat,
                                $plng,
                                $pickAddr !== '' ? $pickAddr : null,
                                $pricingPayload($pricing),
                                array_merge($metaBase, ['trip_leg' => 'outbound']),
                            );
                            $idRet = $rides->createRequested(
                                $riderUserId,
                                (float) $dlatF,
                                (float) $dlngF,
                                $dropAddr !== '' ? $dropAddr : null,
                                $pricingPayload($pricingReturn),
                                [
                                    'dropoff_lat' => $plat,
                                    'dropoff_lng' => $plng,
                                    'dropoff_address' => $pickAddr !== '' ? $pickAddr : null,
                                    'scheduled_pickup_at' => null,
                                    'distance_km' => $distanceKm,
                                    'trip_leg' => 'return',
                                    'companion_ride_id' => $idOut,
                                ],
                            );
                            $rides->setCompanionRideIds($idOut, $idRet);
                            $pdo->commit();
                            $idsForRedirect[] = $idOut;
                        } catch (Throwable $e) {
                            $pdo->rollBack();
                            throw $e;
                        }
                    }
                } catch (Throwable $e) {
                    $error = 'Could not create ride: ' . $e->getMessage();
                }
                if ($error === '' && $idsForRedirect !== []) {
                    foreach ($idsForRedirect as $ridNew) {
                        if (SchemaInspector::columnExists($pdo, 'rides', 'console_booking')) {
                            $markConsole = $pdo->prepare('UPDATE rides SET console_booking = 1 WHERE id = ?');
                            $markConsole->execute([$ridNew]);
                        }
                    }
                    foreach ($idsForRedirect as $ridNew) {
                        try {
                            RideRequestNotifier::notifyIfEnabled($pdo, (int) $ridNew);
                        } catch (Throwable $e) {
                            error_log('[vpride] admin ride create notify: ' . $e->getMessage());
                        }
                    }
                    if (! $skipAuto) {
                        try {
                            $dispatch = new DispatchService($pdo);
                            if (! $roundTrip || $pricingReturn === null) {
                                $dispatch->tryAutoAssign((int) $idsForRedirect[0]);
                            } else {
                                $dispatch->tryAutoAssign((int) $idsForRedirect[0]);
                            }
                        } catch (Throwable $e) {
                            error_log('[vpride] admin ride create dispatch: ' . $e->getMessage());
                        }
                    }
                    header('Location: ' . Config::url('/rides/' . (int) $idsForRedirect[0] . '/dispatch'));
                    exit;
                }
            }
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Create booking · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'rides';
$vpTopbarTitle = 'Create booking';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<?php vp_breadcrumbs([
    ['label' => 'Bookings', 'href' => vp_url('/rides')],
    ['label' => 'Create booking', 'href' => null],
]); ?>

<header class="vp-page-hero">
  <h1 class="vp-page-title">Create booking</h1>
  <p class="vp-page-desc">Create a ride for an existing app account, or <strong>register a phone-first rider</strong> and book immediately. Round trip and scheduled pickup follow the same rules as the mobile app. Staff can receive email on new rides from <a href="<?= vp_h(vp_url('/settings')) ?>">Settings → Email</a>.</p>
</header>

<?php if ($error !== '') { ?>
  <p class="vp-banner vp-banner--danger" role="alert"><?= vp_h($error) ?></p>
<?php } ?>

<section class="vp-card">
  <div class="vp-card__pad">
    <form method="post" class="vp-stack-form vp-stack-form--wide">
      <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
      <fieldset class="vp-field" style="border:none;padding:0;margin:0;">
        <legend class="vp-label">Rider</legend>
        <div class="vp-field">
          <label class="vp-label" for="rider_mode">Account</label>
          <select class="vp-input" id="rider_mode" name="rider_mode" aria-label="Rider account">
            <option value="existing"<?= $riderMode === 'existing' ? ' selected' : '' ?>>Existing rider (email)</option>
            <option value="new"<?= $riderMode === 'new' ? ' selected' : '' ?>>New rider (name + phone, optional email)</option>
          </select>
        </div>
        <div class="vp-field" id="field_existing_email">
          <label class="vp-label" for="rider_email">Rider email</label>
          <input class="vp-input" id="rider_email" name="rider_email" type="email" value="<?= vp_h($riderEmail) ?>"<?= $riderMode === 'existing' ? ' required' : '' ?>>
        </div>
        <div class="vp-field" id="field_new_rider" style="display:<?= $riderMode === 'new' ? 'block' : 'none' ?>;">
          <label class="vp-label" for="new_display_name">Display name</label>
          <input class="vp-input" id="new_display_name" name="new_display_name" value="<?= vp_h($newDisplayName) ?>">
          <label class="vp-label" for="new_phone" style="margin-top:0.75rem;">Phone (digits; used to de-duplicate)</label>
          <input class="vp-input" id="new_phone" name="new_phone" type="text" inputmode="tel" autocomplete="tel" value="<?= vp_h($newPhone) ?>">
          <label class="vp-label" for="new_email" style="margin-top:0.75rem;">Email (optional)</label>
          <input class="vp-input" id="new_email" name="new_email" type="email" value="<?= vp_h($newEmailOpt) ?>">
          <p class="vp-field-hint">If email is left blank, a placeholder address is generated so the account can exist in the database; the customer can be reached by phone for this booking.</p>
        </div>
      </fieldset>
      <div class="vp-field">
        <label class="vp-label" for="pickup_lat">Pickup latitude</label>
        <input class="vp-input" id="pickup_lat" name="pickup_lat" type="text" required value="<?= vp_h($plat) ?>">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="pickup_lng">Pickup longitude</label>
        <input class="vp-input" id="pickup_lng" name="pickup_lng" type="text" required value="<?= vp_h($plng) ?>">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="pickup_address">Pickup address (optional)</label>
        <input class="vp-input" id="pickup_address" name="pickup_address" value="<?= vp_h($pickAddr) ?>">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="dropoff_lat">Drop-off latitude (optional)</label>
        <input class="vp-input" id="dropoff_lat" name="dropoff_lat" value="<?= vp_h($dlat) ?>">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="dropoff_lng">Drop-off longitude (optional)</label>
        <input class="vp-input" id="dropoff_lng" name="dropoff_lng" value="<?= vp_h($dlng) ?>">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="dropoff_address">Drop-off address (optional)</label>
        <input class="vp-input" id="dropoff_address" name="dropoff_address" value="<?= vp_h($dropAddr) ?>">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="scheduled_pickup_local">Scheduled pickup (optional, local / browser time)</label>
        <input class="vp-input" id="scheduled_pickup_local" name="scheduled_pickup_local" type="datetime-local" value="<?= vp_h($scheduledLocal) ?>">
        <p class="vp-field-hint">Leave empty for “as soon as possible”. Must be at least 5 minutes ahead and within platform advance-booking limits.</p>
      </div>
      <label class="vp-toggle" style="display:flex;align-items:flex-start;gap:0.65rem;cursor:pointer;">
        <input type="checkbox" name="round_trip" value="1"<?= $roundTrip ? ' checked' : '' ?> style="margin-top:0.2rem;" id="round_trip">
        <span>Round trip (outbound to drop-off, return leg to pickup). Requires valid drop-off coordinates above.</span>
      </label>
      <label class="vp-toggle" style="display:flex;align-items:flex-start;gap:0.65rem;cursor:pointer;">
        <input type="checkbox" name="skip_auto_dispatch" value="1"<?= $skipAuto ? ' checked' : '' ?> style="margin-top:0.2rem;">
        <span>Skip automatic driver assignment (assign manually afterward).</span>
      </label>
      <button type="submit" class="vp-btn vp-btn--primary">Create ride</button>
    </form>
  </div>
</section>
<script>
(function () {
  var mode = document.getElementById('rider_mode');
  var ex = document.getElementById('field_existing_email');
  var nr = document.getElementById('field_new_rider');
  var email = document.getElementById('rider_email');
  if (!mode || !ex || !nr || !email) return;
  function sync() {
    var v = mode.value;
    ex.style.display = v === 'existing' ? 'block' : 'none';
    nr.style.display = v === 'new' ? 'block' : 'none';
    email.required = v === 'existing';
  }
  mode.addEventListener('change', sync);
  sync();
})();
</script>
<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
