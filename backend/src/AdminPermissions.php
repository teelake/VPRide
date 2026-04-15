<?php

declare(strict_types=1);

namespace VprideBackend;

/**
 * Role → permission map for the admin console. Extend with DB-driven roles later if needed.
 */
final class AdminPermissions
{
    /** @var array<string, list<string>> */
    private const MAP = [
        'system_admin' => ['*'],
        'dispatcher' => [
            'dashboard.view',
            'regions.view',
            'rides.view',
            'riders.view',
            'team.view',
        ],
        'support' => [
            'dashboard.view',
            'rides.view',
            'riders.view',
        ],
    ];

    public static function can(string $role, string $permission): bool
    {
        $list = self::MAP[$role] ?? [];
        if (in_array('*', $list, true)) {
            return true;
        }

        return in_array($permission, $list, true);
    }
}
