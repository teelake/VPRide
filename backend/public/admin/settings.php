<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/AppSettingsRepository.php';
require_once $backendRoot . '/src/WelcomeImageUpload.php';

use VprideBackend\AppSettingsRepository;
use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\WelcomeImageUpload;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('settings.manage');

$admin = Auth::currentAdmin();
$repo = new AppSettingsRepository(Database::pdo());
$settings = $repo->getPublicSettings();
$operations = $repo->getOperations();
$emailSettings = $repo->getEmailSettings();
$dispatch = $repo->getDispatchSettings();
$message = '';
$error = '';
$csrf = Auth::csrfToken();
$settingsTab = 'keys';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $t = (string) ($_POST['settings_ui_tab'] ?? '');
    if (in_array($t, ['keys', 'welcome', 'features', 'email', 'operations'], true)) {
        $settingsTab = $t;
    }
}

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

            $bgUrl = (string) ($_POST['welcomeBackgroundImageUrl'] ?? '');
            if (isset($_POST['welcome_clear_bg']) && $_POST['welcome_clear_bg'] === '1') {
                $bgUrl = '';
            } else {
                $uploaded = WelcomeImageUpload::saveFromRequest('welcomeBgUpload', $backendRoot);
                if ($uploaded !== null) {
                    $bgUrl = $uploaded;
                }
            }

            $repo->savePublicSettings(
                [
                    'googleWebClientId' => (string) ($_POST['googleWebClientId'] ?? ''),
                    'mapsApiKey' => (string) ($_POST['mapsApiKey'] ?? ''),
                    'minimumAppVersion' => (string) ($_POST['minimumAppVersion'] ?? ''),
                    'welcome' => [
                        'backgroundImageUrl' => $bgUrl,
                        'overlayColor' => (string) ($_POST['welcomeOverlayColor'] ?? '#F0F0F0'),
                        'overlayOpacity' => $welcomeOpacityPct / 100.0,
                        'brandWordmark' => (string) ($_POST['welcomeBrandWordmark'] ?? ''),
                        'headline' => (string) ($_POST['welcomeHeadline'] ?? ''),
                        'subhead' => (string) ($_POST['welcomeSubhead'] ?? ''),
                        'featureLeftTitle' => (string) ($_POST['welcomeFeatureLeft'] ?? ''),
                        'featureRightTitle' => (string) ($_POST['welcomeFeatureRight'] ?? ''),
                        'footerTagline' => (string) ($_POST['welcomeFooterTagline'] ?? ''),
                        'showFeatureRow' => isset($_POST['welcome_show_features']),
                        'showPagerDots' => isset($_POST['welcome_show_pager']),
                        'ctaRegister' => (string) ($_POST['welcomeCtaRegister'] ?? ''),
                        'ctaEmailLogin' => (string) ($_POST['welcomeCtaEmailLogin'] ?? ''),
                        'ctaGoogle' => (string) ($_POST['welcomeCtaGoogle'] ?? ''),
                    ],
                    'features' => [
                        'rideBookingEnabled' => isset($_POST['feat_ride_booking']),
                        'promoBannerEnabled' => isset($_POST['feat_promo']),
                        'maintenanceMode' => isset($_POST['feat_maintenance']),
                        'maintenanceMessage' => (string) ($_POST['maintenanceMessage'] ?? ''),
                        'helpCenterUrl' => (string) ($_POST['helpCenterUrl'] ?? ''),
                        'requireSignInForHome' => isset($_POST['feat_require_signin_home']),
                        'sosEnabled' => isset($_POST['feat_sos']),
                        'promoCodeEntryEnabled' => isset($_POST['feat_promo_code']),
                    ],
                    'email' => [
                        'mailFrom' => (string) ($_POST['emailMailFrom'] ?? ''),
                        'staffNotifyOnRiderSignup' => isset($_POST['email_staff_notify_rider']),
                        'staffNotifyEmails' => (string) ($_POST['emailStaffNotifyEmails'] ?? ''),
                        'staffNotifySubject' => (string) ($_POST['emailStaffNotifySubject'] ?? ''),
                        'staffNotifyBody' => (string) ($_POST['emailStaffNotifyBody'] ?? ''),
                        'sosNotifyEmails' => (string) ($_POST['emailSosNotifyEmails'] ?? ''),
                        'riderWelcomeEnabled' => isset($_POST['email_rider_welcome']),
                        'riderWelcomeSubject' => (string) ($_POST['emailRiderWelcomeSubject'] ?? ''),
                        'riderWelcomeBody' => (string) ($_POST['emailRiderWelcomeBody'] ?? ''),
                        'notifyOnNewRide' => isset($_POST['email_notify_new_ride']),
                        'newRideNotifyEmails' => (string) ($_POST['emailNewRideNotifyEmails'] ?? ''),
                        'newRideNotifySubject' => (string) ($_POST['emailNewRideNotifySubject'] ?? ''),
                        'newRideNotifyBody' => (string) ($_POST['emailNewRideNotifyBody'] ?? ''),
                    ],
                    'operations' => [
                        'riderCancellationFeeAmount' => (float) str_replace(
                            ',',
                            '.',
                            trim((string) ($_POST['op_rider_cancel_fee'] ?? '0')),
                        ),
                        'driverEarningsPercentGlobal' => (float) str_replace(
                            ',',
                            '.',
                            trim((string) ($_POST['op_driver_pct_global'] ?? '80')),
                        ),
                    ],
                    'dispatch' => [
                        'maxAutoDriverAttempts' => (int) ($_POST['dispatch_max_auto'] ?? 8),
                        'maxRiderDriverRejects' => (int) ($_POST['dispatch_max_rider_rejects'] ?? 2),
                        'tripConfirmedWhen' => in_array(
                            (string) ($_POST['dispatch_trip_confirmed'] ?? ''),
                            ['driver_assigned', 'driver_accepted'],
                            true,
                        ) ? (string) $_POST['dispatch_trip_confirmed'] : 'driver_accepted',
                    ],
                ],
                $admin[0],
            );
            $settings = $repo->getPublicSettings();
            $operations = $repo->getOperations();
            $emailSettings = $repo->getEmailSettings();
            $dispatch = $repo->getDispatchSettings();
            $message = 'Settings saved. Mobile apps receive key and feature updates on the next config sync.';
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
        ['label' => 'Dashboard', 'href' => vp_url('/dashboard')],
        ['label' => 'Settings', 'href' => null],
    ]);
