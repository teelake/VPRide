<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/AppSettingsRepository.php';

use VprideBackend\AppSettingsRepository;
use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('settings.manage');

$admin = Auth::currentAdmin();
$repo = new AppSettingsRepository(Database::pdo());
$settings = $repo->getPublicSettings();
$message = '';
$error = '';
$csrf = Auth::csrfToken();

$envOverridesClient = trim(getenv('GOOGLE_OAUTH_CLIENT_ID') ?: '') !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session.';
    } else {
        try {
            $welcomeOpacityPct = (int) ($_POST['welcomeOverlayOpacity'] ?? 78);
            if ($welcomeOpacityPct < 0) {
                $welcomeOpacityPct = 0;
            }
            if ($welcomeOpacityPct > 100) {
                $welcomeOpacityPct = 100;
            }
            $repo->savePublicSettings(
                [
                    'googleWebClientId' => (string) ($_POST['googleWebClientId'] ?? ''),
                    'mapsApiKey' => (string) ($_POST['mapsApiKey'] ?? ''),
                    'minimumAppVersion' => (string) ($_POST['minimumAppVersion'] ?? ''),
                    'welcome' => [
                        'backgroundImageUrl' => (string) ($_POST['welcomeBackgroundImageUrl'] ?? ''),
                        'overlayColor' => (string) ($_POST['welcomeOverlayColor'] ?? '#F0F0F0'),
                        'overlayOpacity' => $welcomeOpacityPct / 100.0,
                    ],
                    'features' => [
                        'rideBookingEnabled' => isset($_POST['feat_ride_booking']),
                        'promoBannerEnabled' => isset($_POST['feat_promo']),
                        'maintenanceMode' => isset($_POST['feat_maintenance']),
                        'maintenanceMessage' => (string) ($_POST['maintenanceMessage'] ?? ''),
                        'helpCenterUrl' => (string) ($_POST['helpCenterUrl'] ?? ''),
                        'requireSignInForHome' => isset($_POST['feat_require_signin_home']),
                    ],
                ],
                $admin[0],
            );
            $settings = $repo->getPublicSettings();
            $message = 'Public app settings saved. Mobile apps will receive updates on the next config fetch.';
        } catch (Throwable $e) {
            $error = 'Could not save: ' . $e->getMessage();
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Settings · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'settings';
$vpTopbarTitle = 'Settings';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<header class="vp-page-hero">
  <?php
    vp_breadcrumbs([
        ['label' => 'Dashboard', 'href' => vp_url('/admin/dashboard')],
        ['label' => 'Settings', 'href' => null],
    ]);
?>
  <h1 class="vp-page-title">Public app configuration</h1>
  <p class="vp-page-desc">Keys, version rules, and mobile feature flags delivered to VP Ride apps on each config sync. The app should read the <code class="vp-inline-code">features</code> object from the public config response.</p>
</header>

<?php if ($message !== '') { ?>
  <div class="vp-alert vp-alert--success" role="status"><?= vp_h($message) ?></div>
<?php } ?>
<?php if ($error !== '') { ?>
  <div class="vp-alert vp-alert--error" role="alert"><?= vp_h($error) ?></div>
<?php } ?>

<form method="post" action="<?= vp_url('/admin/settings') ?>" class="vp-settings-form">
  <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">

  <section class="vp-card" aria-labelledby="settings-form-heading">
    <div class="vp-card__pad">
      <h2 id="settings-form-heading" class="vp-section-title">Client-visible keys</h2>
      <div class="vp-stack-form">
        <div class="vp-field">
          <label class="vp-label" for="googleWebClientId">Google OAuth Web client ID</label>
          <input class="vp-input vp-input--mono" id="googleWebClientId" name="googleWebClientId" type="text" value="<?= vp_h($settings['googleWebClientId']) ?>" placeholder="123….apps.googleusercontent.com" autocomplete="off">
          <p class="vp-field-hint">Used by the app for Google Sign-In and must match the audience your backend verifies. <?php if ($envOverridesClient) { ?><strong class="vp-hint-warn">Overridden for JWT verification by <code>GOOGLE_OAUTH_CLIENT_ID</code> in <code>.env</code>.</strong><?php } else { ?>If <code>.env</code> is empty, the backend uses this value to verify ID tokens.<?php } ?></p>
        </div>

        <div class="vp-field">
          <label class="vp-label" for="mapsApiKey">Maps / Geocoding API key</label>
          <input class="vp-input vp-input--mono" id="mapsApiKey" name="mapsApiKey" type="text" value="<?= vp_h($settings['mapsApiKey']) ?>" placeholder="AIza…" autocomplete="off">
          <p class="vp-field-hint">Returned to the app in <code class="vp-inline-code">GET /api/v1/config/public</code> as <code class="vp-inline-code">mapsApiKey</code> (geocoding). If this field is empty, the API can still expose a key from <code class="vp-inline-code">MAPS_API_KEY</code> or <code class="vp-inline-code">GOOGLE_MAPS_API_KEY</code> in <code>.env</code>. Native Maps SDK builds should also set the key in Gradle / iOS (<code>maps.api.key</code> / <code>GMSApiKey</code>).</p>
        </div>

        <div class="vp-field">
          <label class="vp-label" for="minimumAppVersion">Minimum app version</label>
          <input class="vp-input" id="minimumAppVersion" name="minimumAppVersion" type="text" value="<?= vp_h($settings['minimumAppVersion']) ?>" placeholder="1.0.0">
          <p class="vp-field-hint">Semantic version string for future force-upgrade checks in the mobile app.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="vp-card" aria-labelledby="welcome-heading">
    <div class="vp-card__pad">
      <h2 id="welcome-heading" class="vp-section-title">Welcome screen (mobile)</h2>
      <p class="vp-field-hint" style="margin:-0.35rem 0 1.25rem;">Delivered in <code class="vp-inline-code">GET /api/v1/config/public</code> as <code class="vp-inline-code">welcome</code>. Use a high-resolution JPG/PNG/WebP URL (HTTPS). The overlay sits on top of the image so text stays readable.</p>
      <div class="vp-stack-form">
        <div class="vp-field">
          <label class="vp-label" for="welcomeBackgroundImageUrl">Background image URL</label>
          <input class="vp-input vp-input--mono" id="welcomeBackgroundImageUrl" name="welcomeBackgroundImageUrl" type="url" value="<?= vp_h($settings['welcome']['backgroundImageUrl'] ?? '') ?>" placeholder="https://…/hero.jpg" autocomplete="off">
        </div>
        <div class="vp-field">
          <label class="vp-label" for="welcomeOverlayColor">Overlay color (hex)</label>
          <input class="vp-input vp-input--mono" id="welcomeOverlayColor" name="welcomeOverlayColor" type="text" value="<?= vp_h($settings['welcome']['overlayColor'] ?? '#F0F0F0') ?>" placeholder="#F0F0F0" pattern="#[0-9A-Fa-f]{6}" autocomplete="off">
        </div>
        <div class="vp-field">
          <label class="vp-label" for="welcomeOverlayOpacity">Overlay strength (%)</label>
          <input class="vp-input" id="welcomeOverlayOpacity" name="welcomeOverlayOpacity" type="number" min="0" max="100" step="1" value="<?= (int) round(((float) ($settings['welcome']['overlayOpacity'] ?? 0.78)) * 100) ?>">
          <p class="vp-field-hint">0 = image only, 100 = solid overlay color.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="vp-card" aria-labelledby="features-heading">
    <div class="vp-card__pad">
      <h2 id="features-heading" class="vp-section-title">Mobile app features</h2>
      <p class="vp-field-hint" style="margin:-0.35rem 0 1.25rem;">Toggles are exposed in the API as booleans under <code class="vp-inline-code">features</code>. Wire the native app to respect them (ride booking and maintenance are enforced on the server for ride creation).</p>
      <div class="vp-feature-grid">
        <label class="vp-toggle">
          <input class="vp-sr-only" type="checkbox" name="feat_ride_booking"<?= ! empty($settings['features']['rideBookingEnabled']) ? ' checked' : '' ?>>
          <span class="vp-toggle__ui"></span>
          <span class="vp-toggle__text"><strong>Ride booking</strong><span class="vp-toggle__sub">Allow new ride requests from the app</span></span>
        </label>
        <label class="vp-toggle">
          <input class="vp-sr-only" type="checkbox" name="feat_promo"<?= ! empty($settings['features']['promoBannerEnabled']) ? ' checked' : '' ?>>
          <span class="vp-toggle__ui"></span>
          <span class="vp-toggle__text"><strong>Promo banner</strong><span class="vp-toggle__sub">Client-side: show marketing strip when true</span></span>
        </label>
        <label class="vp-toggle">
          <input class="vp-sr-only" type="checkbox" name="feat_maintenance"<?= ! empty($settings['features']['maintenanceMode']) ? ' checked' : '' ?>>
          <span class="vp-toggle__ui"></span>
          <span class="vp-toggle__text"><strong>Maintenance mode</strong><span class="vp-toggle__sub">Blocks new ride requests (503) with optional message</span></span>
        </label>
        <label class="vp-toggle">
          <input class="vp-sr-only" type="checkbox" name="feat_require_signin_home"<?= ! empty($settings['features']['requireSignInForHome']) ? ' checked' : '' ?>>
          <span class="vp-toggle__ui"></span>
          <span class="vp-toggle__text"><strong>Require sign-in for Map / Home</strong><span class="vp-toggle__sub">Recommended: on for production. When on, unsigned users stay on welcome (no guest map link). Turn off only if you want map browsing without an account.</span></span>
        </label>
      </div>
      <div class="vp-field" style="margin-top:1.25rem;">
        <label class="vp-label" for="maintenanceMessage">Maintenance message</label>
        <textarea class="vp-input vp-textarea vp-textarea--sm" id="maintenanceMessage" name="maintenanceMessage" rows="2" placeholder="Short note for riders"><?= vp_h($settings['features']['maintenanceMessage']) ?></textarea>
      </div>
      <div class="vp-field">
        <label class="vp-label" for="helpCenterUrl">Help center URL</label>
        <input class="vp-input" id="helpCenterUrl" name="helpCenterUrl" type="url" value="<?= vp_h($settings['features']['helpCenterUrl']) ?>" placeholder="https://…">
      </div>
    </div>
  </section>

  <div class="vp-form-actions vp-form-actions--page">
    <button type="submit" class="vp-btn vp-btn--primary">Save settings</button>
  </div>
</form>

<section class="vp-card vp-card--note" aria-labelledby="doc-heading">
  <div class="vp-card__pad">
    <h2 id="doc-heading" class="vp-section-title">Google Cloud setup</h2>
    <ul class="vp-doc-list">
      <li><strong>Web client ID:</strong> Google Cloud Console → APIs &amp; Services → Credentials → Create credentials → OAuth client ID → Application type <em>Web application</em>. Use the same Google Cloud project as your Android/iOS OAuth clients.</li>
      <li><strong>Maps API key:</strong> Credentials → Create credentials → API key. Enable Maps SDK for Android, Maps SDK for iOS, and Geocoding API. Restrict the key (Android package + SHA-1, iOS bundle ID, or IP for backend-only keys).</li>
    </ul>
  </div>
</section>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
