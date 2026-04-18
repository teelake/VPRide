<?php

declare(strict_types=1);

/**
 * Compact icon actions for data tables (edit, delete, publish, etc.).
 * Use inside a cell with class vp-table__actions-col wrapped in vp-action-icons.
 */

function vp_icon_svg_edit(): string
{
    return '<svg class="vp-icon-action__svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
}

function vp_icon_svg_trash(): string
{
    return '<svg class="vp-icon-action__svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>';
}

/** Publish / go live / activate (upload to live) */
function vp_icon_svg_publish(): string
{
    return '<svg class="vp-icon-action__svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>';
}

/** Optional: deactivate / unpublish — use when you add row-level deactivate */
function vp_icon_svg_deactivate(): string
{
    return '<svg class="vp-icon-action__svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>';
}

function vp_icon_svg_dispatch(): string
{
    return '<svg class="vp-icon-action__svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>';
}

function vp_icon_svg_mark_paid(): string
{
    return '<svg class="vp-icon-action__svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
}

function vp_action_icons_open(string $ariaLabel = 'Row actions'): void
{
    echo '<div class="vp-action-icons" role="group" aria-label="' . vp_h($ariaLabel) . '">';
}

function vp_action_icons_close(): void
{
    echo '</div>';
}

function vp_action_edit(string $href, string $label = 'Edit'): void
{
    echo '<a class="vp-icon-action vp-icon-action--edit" href="' . vp_h($href) . '" title="' . vp_h($label) . '" aria-label="' . vp_h($label) . '">';
    echo vp_icon_svg_edit();
    echo '</a>';
}

/**
 * @param array<string, string|int> $hidden Field name => value (excluding _csrf)
 */
function vp_action_delete_form(
    string $postAction,
    string $csrf,
    array $hidden,
    string $confirmMessage,
    string $label = 'Delete',
): void {
    echo '<form method="post" action="' . vp_h($postAction) . '" class="vp-icon-action-form" onsubmit="return confirm(' . vp_confirm_attr($confirmMessage) . ');">';
    echo '<input type="hidden" name="_csrf" value="' . vp_h($csrf) . '">';
    foreach ($hidden as $name => $val) {
        echo '<input type="hidden" name="' . vp_h((string) $name) . '" value="' . vp_h((string) $val) . '">';
    }
    echo '<button type="submit" class="vp-icon-action vp-icon-action--danger" title="' . vp_h($label) . '" aria-label="' . vp_h($label) . '">';
    echo vp_icon_svg_trash();
    echo '</button></form>';
}

/**
 * @param array<string, string|int> $hidden
 */
function vp_action_publish_form(
    string $postAction,
    string $csrf,
    array $hidden,
    string $confirmMessage,
    string $label = 'Go live',
): void {
    echo '<form method="post" action="' . vp_h($postAction) . '" class="vp-icon-action-form" onsubmit="return confirm(' . vp_confirm_attr($confirmMessage) . ');">';
    echo '<input type="hidden" name="_csrf" value="' . vp_h($csrf) . '">';
    foreach ($hidden as $name => $val) {
        echo '<input type="hidden" name="' . vp_h((string) $name) . '" value="' . vp_h((string) $val) . '">';
    }
    echo '<button type="submit" class="vp-icon-action vp-icon-action--success" title="' . vp_h($label) . '" aria-label="' . vp_h($label) . '">';
    echo vp_icon_svg_publish();
    echo '</button></form>';
}

function vp_action_link_dispatch(string $href, string $label = 'Dispatch'): void
{
    echo '<a class="vp-icon-action vp-icon-action--dispatch" href="' . vp_h($href) . '" title="' . vp_h($label) . '" aria-label="' . vp_h($label) . '">';
    echo vp_icon_svg_dispatch();
    echo '</a>';
}

/**
 * @param array<string, string|int> $hidden
 */
function vp_action_mark_paid_form(
    string $postAction,
    string $csrf,
    array $hidden,
    string $label = 'Mark paid',
): void {
    echo '<form method="post" action="' . vp_h($postAction) . '" class="vp-icon-action-form">';
    echo '<input type="hidden" name="_csrf" value="' . vp_h($csrf) . '">';
    foreach ($hidden as $name => $val) {
        echo '<input type="hidden" name="' . vp_h((string) $name) . '" value="' . vp_h((string) $val) . '">';
    }
    echo '<button type="submit" class="vp-icon-action vp-icon-action--paid" title="' . vp_h($label) . '" aria-label="' . vp_h($label) . '">';
    echo vp_icon_svg_mark_paid();
    echo '</button></form>';
}

function vp_icon_svg_acknowledge(): string
{
    return '<svg class="vp-icon-action__svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
}

/**
 * @param array<string, string|int> $hidden
 */
function vp_action_post_icon_form(
    string $postAction,
    string $csrf,
    array $hidden,
    string $svgInner,
    string $label,
    string $modifierClass = 'vp-icon-action--success',
): void {
    echo '<form method="post" action="' . vp_h($postAction) . '" class="vp-icon-action-form">';
    echo '<input type="hidden" name="_csrf" value="' . vp_h($csrf) . '">';
    foreach ($hidden as $name => $val) {
        echo '<input type="hidden" name="' . vp_h((string) $name) . '" value="' . vp_h((string) $val) . '">';
    }
    echo '<button type="submit" class="vp-icon-action ' . vp_h($modifierClass) . '" title="' . vp_h($label) . '" aria-label="' . vp_h($label) . '">';
    echo $svgInner;
    echo '</button></form>';
}
