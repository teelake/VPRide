<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 2);
require_once $backendRoot . '/src/Config.php';
require_once $backendRoot . '/src/Database.php';
require_once $backendRoot . '/src/Auth.php';
require_once $backendRoot . '/src/PromotionRepository.php';
require_once $backendRoot . '/src/PlatformPromoSettingsRepository.php';
require_once $backendRoot . '/src/SchemaInspector.php';

use VprideBackend\Auth;
use VprideBackend\Config;
use VprideBackend\Database;
use VprideBackend\PlatformPromoSettingsRepository;
use VprideBackend\PromotionRepository;
use VprideBackend\SchemaInspector;

Config::load($backendRoot . '/.env');
Auth::startSession();
Auth::requireLogin();
Auth::requirePermission('promotions.manage');

$admin = Auth::currentAdmin();
$pdo = Database::pdo();
$csrf = Auth::csrfToken();
$message = '';
$error = '';
$promoRepo = new PromotionRepository($pdo);
$platRepo = new PlatformPromoSettingsRepository($pdo);

$promoTab = 'pricing';
if (isset($_GET['tab']) && in_array($_GET['tab'], ['pricing', 'catalog', 'editor'], true)) {
    $promoTab = $_GET['tab'];
}
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (! Auth::validateCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session.';
    } elseif (isset($_POST['save_platform'])) {
        try {
            $loyPid = trim((string) ($_POST['loyalty_reward_promotion_id'] ?? ''));
            $platRepo->save([
                'currency_code' => (string) ($_POST['currency_code'] ?? 'NGN'),
                'decimal_places' => (int) ($_POST['decimal_places'] ?? 2),
                'default_ride_estimate' => (float) ($_POST['default_ride_estimate'] ?? 1500),
                'pricing_base_fare' => (float) ($_POST['pricing_base_fare'] ?? 500),
                'pricing_per_km' => (float) ($_POST['pricing_per_km'] ?? 350),
                'pricing_minimum_fare' => (float) ($_POST['pricing_minimum_fare'] ?? 0),
                'advance_booking_max_days' => (int) ($_POST['advance_booking_max_days'] ?? 30),
                'promo_timezone' => (string) ($_POST['promo_timezone'] ?? 'Africa/Lagos'),
                'loyalty_enabled' => isset($_POST['loyalty_enabled']),
                'loyalty_trips_per_reward' => (int) ($_POST['loyalty_trips_per_reward'] ?? 5),
                'loyalty_reward_promotion_id' => $loyPid === '' ? null : (int) $loyPid,
            ], $admin[0]);
            $message = 'Pricing & loyalty settings saved.';
        } catch (Throwable $e) {
            $error = 'Platform settings: ' . $e->getMessage();
        }
    } elseif (isset($_POST['save_promotion'])) {
        $scheduleRaw = trim((string) ($_POST['schedule_json'] ?? ''));
        $scheduleJson = null;
        if ($scheduleRaw !== '') {
            try {
                json_decode($scheduleRaw, true, 512, JSON_THROW_ON_ERROR);
                $scheduleJson = $scheduleRaw;
            } catch (Throwable) {
                $error = 'Schedule must be valid JSON (or leave empty). Example: [{"dow":5,"start":"12:00","end":"13:00"}] for Friday lunch (dow 1=Mon … 7=Sun).';
            }
        }
        if ($error === '') {
            $normDt = static function (string $v): ?string {
                $v = trim($v);
                if ($v === '') {
                    return null;
                }
                $v = str_replace('T', ' ', $v);
                if (strlen($v) === 16) {
                    $v .= ':00';
                }

                return $v;
            };
            $sStart = trim((string) ($_POST['starts_at'] ?? ''));
            $sEnd = trim((string) ($_POST['ends_at'] ?? ''));
            $row = [
                'name' => trim((string) ($_POST['promo_name'] ?? '')),
                'is_active' => isset($_POST['promo_active']),
                'starts_at' => $sStart === '' ? null : $normDt($sStart),
                'ends_at' => $sEnd === '' ? null : $normDt($sEnd),
                'kind' => (string) ($_POST['promo_kind'] ?? 'automatic'),
                'coupon_code' => trim((string) ($_POST['coupon_code'] ?? '')) ?: null,
                'discount_kind' => (string) ($_POST['discount_kind'] ?? 'percent'),
                'discount_value' => (float) ($_POST['discount_value'] ?? 0),
                'max_discount_amount' => trim((string) ($_POST['max_discount_amount'] ?? '')) ?: null,
                'new_users_only' => isset($_POST['new_users_only']),
                'schedule_json' => $scheduleJson,
                'max_uses_per_rider' => trim((string) ($_POST['max_uses_per_rider'] ?? '')) ?: null,
                'min_fare_amount' => trim((string) ($_POST['min_fare_amount'] ?? '')) ?: null,
                'priority' => (int) ($_POST['priority'] ?? 0),
            ];
            if ($row['name'] === '') {
                $error = 'Promotion name is required.';
            } elseif (! in_array($row['kind'], ['automatic', 'coupon'], true)) {
                $error = 'Invalid kind.';
            } elseif ($row['kind'] === 'coupon' && ($row['coupon_code'] === null || $row['coupon_code'] === '')) {
                $error = 'Coupon promotions need a code.';
            } elseif ($row['kind'] === 'automatic') {
                $row['coupon_code'] = null;
            }
        }
        if ($error === '' && PromotionRepository::tableExists($pdo)) {
            try {
                $eid = (int) ($_POST['promotion_id'] ?? 0);
                if ($eid > 0) {
                    $promoRepo->update($eid, $row);
                    $message = 'Promotion updated.';
                } else {
                    $promoRepo->insert($row);
                    $message = 'Promotion created.';
                }
            } catch (Throwable $e) {
                $error = 'Could not save promotion: ' . $e->getMessage();
            }
        } elseif ($error === '') {
            $error = 'Promotions table missing — run migration_sos_promos_loyalty.sql.';
        }
    }
    $postedTab = (string) ($_POST['promotions_ui_tab'] ?? '');
    if (in_array($postedTab, ['pricing', 'catalog', 'editor'], true)) {
        $promoTab = $postedTab;
    }
    if ($message !== '' && (str_contains($message, 'Promotion created.') || str_contains($message, 'Promotion updated.'))) {
        $promoTab = 'catalog';
    }
}

