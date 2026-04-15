<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;

final class AdminUserRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return list<array{id: int, email: string, role: string, created_at: string}>
     */
    public function listAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, email, role, created_at FROM admins ORDER BY id ASC',
        );

        return $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
