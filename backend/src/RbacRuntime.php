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
        if (! class_exists(RbacRepository::class, false)) {
            require_once __DIR__ . '/RbacRepository.php';
        }

        return (new RbacRepository($pdo))->roleCan($roleId, $permission);
    }
}