if ($editId > 0 && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $promoTab = 'editor';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_POST['save_promotion'])
    && $error !== ''
    && (int) ($_POST['promotion_id'] ?? 0) > 0) {
    $editId = (int) $_POST['promotion_id'];
    $promoTab = 'editor';
}

$editRow = $editId > 0 && PromotionRepository::tableExists($pdo) ? $promoRepo->findById($editId) : null;
$platform = $platRepo->getSettings();
$list = PromotionRepository::tableExists($pdo) ? $promoRepo->listAll() : [];

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Promotions · VP Ride Console';
$bodyClass = 'vp-body vp-body--app';
$vpNavActive = 'promotions';
$vpTopbarTitle = 'Promotions';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/app_shell_start.php';
?>

<?php if (! PromotionRepository::tableExists($pdo) || ! PlatformPromoSettingsRepository::tableExists($pdo)) { ?>
  <?php vp_schema_single_table_alert($pdo, 'promotions', 'migration_sos_promos_loyalty.sql', 'Promotions'); ?>
<?php } ?>

<header class="vp-page-hero">
  <h1 class="vp-page-title">Promotions &amp; pricing</h1>
  <p class="vp-page-desc">Configure default fare estimates, automatic schedules (happy hour), coupon codes, and loyalty rewards. Rider discounts are funded by the platform (driver payout unchanged).</p>
</header>

<?php if ($message !== '') { ?>
  <p class="vp-banner vp-banner--info" role="status"><?= vp_h($message) ?></p>
<?php } ?>
<?php if ($error !== '') { ?>
  <p class="vp-banner vp-banner--danger" role="alert"><?= vp_h($error) ?></p>
<?php } ?>

<div class="vp-settings-tabs" role="region" aria-label="Promotions sections">
  <input type="radio" name="promotions_tab_ui" id="promotions_tab_pricing" class="vp-sr-only" value="pricing"<?= $promoTab === 'pricing' ? ' checked' : '' ?>>
  <input type="radio" name="promotions_tab_ui" id="promotions_tab_catalog" class="vp-sr-only" value="catalog"<?= $promoTab === 'catalog' ? ' checked' : '' ?>>
  <input type="radio" name="promotions_tab_ui" id="promotions_tab_editor" class="vp-sr-only" value="editor"<?= $promoTab === 'editor' ? ' checked' : '' ?>>

  <div class="vp-tablist" aria-label="Promotions tabs">
    <label class="vp-tab" for="promotions_tab_pricing">Pricing &amp; loyalty</label>
    <label class="vp-tab" for="promotions_tab_catalog">All promotions</label>
    <label class="vp-tab" for="promotions_tab_editor">New / edit</label>
  </div>

  <div class="vp-tab-panels">
    <div class="vp-tab-panel vp-tab-panel--promo-pricing" id="promotions_panel_pricing">
