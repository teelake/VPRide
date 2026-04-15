<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;

final class AdminUserRepository
{
    public function __construct(private PDO $pdo) {}

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
