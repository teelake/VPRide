<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;
use Throwable;

final class RiderAuthService
{
    public function __construct(private PDO $pdo) {}

    /**
     * @param  object{sub: string, email?: string, name?: string, picture?: string}  $claims
     * @return array{sessionToken: string, user: array<string, mixed>}
     */
    public function issueSessionForGoogleUser(object $claims): array
    {
        $sub = (string) $claims->sub;
        $email = isset($claims->email) && is_string($claims->email)
            ? $claims->email
            : '';
        if ($email === '') {
            throw new RuntimeException('Google token has no email');
        }
        $name = isset($claims->name) && is_string($claims->name) ? $claims->name : null;
        $picture = isset($claims->picture) && is_string($claims->picture) ? $claims->picture : null;

        $this->pdo->beginTransaction();
        try {
            $userId = $this->upsertRiderUser($sub, $email, $name, $picture);
            $rawToken = bin2hex(random_bytes(32));
            $hash = hash('sha256', $rawToken);
            $expires = gmdate('Y-m-d H:i:s', time() + 30 * 24 * 3600);

            $stmt = $this->pdo->prepare(
                'INSERT INTO rider_sessions (rider_user_id, token_hash, expires_at) VALUES (?, ?, ?)',
            );
            $stmt->execute([$userId, $hash, $expires]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return [
            'sessionToken' => $rawToken,
            'user' => [
                'id' => $userId,
                'email' => $email,
                'displayName' => $name,
                'photoUrl' => $picture,
            ],
        ];
    }

    private function upsertRiderUser(
        string $googleSub,
        string $email,
        ?string $displayName,
        ?string $photoUrl,
    ): int {
        $sel = $this->pdo->prepare('SELECT id FROM rider_users WHERE google_sub = ? LIMIT 1');
        $sel->execute([$googleSub]);
        $row = $sel->fetch();
        if ($row !== false) {
            $id = (int) $row['id'];
            $up = $this->pdo->prepare(
                'UPDATE rider_users SET email = ?, display_name = ?, photo_url = ? WHERE id = ?',
            );
            $up->execute([$email, $displayName, $photoUrl, $id]);

            return $id;
        }

        $ins = $this->pdo->prepare(
            'INSERT INTO rider_users (google_sub, email, display_name, photo_url) VALUES (?, ?, ?, ?)',
        );
        $ins->execute([$googleSub, $email, $displayName, $photoUrl]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array{rider_user_id: int, email: string, display_name: ?string, photo_url: ?string}|null
     */
    public function resolveBearerToken(string $rawToken): ?array
    {
        $hash = hash('sha256', $rawToken);
        $sql = <<<'SQL'
SELECT u.id AS rider_user_id, u.email, u.display_name, u.photo_url
FROM rider_sessions s
INNER JOIN rider_users u ON u.id = s.rider_user_id
WHERE s.token_hash = ?
  AND s.revoked_at IS NULL
  AND s.expires_at > UTC_TIMESTAMP()
SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return [
            'rider_user_id' => (int) $row['rider_user_id'],
            'email' => (string) $row['email'],
            'display_name' => $row['display_name'] !== null ? (string) $row['display_name'] : null,
            'photo_url' => $row['photo_url'] !== null ? (string) $row['photo_url'] : null,
        ];
    }

    public function revokeBearerToken(string $rawToken): void
    {
        $hash = hash('sha256', $rawToken);
        $stmt = $this->pdo->prepare(
            'UPDATE rider_sessions SET revoked_at = UTC_TIMESTAMP() WHERE token_hash = ? AND revoked_at IS NULL',
        );
        $stmt->execute([$hash]);
    }

    public static function readBearerFromRequest(): ?string
    {
        $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (! is_string($h)) {
            return null;
        }
        if (preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $h, $m)) {
            return $m[1];
        }

        return null;
    }
}
