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
