<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;

final class RiderUserRepository
{
    public function __construct(private PDO $pdo) {}

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM rider_users')->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecent(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT id, email, display_name, google_sub, created_at, updated_at '
            . 'FROM rider_users ORDER BY id DESC LIMIT ?',
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