<section class="vp-card" aria-labelledby="plat-heading">
  <div class="vp-card__pad">
    <h2 id="plat-heading" class="vp-section-title">Pricing &amp; loyalty defaults</h2>
    <form method="post" class="vp-stack-form" style="max-width:40rem;" action="<?= vp_url('/admin/promotions') ?>">
      <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
      <input type="hidden" name="promotions_ui_tab" value="pricing">
      <input type="hidden" name="save_platform" value="1">
      <div class="vp-field">
        <label class="vp-label" for="currency_code">Currency (ISO 4217)</label>
        <input class="vp-input" id="currency_code" name="currency_code" maxlength="3" value="<?= vp_h($platform['currency_code']) ?>">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="decimal_places">Decimal places</label>
        <input class="vp-input" id="decimal_places" name="decimal_places" type="number" min="0" max="4" value="<?= (int) $platform['decimal_places'] ?>">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="default_ride_estimate">Default ride estimate (flat fare when per-km is 0)</label>
        <input class="vp-input" id="default_ride_estimate" name="default_ride_estimate" type="number" step="0.01" value="<?= vp_h((string) $platform['default_ride_estimate']) ?>">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="pricing_base_fare">Distance pricing — base fare</label>
        <input class="vp-input" id="pricing_base_fare" name="pricing_base_fare" type="number" step="0.01" value="<?= vp_h((string) ($platform['pricing_base_fare'] ?? 500)) ?>">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="pricing_per_km">Distance pricing — amount per km (0 = always use flat estimate)</label>
        <input class="vp-input" id="pricing_per_km" name="pricing_per_km" type="number" step="0.0001" value="<?= vp_h((string) ($platform['pricing_per_km'] ?? 350)) ?>">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="pricing_minimum_fare">Distance pricing — minimum total fare</label>
        <input class="vp-input" id="pricing_minimum_fare" name="pricing_minimum_fare" type="number" step="0.01" value="<?= vp_h((string) ($platform['pricing_minimum_fare'] ?? 0)) ?>">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="advance_booking_max_days">Advance booking — max days ahead (rider app)</label>
        <input class="vp-input" id="advance_booking_max_days" name="advance_booking_max_days" type="number" min="1" max="365" value="<?= (int) ($platform['advance_booking_max_days'] ?? 30) ?>">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="promo_timezone">Promo timezone (IANA)</label>
        <input class="vp-input" id="promo_timezone" name="promo_timezone" value="<?= vp_h($platform['promo_timezone']) ?>" placeholder="Africa/Lagos">
      </div>
      <label class="vp-toggle" style="display:flex;align-items:flex-start;gap:0.65rem;cursor:pointer;">
        <input type="checkbox" name="loyalty_enabled" value="1"<?= ! empty($platform['loyalty_enabled']) ? ' checked' : '' ?> style="margin-top:0.2rem;">
        <span><strong>Loyalty rewards enabled</strong> — after each <em>paid</em> completed trip (see Bookings → Mark paid), progress toward a reward grant.</span>
      </label>
      <div class="vp-field">
        <label class="vp-label" for="loyalty_trips_per_reward">Paid trips per reward</label>
        <input class="vp-input" id="loyalty_trips_per_reward" name="loyalty_trips_per_reward" type="number" min="1" value="<?= (int) $platform['loyalty_trips_per_reward'] ?>">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="loyalty_reward_promotion_id">Loyalty reward promotion ID</label>
        <input class="vp-input" id="loyalty_reward_promotion_id" name="loyalty_reward_promotion_id" type="number" min="0"
          value="<?= $platform['loyalty_reward_promotion_id'] !== null ? (int) $platform['loyalty_reward_promotion_id'] : '' ?>"
          placeholder="Create a 100% off (capped) coupon promo first, then enter its ID">
        <p class="vp-field-hint">Create a promotion (e.g. 100% percent discount with max discount cap) dedicated to loyalty; riders receive a grant after every N paid trips.</p>
      </div>
      <button type="submit" class="vp-btn vp-btn--primary">Save platform settings</button>
    </form>
  </div>
