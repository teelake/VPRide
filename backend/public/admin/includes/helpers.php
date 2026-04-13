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
