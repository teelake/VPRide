<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;
use PDOException;

final class RiderPasswordResetRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Creates a new reset token; invalidates other pending tokens for this rider.
     *
     * @return non-empty-string raw token (only shown once — put in email)
     */
    public function createTokenForRider(int $riderUserId): string
    {
        $this->pdo->prepare(
            'DELETE FROM rider_password_resets WHERE rider_user_id = ? AND used_at IS NULL',
        )->execute([$riderUserId]);
        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw, false);
        $expires = date('Y-m-d H:i:s', time() + 3600);
        $this->pdo->prepare(
            'INSERT INTO rider_password_resets (rider_user_id, token_hash, expires_at) VALUES (?, ?, ?)',
        )->execute([$riderUserId, $hash, $expires]);

        return $raw;
    }

    /**
     * @return array{id: int, rider_user_id: int}|null
     */
    public function findValidRowByRawToken(string $rawToken): ?array
    {
        $rawToken = trim($rawToken);
        if (strlen($rawToken) < 64) {
            return null;
        }
        $hash = hash('sha256', $rawToken, false);
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, rider_user_id FROM rider_password_resets '
                . 'WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1',
            );
            $stmt->execute([$hash]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return null;
        }

        return $row === false ? null : ['id' => (int) $row['id'], 'rider_user_id' => (int) $row['rider_user_id']];
    }

    public function markUsed(int $resetRowId): void
    {
        $this->pdo->prepare(
            'UPDATE rider_password_resets SET used_at = NOW() WHERE id = ?',
        )->execute([$resetRowId]);
    }
}
