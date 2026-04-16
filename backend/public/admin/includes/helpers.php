<?php

declare(strict_types=1);

function vp_h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

/** Prefix with APP_BASE_PATH when the app lives in a subfolder (shared hosting). */
function vp_url(string $path): string
{
    return \VprideBackend\Config::url($path);
}

/** Scheme + host for the current HTTP request (no path). Empty when unavailable (e.g. CLI). */
function vp_request_origin(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';
    }
    $https = ! empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    $scheme = $https ? 'https' : 'http';

    return $scheme . '://' . $host;
}

/**
 * Full URL to this deployment’s public entry (origin + APP_BASE_PATH), e.g. …/vpride/backend/public.
 * Useful for bookmarks; matches what riders hit when the document root is the `public` folder.
 */
function vp_console_public_url(): string
{
    $origin = vp_request_origin();
    if ($origin === '') {
        return '';
    }

    return rtrim($origin . \VprideBackend\Config::url('/'), '/');
}

/**
 * Google Static Maps URL for pickup / optional drop-off (returns null if API key empty).
 */
function vp_google_static_map_booking_url(
    string $mapsApiKey,
    float $pickupLat,
    float $pickupLng,
    ?float $dropoffLat,
    ?float $dropoffLng,
): ?string {
    $key = trim($mapsApiKey);
    if ($key === '') {
        return null;
    }
    $base = 'https://maps.googleapis.com/maps/api/staticmap';
    $q = [
        'size' => '480x240',
        'scale' => '2',
        'maptype' => 'roadmap',
        'key' => $key,
    ];
    $hasDrop = $dropoffLat !== null && $dropoffLng !== null
        && abs($dropoffLat) <= 90 && abs($dropoffLng) <= 180;
    $pickSeg = $pickupLat . ',' . $pickupLng;
    if ($hasDrop) {
        $dropSeg = $dropoffLat . ',' . $dropoffLng;
        $q['visible'] = $pickSeg . '|' . $dropSeg;
        $markersPickup = 'color:0x2563eb|label:P|' . $pickSeg;
        $markersDrop = 'color:0xea580c|label:D|' . $dropSeg;
        return $base . '?' . http_build_query($q)
            . '&markers=' . rawurlencode($markersPickup)
            . '&markers=' . rawurlencode($markersDrop);
    }
    $q['center'] = $pickSeg;
    $q['zoom'] = '14';
    $markersPickup = 'color:0x2563eb|label:P|' . $pickSeg;

    return $base . '?' . http_build_query($q) . '&markers=' . rawurlencode($markersPickup);
}

/**
 * Normalized pin positions (percent) for a simple CSS/SVG fallback map.
 *
 * @return array{pick: array{x: float, y: float}, drop: ?array{x: float, y: float}}
 */
function vp_booking_map_pin_positions(
    float $pickupLat,
    float $pickupLng,
    ?float $dropoffLat,
    ?float $dropoffLng,
): array {
    if ($dropoffLat === null || $dropoffLng === null) {
        return [
            'pick' => ['x' => 50.0, 'y' => 50.0],
            'drop' => null,
        ];
    }
    $minLat = min($pickupLat, $dropoffLat);
    $maxLat = max($pickupLat, $dropoffLat);
    $minLng = min($pickupLng, $dropoffLng);
    $maxLng = max($pickupLng, $dropoffLng);
    $latSpan = max(0.00015, $maxLat - $minLat);
    $lngSpan = max(0.00015, $maxLng - $minLng);
    $pad = 18;
    $toX = static function (float $lng) use ($minLng, $lngSpan, $pad): float {
        return $pad + (100 - 2 * $pad) * ($lng - $minLng) / $lngSpan;
    };
    $toY = static function (float $lat) use ($minLat, $latSpan, $pad): float {
        return $pad + (100 - 2 * $pad) * (1 - ($lat - $minLat) / $latSpan);
    };

    return [
        'pick' => ['x' => $toX($pickupLng), 'y' => $toY($pickupLat)],
        'drop' => ['x' => $toX($dropoffLng), 'y' => $toY($dropoffLat)],
    ];
}

