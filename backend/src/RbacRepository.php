<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;
use PDOException;
use RuntimeException;

final class RbacRepository
{
    public function __construct(private PDO $pdo) {}

    public function roleCan(int $roleId, string $permissionSlug): bool
    {
        if ($this->isSuperuser($roleId)) {
            return true;
        }
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM admin_role_permissions arp '
            . 'INNER JOIN admin_permissions ap ON ap.id = arp.permission_id '
            . 'WHERE arp.role_id = ? AND ap.slug = ? LIMIT 1',
        );
        $stmt->execute([$roleId, $permissionSlug]);

        return (bool) $stmt->fetchColumn();
    }

    public function isSuperuser(int $roleId): bool
    {
        $stmt = $this->pdo->prepare('SELECT is_superuser FROM admin_roles WHERE id = ? LIMIT 1');
        $stmt->execute([$roleId]);
        $v = $stmt->fetchColumn();

        return (int) $v === 1;
    }

    /**
     * @return list<array{id: int, slug: string, label: string, is_superuser: int, is_system: int, admin_count: int}>
     */
    public function listRolesWithCounts(): array
    {
        $sql = 'SELECT r.id, r.slug, r.label, r.is_superuser, r.is_system, '
            . 'COUNT(a.id) AS admin_count '
            . 'FROM admin_roles r LEFT JOIN admins a ON a.role_id = r.id '
            . 'GROUP BY r.id, r.slug, r.label, r.is_superuser, r.is_system '
            . 'ORDER BY r.is_system DESC, r.id ASC';
        $stmt = $this->pdo->query($sql);

        return $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array{id: int, slug: string, label: string, category: string}>
     */
    public function listPermissions(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, slug, label, category FROM admin_permissions ORDER BY category ASC, label ASC',
        );

        return $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<int>
     */
    public function permissionIdsForRole(int $roleId): array
    {
        $stmt = $this->pdo->prepare('SELECT permission_id FROM admin_role_permissions WHERE role_id = ?');
        $stmt->execute([$roleId]);
        $ids = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ids[] = (int) $row['permission_id'];
        }

        return $ids;
    }

    /**
     * @param list<int> $permissionIds
     */
    public function setRolePermissions(int $roleId, array $permissionIds): void
    {
        $role = $this->getRoleRow($roleId);
        if ($role === null) {
            throw new RuntimeException('Role not found');
        }
        if ((int) $role['is_superuser'] === 1) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM admin_role_permissions WHERE role_id = ?')->execute([$roleId]);
            $ins = $this->pdo->prepare(
                'INSERT INTO admin_role_permissions (role_id, permission_id) VALUES (?, ?)',
            );
            foreach (array_unique(array_map('intval', $permissionIds)) as $pid) {
                if ($pid <= 0) {
                    continue;
                }
                try {
                    $ins->execute([$roleId, $pid]);
                } catch (PDOException) {
                    // ignore invalid permission id
                }
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @return array{id: int, slug: string, label: string, is_superuser: int, is_system: int}|null
     */
    public function getRoleRow(int $roleId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, slug, label, is_superuser, is_system FROM admin_roles WHERE id = ? LIMIT 1',
        );
        $stmt->execute([$roleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function createRole(string $slug, string $label): int
    {
        $slug = $this->normalizeSlug($slug);
        if ($slug === '') {
            throw new RuntimeException('Invalid role key');
        }
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO admin_roles (slug, label, is_superuser, is_system) VALUES (?, ?, 0, 0)',
            );
            $stmt->execute([$slug, $label]);
            $newId = (int) $this->pdo->lastInsertId();
            if ($newId < 1) {
                throw new RuntimeException('Could not create role — check database permissions and auto-increment.');
            }

            return $newId;
        } catch (PDOException $e) {
            if (self::messageContains($e->getMessage(), 'Duplicate')) {
                throw new RuntimeException('A role with this key already exists');
            }
            throw $e;
        }
    }

    public function updateRoleLabel(int $roleId, string $label): void
    {
        $stmt = $this->pdo->prepare('UPDATE admin_roles SET label = ? WHERE id = ? AND is_system = 0');
        $stmt->execute([$label, $roleId]);
        if ($stmt->rowCount() === 0) {
            $row = $this->getRoleRow($roleId);
            if ($row === null) {
                throw new RuntimeException('Role not found');
            }
            throw new RuntimeException('Built-in roles cannot be renamed here');
        }
    }

    public function deleteRole(int $roleId): void
    {
        $row = $this->getRoleRow($roleId);
        if ($row === null) {
            throw new RuntimeException('Role not found');
        }
        if ((int) $row['is_system'] === 1) {
            throw new RuntimeException('Built-in roles cannot be deleted');
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM admins WHERE role_id = ?');
        $stmt->execute([$roleId]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException('Reassign admins before deleting this role');
        }
        $this->pdo->prepare('DELETE FROM admin_roles WHERE id = ?')->execute([$roleId]);
    }

    public function createPermission(string $slug, string $label, string $category = 'general'): void
    {
        $slug = $this->normalizePermSlug($slug);
        if ($slug === '') {
            throw new RuntimeException('Invalid permission key');
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO admin_permissions (slug, label, category) VALUES (?, ?, ?)',
        );
        try {
            $stmt->execute([$slug, $label, $category === '' ? 'general' : $category]);
        } catch (PDOException $e) {
            if (self::messageContains($e->getMessage(), 'Duplicate')) {
                throw new RuntimeException('Permission already exists');
            }
            throw $e;
        }
    }

    private static function messageContains(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        return strpos($haystack, $needle) !== false;
    }

    public function normalizeSlug(string $raw): string
    {
        $s = strtolower(trim($raw));
        $s = preg_replace('/[^a-z0-9_]+/', '_', $s) ?? '';
        $s = trim((string) $s, '_');

        return strlen($s) > 64 ? substr($s, 0, 64) : $s;
    }

    private function normalizePermSlug(string $raw): string
    {
        $s = strtolower(trim($raw));
        $s = preg_replace('/[^a-z0-9._-]+/', '', $s) ?? '';

        return strlen($s) > 64 ? substr($s, 0, 64) : $s;
    }
}
