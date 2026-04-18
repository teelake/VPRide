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

use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\DispatchService;
use VprideBackend\FarePromoService;
use VprideBackend\FixedPricingService;
use VprideBackend\PlatformPromoSettingsRepository;
use VprideBackend\RideRepository;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('rides.create_manual');

$pdo = Database::pdo();
$csrf = Auth::csrfToken();
$message = '';
$error = '';

$riderEmail = '';
$plat = '';
$plng = '';
$pickAddr = '';
$dlat = '';
$dlng = '';
$dropAddr = '';
$skipAuto = false;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session.';
    } else {
        $riderEmail = trim(strtolower((string) ($_POST['rider_email'] ?? '')));
        $plat = (float) ($_POST['pickup_lat'] ?? 0);
        $plng = (float) ($_POST['pickup_lng'] ?? 0);
        $pickAddr = trim((string) ($_POST['pickup_address'] ?? ''));
        $dlat = trim((string) ($_POST['dropoff_lat'] ?? ''));
        $dlng = trim((string) ($_POST['dropoff_lng'] ?? ''));
        $dropAddr = trim((string) ($_POST['dropoff_address'] ?? ''));
        $skipAuto = ! empty($_POST['skip_auto_dispatch']);

        if ($riderEmail === '' || ! filter_var($riderEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Valid rider email required.';
        } elseif (($plat === 0.0 && $plng === 0.0) || $plat < -90 || $plat > 90 || $plng < -180 || $plng > 180) {
            $error = 'Invalid pickup coordinates.';
        } else {
            $st = $pdo->prepare('SELECT id FROM rider_users WHERE email = ? LIMIT 1');
            $st->execute([$riderEmail]);
            $rid = $st->fetchColumn();
            if ($rid === false) {
                $error = 'No rider account with that email.';
            } else {
                $riderUserId = (int) $rid;
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
                if ($error === '') {
                    $settings = PlatformPromoSettingsRepository::tableExists($pdo)
                        ? (new PlatformPromoSettingsRepository($pdo))->getSettings()
                        : null;
                    $baseFare = null;
                    if ($distanceKm !== null && $settings !== null) {
                        $baseFare = FixedPricingService::fareBeforePromosFromDistance($settings, $distanceKm);
                    }
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
                    $pricingPayload = [
                        'estimated_fare' => $pricing['estimated_fare'],
                        'promo_discount' => $pricing['promo_discount'],
                        'final_fare' => $pricing['final_fare'],
                        'currency' => $pricing['currency'],
                        'applied_promotion_id' => $pricing['applied_promotion_id'] ?? null,
                        'promo_code_used' => $pricing['promo_code_used'] ?? null,
                        'reward_grant_id' => null,
                    ];
                    $meta = [
                        'dropoff_lat' => $dlatF,
                        'dropoff_lng' => $dlngF,
                        'dropoff_address' => $dropAddr !== '' ? $dropAddr : null,
                        'scheduled_pickup_at' => null,
                        'distance_km' => $distanceKm,
                        'trip_leg' => 'single',
                    ];
                    try {
                        $rides = new RideRepository($pdo);
                        $newId = $rides->createRequested(
                            $riderUserId,
                            $plat,
                            $plng,
                            $pickAddr !== '' ? $pickAddr : null,
                            $pricingPayload,
                            $meta,
                        );
                        if (! $skipAuto) {
                            try {
                                (new DispatchService($pdo))->tryAutoAssign($newId);
                            } catch (Throwable $e) {
                                error_log('[vpride] admin ride create dispatch: ' . $e->getMessage());
                            }
                        }
                        header('Location: ' . Config::url('/admin/rides/' . $newId . '/dispatch'));
                        exit;
                    } catch (Throwable $e) {
                        $error = 'Could not create ride: ' . $e->getMessage();
                    }
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
    ['label' => 'Bookings', 'href' => vp_url('/admin/rides')],
    ['label' => 'Create booking', 'href' => null],
]); ?>

<header class="vp-page-hero">
  <h1 class="vp-page-title">Create booking</h1>
  <p class="vp-page-desc">Creates a ride for an existing rider account (same as app booking). You are redirected to dispatch to assign a driver.</p>
</header>

<?php if ($error !== '') { ?>
  <p class="vp-banner vp-banner--danger" role="alert"><?= vp_h($error) ?></p>
<?php } ?>

<section class="vp-card">
  <div class="vp-card__pad">
    <form method="post" class="vp-stack-form vp-stack-form--wide">
      <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
      <div class="vp-field">
        <label class="vp-label" for="rider_email">Rider email</label>
        <input class="vp-input" id="rider_email" name="rider_email" type="email" required value="<?= vp_h($riderEmail) ?>">
      </div>
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
      <label class="vp-toggle" style="display:flex;align-items:flex-start;gap:0.65rem;cursor:pointer;">
        <input type="checkbox" name="skip_auto_dispatch" value="1"<?= $skipAuto ? ' checked' : '' ?> style="margin-top:0.2rem;">
        <span>Skip automatic driver assignment (assign manually afterward).</span>
      </label>
      <button type="submit" class="vp-btn vp-btn--primary">Create ride</button>
    </form>
  </div>
</section>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