/** CSS modifier for ride status dots (dashboard / tables). */
function vp_ride_status_dot_class(string $status): string
{
    $s = strtolower(trim($status));
    if ($s === '') {
        return 'vp-status-dot--neutral';
    }
    if (str_contains($s, 'complete')) {
        return 'vp-status-dot--success';
    }
    if (str_contains($s, 'cancel')) {
        return 'vp-status-dot--danger';
    }
    if (str_contains($s, 'pending') || str_contains($s, 'request') || str_contains($s, 'progress') || str_contains($s, 'active')) {
        return 'vp-status-dot--warn';
    }

    return 'vp-status-dot--neutral';
}

/** Human-readable relative time for activity feeds. */
function vp_relative_time(string $timestamp): string
{
    $t = strtotime($timestamp);
    if ($t === false) {
        return $timestamp;
    }
    $diff = time() - $t;
    if ($diff < 45) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $m = (int) floor($diff / 60);

        return $m . ' min ago';
    }
    if ($diff < 86400) {
        $h = (int) floor($diff / 3600);

        return $h . ' hr ago';
    }
    if ($diff < 604800) {
        $d = (int) floor($diff / 86400);

        return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
    }

    return date('j M Y, H:i', $t);
}

/**
 * @param array<string, mixed> $ride
 */
function vp_ride_route_summary(array $ride): string
{
    $pick = trim((string) ($ride['pickup_address'] ?? ''));
    if ($pick === '') {
        $pick = 'Pickup point';
    }
    if (function_exists('mb_strlen') && mb_strlen($pick) > 32) {
        $pick = mb_substr($pick, 0, 29) . '…';
    } elseif (strlen($pick) > 32) {
        $pick = substr($pick, 0, 29) . '…';
    }
    $drop = trim((string) ($ride['dropoff_address'] ?? ''));
    if ($drop === '') {
        $drop = 'Drop-off TBD';
    }
    if (function_exists('mb_strlen') && mb_strlen($drop) > 32) {
        $drop = mb_substr($drop, 0, 29) . '…';
    } elseif (strlen($drop) > 32) {
        $drop = substr($drop, 0, 29) . '…';
    }

    return $pick . ' → ' . $drop;
}

/** CSS modifier for activity list status badges (uppercase chips). */
function vp_ride_activity_badge_mod(string $status): string
{
    $s = strtolower(trim($status));
    if (str_contains($s, 'complete')) {
        return 'vp-activity-badge--done';
    }
    if (str_contains($s, 'cancel')) {
        return 'vp-activity-badge--cancel';
    }
    if (str_contains($s, 'progress') || $s === 'accepted' || $s === 'in_progress') {
        return 'vp-activity-badge--active';
    }

    return 'vp-activity-badge--pending';
}

function vp_nav_icon_bell(): string
{
    return '<svg class="vp-icon-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
}

/** Two-letter avatar label from admin email (e.g. "jd" from "jane.doe@x.com"). */
function vp_admin_initials(string $email): string
{
    $local = strstr($email, '@', true);
    if ($local === false || $local === '') {
        $local = $email;
    }
    $parts = preg_split('/[\s._-]+/', $local, -1, PREG_SPLIT_NO_EMPTY);
    if (is_array($parts) && count($parts) >= 2) {
        $a = function_exists('mb_substr') ? mb_substr($parts[0], 0, 1) : substr($parts[0], 0, 1);
        $b = function_exists('mb_substr') ? mb_substr($parts[1], 0, 1) : substr($parts[1], 0, 1);
        $pair = $a . $b;
    } else {
        $pair = function_exists('mb_substr')
            ? mb_substr($local, 0, 2)
            : substr($local, 0, 2);
    }

    return function_exists('mb_strtoupper') ? mb_strtoupper($pair) : strtoupper($pair);
}

