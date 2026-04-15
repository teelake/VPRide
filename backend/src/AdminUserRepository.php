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
            'SELECT a.id, a.email, r.slug AS role, r.label AS role_label, a.created_at '
            . 'FROM admins a INNER JOIN admin_roles r ON r.id = a.role_id '
            . 'ORDER BY a.id ASC',
        );

        return $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