?>
  <h1 class="vp-page-title">Public app configuration</h1>
  <p class="vp-page-desc"><strong>Riders</strong> create accounts in the mobile app (email or Google). <strong>Drivers</strong> are added only from this console — there is no driver self-sign-up in the app. Keys, version rules, feature flags, and outbound email for notifications are managed here.</p>
</header>

<?php if ($message !== '') { ?>
  <div class="vp-alert vp-alert--success" role="status"><?= vp_h($message) ?></div>
<?php } ?>
<?php if ($error !== '') { ?>
  <div class="vp-alert vp-alert--error" role="alert"><?= vp_h($error) ?></div>
<?php } ?>

<form method="post" action="<?= vp_url('/settings') ?>" class="vp-settings-form" enctype="multipart/form-data">
  <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">

  <div class="vp-settings-tabs" role="region" aria-label="Settings categories">
    <input type="radio" name="settings_ui_tab" id="settings_tab_keys" class="vp-sr-only" value="keys"<?= $settingsTab === 'keys' ? ' checked' : '' ?>>
    <input type="radio" name="settings_ui_tab" id="settings_tab_welcome" class="vp-sr-only" value="welcome"<?= $settingsTab === 'welcome' ? ' checked' : '' ?>>
    <input type="radio" name="settings_ui_tab" id="settings_tab_features" class="vp-sr-only" value="features"<?= $settingsTab === 'features' ? ' checked' : '' ?>>
    <input type="radio" name="settings_ui_tab" id="settings_tab_email" class="vp-sr-only" value="email"<?= $settingsTab === 'email' ? ' checked' : '' ?>>
    <input type="radio" name="settings_ui_tab" id="settings_tab_operations" class="vp-sr-only" value="operations"<?= $settingsTab === 'operations' ? ' checked' : '' ?>>

    <div class="vp-tablist" aria-label="Settings sections">
      <label class="vp-tab" for="settings_tab_keys">Keys &amp; limits</label>
      <label class="vp-tab" for="settings_tab_welcome">Welcome screen</label>
      <label class="vp-tab" for="settings_tab_features">App features</label>
      <label class="vp-tab" for="settings_tab_email">Email</label>
      <label class="vp-tab" for="settings_tab_operations">Fees &amp; payouts</label>
    </div>

    <div class="vp-tab-panels">
      <div class="vp-tab-panel vp-tab-panel--keys" id="settings_panel_keys">
        <section class="vp-card" aria-labelledby="settings-form-heading">
          <div class="vp-card__pad">
            <h2 id="settings-form-heading" class="vp-section-title">Client-visible keys</h2>
            <div class="vp-stack-form">
              <div class="vp-field">
                <label class="vp-label" for="googleWebClientId">Google Sign-In server client ID</label>
                <input class="vp-input vp-input--mono" id="googleWebClientId" name="googleWebClientId" type="text" value="<?= vp_h($settings['googleWebClientId']) ?>" placeholder="123….apps.googleusercontent.com" maxlength="512" pattern="[0-9a-zA-Z_-]+\.apps\.googleusercontent\.com" title="Server client ID ending in .apps.googleusercontent.com" autocomplete="off">
                <p class="vp-field-hint">Android and iOS use their own OAuth clients; this value is the <strong>server</strong> client ID passed to the app as <code class="vp-inline-code">serverClientId</code> so Google returns an ID token your API can verify. It must match the JWT audience. <?php if ($envOverridesClient) { ?><strong class="vp-hint-warn">Overridden for verification by <code>GOOGLE_OAUTH_CLIENT_ID</code> in <code>.env</code>.</strong><?php } else { ?>If <code>.env</code> is empty, the backend uses this value to verify ID tokens.<?php } ?></p>
              </div>

              <div class="vp-field">
                <label class="vp-label" for="mapsApiKey">Maps / Geocoding API key</label>
                <input class="vp-input vp-input--mono" id="mapsApiKey" name="mapsApiKey" type="text" value="<?= vp_h($settings['mapsApiKey']) ?>" placeholder="AIza…" maxlength="512" pattern="([A-Za-z0-9_-]{30,512})?" title="Leave blank to use .env, or enter an alphanumeric key (about 30+ characters)" autocomplete="off">
                <p class="vp-field-hint">Returned to the app in <code class="vp-inline-code">GET /api/v1/config/public</code> as <code class="vp-inline-code">mapsApiKey</code> (geocoding). The dashboard &quot;Live booking monitor&quot; map uses the <strong>Maps JavaScript API</strong> (pan, zoom, fullscreen); enable that API on the same key. Static Maps is used as a fallback when JavaScript is off. If this field is empty, the API can still expose a key from <code class="vp-inline-code">MAPS_API_KEY</code> or <code class="vp-inline-code">GOOGLE_MAPS_API_KEY</code> in <code>.env</code>. Native Maps SDK builds should also set the key in Gradle / iOS (<code>maps.api.key</code> / <code>GMSApiKey</code>).</p>
              </div>

              <div class="vp-field">
                <label class="vp-label" for="minimumAppVersion">Minimum app version</label>
                <input class="vp-input" id="minimumAppVersion" name="minimumAppVersion" type="text" value="<?= vp_h($settings['minimumAppVersion']) ?>" placeholder="1.0.0" maxlength="32" required pattern="\d+(\.\d+){1,2}([a-zA-Z0-9._+-]*)?" title="e.g. 1.0.0 or 1.2">
                <p class="vp-field-hint">Semantic version for future force-upgrade checks (e.g. <code class="vp-inline-code">1.0.0</code>). Validated on save.</p>
              </div>
            </div>
          </div>
        </section>

        <section class="vp-card vp-card--note" aria-labelledby="doc-heading">
          <div class="vp-card__pad">
            <h2 id="doc-heading" class="vp-section-title">Google Cloud setup</h2>
            <ul class="vp-doc-list">
              <li><strong>Server client ID (mobile + API):</strong> In Google Cloud Console → APIs &amp; Services → Credentials → Create credentials → OAuth client ID, create an application of type <em>Web application</em> — that is only Google’s label for this credential; you are not building a website. Use this client’s ID here and for <code class="vp-inline-code">GOOGLE_SERVER_CLIENT_ID</code> in app builds. Keep separate Android and iOS OAuth clients in the same project for the native Google Sign-In button.</li>
              <li><strong>Maps API key:</strong> Credentials → Create credentials → API key. Enable Maps SDK for Android, Maps SDK for iOS, and Geocoding API. Restrict the key (Android package + SHA-1, iOS bundle ID, or IP for backend-only keys).</li>
            </ul>
          </div>
        </section>
      </div>

      <div class="vp-tab-panel vp-tab-panel--welcome" id="settings_panel_welcome">
        <section class="vp-card" aria-labelledby="welcome-heading">
          <div class="vp-card__pad">
            <h2 id="welcome-heading" class="vp-section-title">Welcome screen (mobile)</h2>
            <p class="vp-field-hint" style="margin:-0.35rem 0 1.25rem;">Delivered in <code class="vp-inline-code">GET /api/v1/config/public</code> as <code class="vp-inline-code">welcome</code>. Upload sets a public URL under <code class="vp-inline-code">/uploads/welcome/</code>. Use <code class="vp-inline-code">{{region}}</code> in subhead — the app replaces it with the rider&apos;s service area label.</p>
            <div class="vp-stack-form">
              <div class="vp-field">
                <label class="vp-label" for="welcomeBgUpload">Upload background image</label>
                <input class="vp-input" id="welcomeBgUpload" name="welcomeBgUpload" type="file" accept="image/jpeg,image/png,image/webp">
                <p class="vp-field-hint"><strong>Recommended:</strong> portrait hero <strong>1080×1920 px</strong> (9∶16) or <strong>1242×2688 px</strong> for sharp full-screen on large phones; <strong>9∶16 to 3∶4</strong> aspect works well. Minimum useful size about <strong>720×1280</strong>. JPEG, PNG, or WebP; <strong>max 3 MB</strong> upload. The server <strong>re-encodes</strong> to WebP (or JPEG if WebP is unavailable) at <strong>high quality</strong> and scales down if the longest side exceeds 2560 px so riders get a smaller file without visible loss. Prior files on disk are not deleted automatically.</p>
              </div>
              <div class="vp-field">
                <label class="vp-toggle" style="display:flex;align-items:flex-start;gap:0.65rem;cursor:pointer;">
                  <input type="checkbox" name="welcome_clear_bg" value="1" style="margin-top:0.2rem;">
                  <span>Clear background image URL (remove hero from app)</span>
                </label>
              </div>
              <div class="vp-field">
                <label class="vp-label" for="welcomeBackgroundImageUrl">Background image URL (optional override)</label>
                <input class="vp-input vp-input--mono" id="welcomeBackgroundImageUrl" name="welcomeBackgroundImageUrl" type="url" maxlength="2048" value="<?= vp_h($settings['welcome']['backgroundImageUrl'] ?? '') ?>" placeholder="https://…/hero.jpg" autocomplete="off">
                <p class="vp-field-hint">Must be a full <code class="vp-inline-code">http://</code> or <code class="vp-inline-code">https://</code> URL. Prefer uploading above so the image is optimized on this server.</p>
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
              <div class="vp-field">
                <label class="vp-label" for="welcomeBrandWordmark">Brand line (small caps)</label>
                <input class="vp-input" id="welcomeBrandWordmark" name="welcomeBrandWordmark" type="text" value="<?= vp_h($settings['welcome']['brandWordmark'] ?? 'VP Ride') ?>" maxlength="48">
              </div>
              <div class="vp-field">
                <label class="vp-label" for="welcomeHeadline">Headline</label>
                <input class="vp-input" id="welcomeHeadline" name="welcomeHeadline" type="text" value="<?= vp_h($settings['welcome']['headline'] ?? '') ?>" maxlength="120">
              </div>
              <div class="vp-field">
                <label class="vp-label" for="welcomeSubhead">Subhead / body</label>
                <textarea class="vp-input vp-textarea vp-textarea--sm" id="welcomeSubhead" name="welcomeSubhead" rows="3" maxlength="600"><?= vp_h($settings['welcome']['subhead'] ?? '') ?></textarea>
              </div>
              <div class="vp-field">
                <label class="vp-label" for="welcomeFeatureLeft">Feature card — left title</label>
                <input class="vp-input" id="welcomeFeatureLeft" name="welcomeFeatureLeft" type="text" value="<?= vp_h($settings['welcome']['featureLeftTitle'] ?? '') ?>" maxlength="64">
              </div>
              <div class="vp-field">
                <label class="vp-label" for="welcomeFeatureRight">Feature card — right title</label>
                <input class="vp-input" id="welcomeFeatureRight" name="welcomeFeatureRight" type="text" value="<?= vp_h($settings['welcome']['featureRightTitle'] ?? '') ?>" maxlength="64">
              </div>
              <div class="vp-field">
                <label class="vp-label" for="welcomeFooterTagline">Footer tagline</label>
                <input class="vp-input" id="welcomeFooterTagline" name="welcomeFooterTagline" type="text" value="<?= vp_h($settings['welcome']['footerTagline'] ?? '') ?>" maxlength="80">
              </div>
              <div class="vp-field">
                <label class="vp-toggle" style="display:flex;align-items:flex-start;gap:0.65rem;cursor:pointer;">
                  <input type="checkbox" name="welcome_show_features" value="1"<?= ! empty($settings['welcome']['showFeatureRow']) ? ' checked' : '' ?> style="margin-top:0.2rem;">
                  <span>Show feature cards row</span>
                </label>
              </div>
              <div class="vp-field">
                <label class="vp-toggle" style="display:flex;align-items:flex-start;gap:0.65rem;cursor:pointer;">
                  <input type="checkbox" name="welcome_show_pager" value="1"<?= ! empty($settings['welcome']['showPagerDots']) ? ' checked' : '' ?> style="margin-top:0.2rem;">
                  <span>Show pager dots (decorative)</span>
                </label>
              </div>
              <div class="vp-field">
                <label class="vp-label" for="welcomeCtaRegister">Button — create account</label>
                <input class="vp-input" id="welcomeCtaRegister" name="welcomeCtaRegister" type="text" value="<?= vp_h($settings['welcome']['ctaRegister'] ?? '') ?>" maxlength="48">
              </div>
              <div class="vp-field">
                <label class="vp-label" for="welcomeCtaEmailLogin">Button — email sign in</label>
                <input class="vp-input" id="welcomeCtaEmailLogin" name="welcomeCtaEmailLogin" type="text" value="<?= vp_h($settings['welcome']['ctaEmailLogin'] ?? '') ?>" maxlength="48">
              </div>
              <div class="vp-field">
                <label class="vp-label" for="welcomeCtaGoogle">Button — Google</label>
                <input class="vp-input" id="welcomeCtaGoogle" name="welcomeCtaGoogle" type="text" value="<?= vp_h($settings['welcome']['ctaGoogle'] ?? '') ?>" maxlength="64">
              </div>
            </div>
          </div>
        </section>
      </div>

      <div class="vp-tab-panel vp-tab-panel--features" id="settings_panel_features">
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
              <label class="vp-toggle">
                <input class="vp-sr-only" type="checkbox" name="feat_sos"<?= ! empty($settings['features']['sosEnabled']) ? ' checked' : '' ?>>
                <span class="vp-toggle__ui"></span>
                <span class="vp-toggle__text"><strong>SOS / panic</strong><span class="vp-toggle__sub">Allow riders and assigned drivers to trigger SOS during an active trip (server-enforced).</span></span>
              </label>
              <label class="vp-toggle">
                <input class="vp-sr-only" type="checkbox" name="feat_promo_code"<?= ! empty($settings['features']['promoCodeEntryEnabled']) ? ' checked' : '' ?>>
                <span class="vp-toggle__ui"></span>
                <span class="vp-toggle__text"><strong>Promo code field at booking</strong><span class="vp-toggle__sub">When off, automatic promos and loyalty rewards still apply; riders cannot type a code.</span></span>
              </label>
            </div>
            <div class="vp-field" style="margin-top:1.25rem;">
              <label class="vp-label" for="maintenanceMessage">Maintenance message</label>
              <textarea class="vp-input vp-textarea vp-textarea--sm" id="maintenanceMessage" name="maintenanceMessage" rows="2" placeholder="Short note for riders"><?= vp_h($settings['features']['maintenanceMessage']) ?></textarea>
            </div>
            <div class="vp-field">
              <label class="vp-label" for="helpCenterUrl">Help center URL</label>
              <input class="vp-input" id="helpCenterUrl" name="helpCenterUrl" type="url" maxlength="512" value="<?= vp_h($settings['features']['helpCenterUrl']) ?>" placeholder="https://…">
              <p class="vp-field-hint">Optional. If set, must be <code class="vp-inline-code">http(s)://</code> — validated on save.</p>
            </div>
          </div>
        </section>
      </div>

      <div class="vp-tab-panel vp-tab-panel--email" id="settings_panel_email">
        <section class="vp-card" aria-labelledby="email-heading">
          <div class="vp-card__pad">
            <h2 id="email-heading" class="vp-section-title">Outbound email</h2>
            <p class="vp-field-hint" style="margin:-0.35rem 0 1.25rem;">Used for rider sign-up notifications, rider welcome mail, and admin password reset links. Delivery uses PHP <code class="vp-inline-code">mail()</code> — your host must allow sending from the address you set below. These values are stored in the database and are <strong>not</strong> exposed to mobile apps.</p>
            <div class="vp-stack-form">
              <div class="vp-field">
                <label class="vp-label" for="emailMailFrom">From (sender)</label>
                <input class="vp-input vp-input--mono" id="emailMailFrom" name="emailMailFrom" type="text" value="<?= vp_h($emailSettings['mailFrom']) ?>" placeholder="VP Ride &lt;noreply@yourdomain.com&gt;" autocomplete="off" maxlength="255">
                <p class="vp-field-hint">If empty, the backend falls back to <code class="vp-inline-code">APP_MAIL_FROM</code> in <code>.env</code>, then a local placeholder (not suitable for production).</p>
              </div>
              <div class="vp-field">
                <label class="vp-toggle" style="display:flex;align-items:flex-start;gap:0.65rem;cursor:pointer;">
                  <input type="checkbox" name="email_staff_notify_rider" value="1"<?= ! empty($emailSettings['staffNotifyOnRiderSignup']) ? ' checked' : '' ?> style="margin-top:0.2rem;">
                  <span><strong>Email staff when a new rider registers</strong> (mobile app sign-up only)</span>
                </label>
              </div>
              <div class="vp-field">
                <label class="vp-label" for="emailStaffNotifyEmails">Staff notification addresses</label>
                <textarea class="vp-input vp-textarea vp-textarea--sm" id="emailStaffNotifyEmails" name="emailStaffNotifyEmails" rows="2" placeholder="ops@company.com, admin@company.com"><?= vp_h($emailSettings['staffNotifyEmails']) ?></textarea>
                <p class="vp-field-hint">Comma-separated. If empty, falls back to <code class="vp-inline-code">RIDER_SIGNUP_NOTIFY_EMAIL</code> in <code>.env</code>. No mail is sent to staff if the resolved list is empty.</p>
              </div>
              <div class="vp-field">
                <label class="vp-label" for="emailSosNotifyEmails">SOS alert addresses (optional)</label>
                <textarea class="vp-input vp-textarea vp-textarea--sm" id="emailSosNotifyEmails" name="emailSosNotifyEmails" rows="2" placeholder="Leave empty to reuse staff addresses above"><?= vp_h($emailSettings['sosNotifyEmails'] ?? '') ?></textarea>
                <p class="vp-field-hint">Comma-separated. When empty, SOS emails go to the same list as staff notifications.</p>
              </div>
              <div class="vp-field">
                <label class="vp-label" for="emailStaffNotifySubject">Staff notification — subject</label>
                <input class="vp-input" id="emailStaffNotifySubject" name="emailStaffNotifySubject" type="text" value="<?= vp_h($emailSettings['staffNotifySubject']) ?>" maxlength="200">
              </div>
              <div class="vp-field">
                <label class="vp-label" for="emailStaffNotifyBody">Staff notification — body (plain text)</label>
                <textarea class="vp-input vp-textarea" id="emailStaffNotifyBody" name="emailStaffNotifyBody" rows="6" maxlength="4000"><?= vp_h($emailSettings['staffNotifyBody']) ?></textarea>
                <p class="vp-field-hint">Placeholders: <code class="vp-inline-code">{email}</code>, <code class="vp-inline-code">{displayName}</code>, <code class="vp-inline-code">{userId}</code>, <code class="vp-inline-code">{greeting}</code> (same as rider welcome).</p>
              </div>
              <div class="vp-field">
                <label class="vp-toggle" style="display:flex;align-items:flex-start;gap:0.65rem;cursor:pointer;">
                  <input type="checkbox" name="email_rider_welcome" value="1"<?= ! empty($emailSettings['riderWelcomeEnabled']) ? ' checked' : '' ?> style="margin-top:0.2rem;">
                  <span><strong>Send welcome email to new riders</strong></span>
                </label>
              </div>
              <div class="vp-field">
                <label class="vp-label" for="emailRiderWelcomeSubject">Rider welcome — subject</label>
                <input class="vp-input" id="emailRiderWelcomeSubject" name="emailRiderWelcomeSubject" type="text" value="<?= vp_h($emailSettings['riderWelcomeSubject']) ?>" maxlength="200">
              </div>
              <div class="vp-field">
                <label class="vp-label" for="emailRiderWelcomeBody">Rider welcome — body (plain text)</label>
                <textarea class="vp-input vp-textarea" id="emailRiderWelcomeBody" name="emailRiderWelcomeBody" rows="6" maxlength="4000"><?= vp_h($emailSettings['riderWelcomeBody']) ?></textarea>
                <p class="vp-field-hint">Use <code class="vp-inline-code">{greeting}</code> for &ldquo;Hi Name,&rdquo; vs &ldquo;Hello,&rdquo; plus <code class="vp-inline-code">{email}</code>, <code class="vp-inline-code">{displayName}</code>, <code class="vp-inline-code">{userId}</code>.</p>
              </div>
              <div class="vp-field" style="margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid rgba(0,0,0,0.08);">
                <h3 class="vp-section-title" style="font-size:1rem;margin:0 0 0.75rem;">New ride request (app + console)</h3>
                <p class="vp-field-hint" style="margin:-0.2rem 0 1rem;">Sends a plain-text message when a ride is created. If the dedicated address list is empty, the same list as <strong>Staff notification addresses</strong> is used (and then <code class="vp-inline-code">RIDER_SIGNUP_NOTIFY_EMAIL</code> from <code>.env</code> for From only — see outbound rules above).</p>
                <label class="vp-toggle" style="display:flex;align-items:flex-start;gap:0.65rem;cursor:pointer;">
                  <input type="checkbox" name="email_notify_new_ride" value="1"<?= ! empty($emailSettings['notifyOnNewRide']) ? ' checked' : '' ?> style="margin-top:0.2rem;">
                  <span><strong>Email staff on every new ride</strong> (in addition to in-app / dispatch flow)</span>
                </label>
                <label class="vp-label" for="emailNewRideNotifyEmails" style="margin-top:1rem;">New-ride addresses (optional)</label>
                <textarea class="vp-input vp-textarea vp-textarea--sm" id="emailNewRideNotifyEmails" name="emailNewRideNotifyEmails" rows="2" placeholder="Empty = reuse staff list"><?= vp_h($emailSettings['newRideNotifyEmails'] ?? '') ?></textarea>
                <label class="vp-label" for="emailNewRideNotifySubject">New ride — subject</label>
                <input class="vp-input" id="emailNewRideNotifySubject" name="emailNewRideNotifySubject" type="text" value="<?= vp_h($emailSettings['newRideNotifySubject'] ?? 'VP Ride: new ride request') ?>" maxlength="200">
                <label class="vp-label" for="emailNewRideNotifyBody">New ride — body (plain text)</label>
                <textarea class="vp-input vp-textarea" id="emailNewRideNotifyBody" name="emailNewRideNotifyBody" rows="6" maxlength="4000"><?= vp_h($emailSettings['newRideNotifyBody'] ?? '') ?></textarea>
                <p class="vp-field-hint">Placeholders: <code class="vp-inline-code">{rideId}</code>, <code class="vp-inline-code">{status}</code>, <code class="vp-inline-code">{riderUserId}</code>, <code class="vp-inline-code">{riderEmail}</code>, <code class="vp-inline-code">{riderName}</code>, <code class="vp-inline-code">{pickupLine}</code>, <code class="vp-inline-code">{dropoffLine}</code>, <code class="vp-inline-code">{consoleUrl}</code>.</p>
              </div>
            </div>
          </div>
        </section>
        <section class="vp-card vp-card--note" aria-labelledby="email-drivers-note">
          <div class="vp-card__pad">
            <h2 id="email-drivers-note" class="vp-section-title">Drivers</h2>
            <p class="vp-field-hint" style="margin:0;">Driver accounts are provisioned from the console, not from the rider app. When driver onboarding emails are added, they will use this same <strong>From</strong> line and delivery path.</p>
          </div>
        </section>
      </div>

      <div class="vp-tab-panel vp-tab-panel--operations" id="settings_panel_operations">
        <section class="vp-card" aria-labelledby="operations-heading">
          <div class="vp-card__pad">
            <h2 id="operations-heading" class="vp-section-title">Cancellations &amp; driver earnings</h2>
            <p class="vp-field-hint" style="margin:-0.35rem 0 1.25rem;">Cancellation fee is shown to riders in the app and recorded on the ride when they cancel (collection is operational — not auto-charged in-app). Driver earnings % sets each driver&apos;s share of the <strong>final fare</strong> on newly completed trips; you can override per driver on the driver edit screen.</p>
            <div class="vp-stack-form">
              <div class="vp-field">
                <label class="vp-label" for="op_rider_cancel_fee">Rider cancellation fee (fixed, same currency as rides)</label>
                <input class="vp-input" id="op_rider_cancel_fee" name="op_rider_cancel_fee" type="number" min="0" step="0.01" value="<?= vp_h((string) $operations['riderCancellationFeeAmount']) ?>" inputmode="decimal">
                <p class="vp-field-hint">Use <code class="vp-inline-code">0</code> for no fee. Amount is stored on the ride row for reporting.</p>
              </div>
              <div class="vp-field">
                <label class="vp-label" for="op_driver_pct_global">Default driver earnings (% of trip fare)</label>
                <input class="vp-input" id="op_driver_pct_global" name="op_driver_pct_global" type="number" min="0" max="100" step="0.01" value="<?= vp_h((string) $operations['driverEarningsPercentGlobal']) ?>" inputmode="decimal">
                <p class="vp-field-hint">Example: <code class="vp-inline-code">80</code> means the driver earns 80% of the fare; the remainder is an approximate platform share in reports.</p>
              </div>
            </div>
          </div>
        </section>
        <section class="vp-card" aria-labelledby="dispatch-heading" style="margin-top:1rem;">
          <div class="vp-card__pad">
            <h2 id="dispatch-heading" class="vp-section-title">Dispatch &amp; matching</h2>
            <p class="vp-field-hint" style="margin:-0.35rem 0 1.25rem;">Tuning for automatic re-offers when drivers decline, rider-requested driver swaps, and when the app should treat a trip as <strong>confirmed</strong> (exposed in <code class="vp-inline-code">GET /api/v1/config/public</code> as <code class="vp-inline-code">dispatch</code>).</p>
            <div class="vp-stack-form">
              <div class="vp-field">
                <label class="vp-label" for="dispatch_max_auto">Max automatic driver re-offers per ride (driver refusals / pool exhaustion)</label>
                <input class="vp-input" id="dispatch_max_auto" name="dispatch_max_auto" type="number" min="1" max="50" step="1" value="<?= (int) ($dispatch['maxAutoDriverAttempts'] ?? 8) ?>" inputmode="numeric">
              </div>
              <div class="vp-field">
                <label class="vp-label" for="dispatch_max_rider_rejects">Max times rider can request a different driver (before accept)</label>
                <input class="vp-input" id="dispatch_max_rider_rejects" name="dispatch_max_rider_rejects" type="number" min="0" max="20" step="1" value="<?= (int) ($dispatch['maxRiderDriverRejects'] ?? 2) ?>" inputmode="numeric">
                <p class="vp-field-hint">Set to <code class="vp-inline-code">0</code> to disable the &ldquo;choose another driver&rdquo; action in the app.</p>
              </div>
              <div class="vp-field">
                <label class="vp-label" for="dispatch_trip_confirmed">“Trip confirmed” in the app means</label>
                <select class="vp-input" id="dispatch_trip_confirmed" name="dispatch_trip_confirmed">
                  <option value="driver_assigned"<?= (($dispatch['tripConfirmedWhen'] ?? '') === 'driver_assigned') ? ' selected' : '' ?>>A driver was assigned (may still be pending their accept)</option>
                  <option value="driver_accepted"<?= (($dispatch['tripConfirmedWhen'] ?? 'driver_accepted') === 'driver_accepted') ? ' selected' : '' ?>>The assigned driver has accepted the trip</option>
                </select>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>

  <div class="vp-form-actions vp-form-actions--page">
    <button type="submit" class="vp-btn vp-btn--primary">Save settings</button>
  </div>
</form>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
