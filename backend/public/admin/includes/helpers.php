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
