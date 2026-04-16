<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;
use PDOException;
use RuntimeException;

final class AdminUserRepository
{
    public function __construct(private PDO $pdo) {}

    public function emailExists(string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM admins WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);

        return (bool) $stmt->fetchColumn();
    }

    public function findIdByEmail(string $email): ?int
    {
        $email = trim($email);
        if ($email === '') {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM admins WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $v = $stmt->fetchColumn();

        return $v === false ? null : (int) $v;
    }

    /**
     * @return array{id: int, email: string, display_name: ?string, role_slug: string, role_label: string, created_at: string}|null
     */
    public function getProfile(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.email, a.display_name, a.created_at, r.slug AS role_slug, r.label AS role_label '
            . 'FROM admins a INNER JOIN admin_roles r ON r.id = a.role_id WHERE a.id = ? LIMIT 1',
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'display_name' => $row['display_name'] !== null && $row['display_name'] !== ''
                ? (string) $row['display_name']
                : null,
            'role_slug' => (string) $row['role_slug'],
            'role_label' => (string) $row['role_label'],
            'created_at' => (string) $row['created_at'],
        ];
    }

    public function updateProfile(int $id, string $email, string $displayName): void
    {
        $email = trim($email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Valid email required');
        }
        $displayName = trim($displayName);
        if ($displayName === '') {
            $displayName = null;
        } elseif (strlen($displayName) > 255) {
            throw new RuntimeException('Display name is too long');
        }
        $stmt = $this->pdo->prepare('SELECT id FROM admins WHERE email = ? AND id != ? LIMIT 1');
        $stmt->execute([$email, $id]);
        if ($stmt->fetchColumn()) {
            throw new RuntimeException('Another account already uses this email');
        }
        $stmt = $this->pdo->prepare('UPDATE admins SET email = ?, display_name = ? WHERE id = ?');
        $stmt->execute([$email, $displayName, $id]);
    }

    public function changePassword(int $id, string $currentPlain, string $newPlain): void
    {
        if (strlen($newPlain) < 8) {
            throw new RuntimeException('New password must be at least 8 characters');
        }
        $stmt = $this->pdo->prepare('SELECT password_hash FROM admins WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $hash = $stmt->fetchColumn();
        if ($hash === false) {
            throw new RuntimeException('Account not found');
        }
        $hash = (string) $hash;
        if (! password_verify($currentPlain, $hash)) {
            throw new RuntimeException('Current password is incorrect');
        }
        if (password_verify($newPlain, $hash)) {
            throw new RuntimeException('New password must differ from your current password');
        }
        $this->setPasswordHash($id, $newPlain);
    }

    public function setPasswordFromReset(int $adminId, string $newPlain): void
    {
        $this->setPasswordHash($adminId, $newPlain);
    }

    private function setPasswordHash(int $id, string $plain): void
    {
        if (strlen($plain) < 8) {
            throw new RuntimeException('Password must be at least 8 characters');
        }
        $newHash = password_hash($plain, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
        $stmt->execute([$newHash, $id]);
    }

    public function create(string $email, string $passwordPlain, int $roleId): int
    {
        $email = trim($email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Valid email required');
        }
        if (strlen($passwordPlain) < 8) {
            throw new RuntimeException('Password must be at least 8 characters');
        }
        $stmt = $this->pdo->prepare('SELECT id FROM admin_roles WHERE id = ? LIMIT 1');
        $stmt->execute([$roleId]);
        if (! $stmt->fetchColumn()) {
            throw new RuntimeException('Invalid role');
        }
        if ($this->emailExists($email)) {
            throw new RuntimeException('An admin with this email already exists');
        }
        $hash = password_hash($passwordPlain, PASSWORD_DEFAULT);
        try {
            $ins = $this->pdo->prepare(
                'INSERT INTO admins (email, password_hash, role_id) VALUES (?, ?, ?)',
            );
            $ins->execute([$email, $hash, $roleId]);

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            throw new RuntimeException('Could not create account: ' . $e->getMessage());
        }
    }

    /**
     * @return list<array{id: int, email: string, role: string, role_label: string, created_at: string}>
     */
    public function listAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT a.id, a.email, a.display_name, r.slug AS role, r.label AS role_label, a.created_at '
            . 'FROM admins a INNER JOIN admin_roles r ON r.id = a.role_id '
            . 'ORDER BY a.id ASC',
        );

        return $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
