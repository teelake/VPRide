<?php

declare(strict_types=1);

use VprideBackend\SchemaInspector;

require_once dirname(__DIR__, 3) . '/src/SchemaInspector.php';

/**
 * @return array{level: 'active'|'attention'|'degraded', label: string, issues: list<string>}
 */
function vp_system_health(\PDO $pdo, string $liveRegionLabel): array
{
    $issues = [];
    $level = 'active';
    $rides = SchemaInspector::tableExists($pdo, 'rides');
    $riders = SchemaInspector::tableExists($pdo, 'rider_users');
    if (! $rides) {
        $issues[] = 'Rides table not installed';
        $level = 'degraded';
    }
    if (! $riders) {
        $issues[] = 'Rider accounts table not installed';
        $level = 'degraded';
    }
    $regionMissing = $liveRegionLabel === '—' || $liveRegionLabel === '';
    if ($rides && $riders && $regionMissing) {
        $issues[] = 'No live routing region configured';
        if ($level === 'active') {
            $level = 'attention';
        }
    }

    $label = match ($level) {
        'degraded' => 'Degraded',
        'attention' => 'Needs attention',
        default => 'Active',
    };

    return ['level' => $level, 'label' => $label, 'issues' => $issues];
}

/**
 * @param array{level: string, label: string, issues: list<string>} $health
 */
function vp_system_health_render(array $health): void
{
    $lvl = preg_match('/^[a-z]+$/', $health['level']) ? $health['level'] : 'active';
    $tip = $health['issues'] !== [] ? implode(' · ', $health['issues']) : 'Core tables and routing look OK.';
    echo '<div class="vp-system-health vp-system-health--' . vp_h($lvl) . '" role="status" title="' . vp_h($tip) . '">';
    echo '<span class="vp-system-health__k">Console health</span>';
    echo '<span class="vp-system-health__dot" aria-hidden="true"></span>';
    echo '<span class="vp-system-health__state">' . vp_h($health['label']) . '</span>';
    echo '</div>';
}

/**
 * Breadcrumb trail. Last item typically has href null (current page).
 *
 * @param list<array{label: string, href: ?string}> $items
 */
function vp_breadcrumbs(array $items): void
{
    if ($items === []) {
        return;
    }
    echo '<nav class="vp-breadcrumbs" aria-label="Breadcrumb">';
    echo '<ol class="vp-breadcrumbs__list">';
    $n = count($items);
    foreach ($items as $i => $item) {
        $label = (string) ($item['label'] ?? '');
        if ($label === '') {
            continue;
        }
        $href = $item['href'] ?? null;
        $isLast = $i === $n - 1;
        echo '<li class="vp-breadcrumbs__item">';
        if ($href !== null && $href !== '') {
            echo '<a class="vp-breadcrumbs__link" href="' . vp_h($href) . '">' . vp_h($label) . '</a>';
        } else {
            echo '<span class="vp-breadcrumbs__current"' . ($isLast ? ' aria-current="page"' : '') . '>' . vp_h($label) . '</span>';
        }
        if (! $isLast) {
            echo '<span class="vp-breadcrumbs__sep" aria-hidden="true">' . vp_nav_icon_chevron_right() . '</span>';
        }
        echo '</li>';
    }
    echo '</ol>';
    echo '</nav>';
}

function vp_nav_icon_chevron_right(): string
{
    return '<svg class="vp-breadcrumbs__chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>';
}

/**
 * @param list<array{label: string, href: string, variant?: string}> $actions variant: primary (default) | ghost
 */
function vp_empty_state(string $title, string $description, array $actions = []): void
{
    echo '<div class="vp-empty" role="status">';
    echo '<div class="vp-empty__body">';
    echo '<h2 class="vp-empty__title">' . vp_h($title) . '</h2>';
    echo '<p class="vp-empty__desc">' . vp_h($description) . '</p>';
    if ($actions !== []) {
        echo '<div class="vp-empty__actions">';
        foreach ($actions as $a) {
            $lab = (string) ($a['label'] ?? '');
            $href = (string) ($a['href'] ?? '');
            if ($lab === '' || $href === '') {
                continue;
            }
            $variant = ($a['variant'] ?? 'primary') === 'ghost' ? 'ghost' : 'primary';
            $cls = $variant === 'ghost' ? 'vp-btn vp-btn--ghost' : 'vp-btn vp-btn--primary';
            echo '<a class="' . vp_h($cls) . '" href="' . vp_h($href) . '">' . vp_h($lab) . '</a>';
        }
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';
}

/**
 * Warn when core tables from migrations are missing (non-fatal; repos may return empty).
 */
function vp_schema_migration_alerts(\PDO $pdo): void
{
    $rides = SchemaInspector::tableExists($pdo, 'rides');
    $riders = SchemaInspector::tableExists($pdo, 'rider_users');
    if ($rides && $riders) {
        return;
    }
    $parts = [];
    if (! $rides) {
        $parts[] = 'rides (import <code class="vp-inline-code">backend/sql/migration_rides.sql</code>)';
    }
    if (! $riders) {
        $parts[] = 'rider_users (import <code class="vp-inline-code">backend/sql/migration_rider_auth.sql</code> or <code class="vp-inline-code">backend/sql/schema_tables_only.sql</code>)';
    }
    echo '<div class="vp-alert vp-alert--warn" role="status">';
    echo '<p class="vp-alert__title">Finish database setup</p>';
    echo '<p class="vp-alert__body">These tables are missing on this database: ' . implode('; ', $parts) . '. Until they exist, ride and rider data will stay empty.</p>';
    echo '</div>';
}

function vp_schema_single_table_alert(\PDO $pdo, string $table, string $migrationFile, string $label): void
{
    if (SchemaInspector::tableExists($pdo, $table)) {
        return;
    }
    echo '<div class="vp-alert vp-alert--warn" role="status">';
    echo '<p class="vp-alert__title">' . vp_h($label) . ' unavailable</p>';
    echo '<p class="vp-alert__body">Create the <code class="vp-inline-code">' . vp_h($table) . '</code> table by importing <code class="vp-inline-code">backend/sql/' . vp_h($migrationFile) . '</code> in phpMyAdmin (or your SQL client).</p>';
    echo '</div>';
}

/**
 * JSON-encode a string safe for use inside HTML attribute (e.g. onsubmit confirm).
 */
function vp_confirm_attr(string $message): string
{
    $j = json_encode($message, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);

    return htmlspecialchars($j !== false ? $j : '""', ENT_QUOTES, 'UTF-8');
}