function vp_nav_icon_grid(): string
{
    return '<svg class="vp-icon-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>';
}

function vp_nav_icon_plus(): string
{
    return '<svg class="vp-icon-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>';
}

function vp_nav_icon_menu(): string
{
    return '<svg class="vp-icon-svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>';
}

function vp_nav_icon_chevron(): string
{
    return '<svg class="vp-icon-svg vp-icon-svg--chev" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';
}

function vp_nav_icon_overview(): string
{
    return '<svg class="vp-icon-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19V5M4 19h16M4 19l3-9h10l3 9"/><path d="M9 10V7h6v3"/></svg>';
}

function vp_nav_icon_regions(): string
{
    return '<svg class="vp-icon-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>';
}

function vp_nav_icon_rides(): string
{
    return '<svg class="vp-icon-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.5-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/></svg>';
}

function vp_nav_icon_riders(): string
{
    return '<svg class="vp-icon-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
}

function vp_nav_icon_team(): string
{
    return '<svg class="vp-icon-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
}

function vp_nav_icon_settings(): string
{
    return '<svg class="vp-icon-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>';
}

function vp_nav_icon_reports(): string
{
    return '<svg class="vp-icon-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 22h16a2 2 0 0 0 2-2V6M4 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h10l6 6v12a2 2 0 0 1-2 2"/><path d="M14 2v6h6"/><path d="M8 13h2"/><path d="M8 17h8"/></svg>';
}

function vp_nav_icon_rbac(): string
{
    return '<svg class="vp-icon-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>';
}

function vp_nav_icon_schedule(): string
{
    return '<svg class="vp-icon-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg>';
}

function vp_nav_icon_fleet(): string
{
    return '<svg class="vp-icon-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/></svg>';
}

function vp_nav_icon_users_hub(): string
{
    return '<svg class="vp-icon-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
}

function vp_nav_icon_help(): string
{
    return '<svg class="vp-icon-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>';
}

function vp_nav_icon_phone(): string
{
    return '<svg class="vp-icon-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>';
}

function vp_kpi_icon_riders(): string
{
    return '<svg class="vp-kpi-card__icon-svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>';
}

function vp_kpi_icon_rides(): string
{
    return '<svg class="vp-kpi-card__icon-svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 17h2a1 1 0 0 0 1-1v-2.5a2 2 0 0 0-.8-1.6l-1.5-1.2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/><path d="M14 17H9M5 17H3v-5l2-4h8l4 4v5"/></svg>';
}

function vp_kpi_icon_globe(): string
{
    return '<svg class="vp-kpi-card__icon-svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 4 10 15 15 0 0 1-4 10"/></svg>';
}

function vp_kpi_icon_layers(): string
{
    return '<svg class="vp-kpi-card__icon-svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>';
}

/**
 * @param 'rides'|'riders' $active
 */
function vp_reports_tabs(string $active): void
{
    $ridesClass = $active === 'rides' ? ' vp-tabs__link--active' : '';
    $ridersClass = $active === 'riders' ? ' vp-tabs__link--active' : '';
    echo '<nav class="vp-tabs" aria-label="Report type">';
    echo '<a class="vp-tabs__link' . $ridesClass . '" href="' . vp_h(vp_url('/admin/reports/rides')) . '">Rides</a>';
    echo '<a class="vp-tabs__link' . $ridersClass . '" href="' . vp_h(vp_url('/admin/reports/riders')) . '">Riders</a>';
    echo '</nav>';
}

/**
 * @param list<string> $fields
 */
function vp_csv_line(array $fields): string
{
    $out = [];
    foreach ($fields as $f) {
        $s = (string) $f;
        if (str_contains($s, '"') || str_contains($s, ',') || str_contains($s, "\n") || str_contains($s, "\r")) {
            $s = '"' . str_replace('"', '""', $s) . '"';
        }
        $out[] = $s;
    }

    return implode(',', $out) . "\r\n";
}

require_once __DIR__ . '/ui_components.php';