</section>
    </div>

    <div class="vp-tab-panel vp-tab-panel--promo-catalog" id="promotions_panel_catalog">
<section class="vp-card" aria-labelledby="promo-list-heading">
  <div class="vp-card__pad">
    <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:0.75rem;margin-bottom:1rem;">
      <h2 id="promo-list-heading" class="vp-section-title" style="margin:0;">All promotions</h2>
      <a class="vp-btn vp-btn--primary vp-btn--sm" href="<?= vp_url('/admin/promotions?tab=editor') ?>">New promotion</a>
    </div>
    <?php if ($list === []) { ?>
      <p class="vp-field-hint">None yet. Use <strong>New / edit</strong> to create one.</p>
    <?php } else { ?>
      <div class="vp-table-wrap">
        <table class="vp-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Kind</th>
              <th>Code</th>
              <th>Discount</th>
              <th>Active</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($list as $p) { ?>
              <tr>
                <td class="vp-table__id"><?= (int) $p['id'] ?></td>
                <td><?= vp_h((string) $p['name']) ?></td>
                <td><?= vp_h((string) $p['kind']) ?></td>
                <td class="vp-table__muted"><?= vp_h((string) ($p['coupon_code'] ?? '—')) ?></td>
                <td><?= vp_h((string) $p['discount_kind'] . ' ' . (string) $p['discount_value']) ?></td>
                <td><?= ! empty($p['is_active']) ? 'Yes' : 'No' ?></td>
                <td><a href="<?= vp_url('/admin/promotions?edit=' . (int) $p['id']) ?>">Edit</a></td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    <?php } ?>
  </div>
</section>
    </div>

    <div class="vp-tab-panel vp-tab-panel--promo-editor" id="promotions_panel_editor">
<section class="vp-card" aria-labelledby="promo-form-heading">
  <div class="vp-card__pad">
    <h2 id="promo-form-heading" class="vp-section-title"><?= $editRow !== null ? 'Edit promotion' : 'New promotion' ?></h2>
    <p class="vp-field-hint" style="margin:-0.35rem 0 1rem;">Automatic promos can use a JSON schedule (happy hour). Coupon promos require a code riders enter when booking (if enabled in Settings).</p>
    <form method="post" class="vp-stack-form" style="max-width:44rem;" action="<?= vp_url('/admin/promotions') ?>">
      <input type="hidden" name="_csrf" value="<?= vp_h($csrf) ?>">
      <input type="hidden" name="promotions_ui_tab" value="editor">
      <input type="hidden" name="save_promotion" value="1">
      <input type="hidden" name="promotion_id" value="<?= $editRow !== null ? (int) $editRow['id'] : 0 ?>">
      <div class="vp-field">
        <label class="vp-label" for="promo_name">Name</label>
        <input class="vp-input" id="promo_name" name="promo_name" required maxlength="160" value="<?= $editRow !== null ? vp_h((string) $editRow['name']) : '' ?>">
      </div>
      <label class="vp-toggle" style="display:flex;align-items:flex-start;gap:0.65rem;cursor:pointer;">
        <input type="checkbox" name="promo_active" value="1"<?= $editRow === null || ! empty($editRow['is_active']) ? ' checked' : '' ?> style="margin-top:0.2rem;">
        <span>Active</span>
      </label>
      <div class="vp-field">
        <label class="vp-label" for="promo_kind">Kind</label>
        <select class="vp-input" id="promo_kind" name="promo_kind">
          <option value="automatic"<?= $editRow !== null && ($editRow['kind'] ?? '') === 'coupon' ? '' : ' selected' ?>>Automatic (schedule / always on)</option>
          <option value="coupon"<?= $editRow !== null && ($editRow['kind'] ?? '') === 'coupon' ? ' selected' : '' ?>>Coupon code</option>
        </select>
      </div>
      <div class="vp-field">
        <label class="vp-label" for="coupon_code">Coupon code (coupon kind only)</label>
        <input class="vp-input vp-input--mono" id="coupon_code" name="coupon_code" maxlength="32" value="<?= $editRow !== null && $editRow['coupon_code'] !== null ? vp_h((string) $editRow['coupon_code']) : '' ?>">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="discount_kind">Discount</label>
        <select class="vp-input" id="discount_kind" name="discount_kind">
          <option value="percent"<?= $editRow !== null && ($editRow['discount_kind'] ?? '') === 'fixed_amount' ? '' : ' selected' ?>>Percent off</option>
          <option value="fixed_amount"<?= $editRow !== null && ($editRow['discount_kind'] ?? '') === 'fixed_amount' ? ' selected' : '' ?>>Fixed amount off</option>
        </select>
      </div>
      <div class="vp-field">
        <label class="vp-label" for="discount_value">Value (% or currency units)</label>
        <input class="vp-input" id="discount_value" name="discount_value" type="number" step="0.01" value="<?= $editRow !== null ? vp_h((string) $editRow['discount_value']) : '10' ?>">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="max_discount_amount">Max discount cap (optional, for %)</label>
        <input class="vp-input" id="max_discount_amount" name="max_discount_amount" type="number" step="0.01" value="<?= $editRow !== null && $editRow['max_discount_amount'] !== null ? vp_h((string) $editRow['max_discount_amount']) : '' ?>">
      </div>
      <label class="vp-toggle" style="display:flex;align-items:flex-start;gap:0.65rem;cursor:pointer;">
        <input type="checkbox" name="new_users_only" value="1"<?= $editRow !== null && ! empty($editRow['new_users_only']) ? ' checked' : '' ?> style="margin-top:0.2rem;">
        <span>New riders only (zero paid trips in loyalty counter)</span>
      </label>
      <div class="vp-field">
        <label class="vp-label" for="schedule_json">Schedule JSON (automatic only; empty = all day)</label>
        <textarea class="vp-input vp-textarea vp-textarea--sm vp-input--mono" id="schedule_json" name="schedule_json" rows="3" placeholder='[{"dow":5,"start":"12:00","end":"13:00"}]'><?php
          if ($editRow !== null && ! empty($editRow['schedule_json'])) {
              $sj = $editRow['schedule_json'];
              if (is_string($sj)) {
                  echo vp_h($sj);
              } else {
                  echo vp_h((string) json_encode($sj, JSON_UNESCAPED_UNICODE));
              }
          }
        ?></textarea>
      </div>
      <div class="vp-field">
        <label class="vp-label" for="max_uses_per_rider">Max uses per rider (optional)</label>
        <input class="vp-input" id="max_uses_per_rider" name="max_uses_per_rider" type="number" min="0" value="<?= $editRow !== null && $editRow['max_uses_per_rider'] !== null ? (int) $editRow['max_uses_per_rider'] : '' ?>">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="min_fare_amount">Min estimated fare to apply (optional)</label>
        <input class="vp-input" id="min_fare_amount" name="min_fare_amount" type="number" step="0.01" value="<?= $editRow !== null && $editRow['min_fare_amount'] !== null ? vp_h((string) $editRow['min_fare_amount']) : '' ?>">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="priority">Priority (higher wins tie in automatic)</label>
        <input class="vp-input" id="priority" name="priority" type="number" value="<?= $editRow !== null ? (int) ($editRow['priority'] ?? 0) : 0 ?>">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="starts_at">Starts at (optional, UTC stored as entered local naive)</label>
        <input class="vp-input" id="starts_at" name="starts_at" type="datetime-local" value="<?= $editRow !== null && $editRow['starts_at'] !== null ? vp_h(substr((string) $editRow['starts_at'], 0, 16)) : '' ?>">
      </div>
      <div class="vp-field">
        <label class="vp-label" for="ends_at">Ends at (optional)</label>
        <input class="vp-input" id="ends_at" name="ends_at" type="datetime-local" value="<?= $editRow !== null && $editRow['ends_at'] !== null ? vp_h(substr((string) $editRow['ends_at'], 0, 16)) : '' ?>">
      </div>
      <button type="submit" class="vp-btn vp-btn--primary"><?= $editRow !== null ? 'Update promotion' : 'Create promotion' ?></button>
      <?php if ($editRow !== null) { ?>
        <a class="vp-btn vp-btn--ghost" href="<?= vp_url('/admin/promotions?tab=catalog') ?>">Cancel edit</a>
      <?php } ?>
    </form>
  </div>
</section>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/app_shell_end.php'; ?>
