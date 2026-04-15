<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;

/**
 * Permission checks against admin_roles / admin_role_permissions.
 */
final class RbacRuntime
{
    public static function can(PDO $pdo, int $roleId, string $permission): bool
    {
        return (new RbacRepository($pdo))->roleCan($roleId, $permission);
    }
}
