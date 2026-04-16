<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class RiderAuthService
{
    private const MIN_PASSWORD_LEN = 8;

    public function __construct(private PDO $pdo) {}

    /**
     * @param  object{sub: string, email?: string, name?: string, picture?: string}  $claims
     * @return array{sessionToken: string, user: array<string, mixed>}
     */
    public function issueSessionForGoogleUser(object $claims): array
    {
        $sub = (string) $claims->sub;
        $email = isset($claims->email) && is_string($claims->email)
            ? trim(strtolower($claims->email))
            : '';
        if ($email === '') {
            throw new RuntimeException('Google token has no email');
        }
        $name = isset($claims->name) && is_string($claims->name) ? $claims->name : null;
        $picture = isset($claims->picture) && is_string($claims->picture) ? $claims->picture : null;

        $this->pdo->beginTransaction();
        try {
            $userId = $this->upsertRiderUserFromGoogle($sub, $email, $name, $picture);
            $out = $this->insertSession($userId, $email, $name, $picture);
            $this->pdo->commit();

            return $out;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @return array{sessionToken: string, user: array<string, mixed>}
     */
    public function registerWithPassword(string $email, string $password, ?string $displayName): array
    {
        $email = trim(strtolower($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('invalid_email');
        }
        if (strlen($password) < self::MIN_PASSWORD_LEN) {
            throw new RuntimeException('password_too_short');
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new RuntimeException('hash_failed');
        }

        $this->pdo->beginTransaction();
        try {
            $chk = $this->pdo->prepare('SELECT id, google_sub, password_hash FROM rider_users WHERE email = ? LIMIT 1');
            $chk->execute([$email]);
            $row = $chk->fetch();
            if ($row !== false) {
                if ($row['password_hash'] !== null && $row['password_hash'] !== '') {
                    throw new RuntimeException('email_taken');
                }
                // Email used by Google-only row: allow adding password? Skip — user should use Google or contact support.
                throw new RuntimeException('email_taken');
            }

            $ins = $this->pdo->prepare(
                'INSERT INTO rider_users (google_sub, password_hash, email, display_name, photo_url) VALUES (NULL, ?, ?, ?, NULL)',
            );
            $ins->execute([$hash, $email, $displayName !== null && $displayName !== '' ? $displayName : null]);
            $userId = (int) $this->pdo->lastInsertId();
            $out = $this->insertSession(
                $userId,
                $email,
                $displayName !== null && $displayName !== '' ? $displayName : null,
                null,
            );
            $this->pdo->commit();

            return $out;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate')) {
                throw new RuntimeException('email_taken', 0, $e);
            }
            throw $e;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @return array{sessionToken: string, user: array<string, mixed>}
     */
    public function loginWithPassword(string $email, string $password): array
    {
        $email = trim(strtolower($email));
        if ($email === '' || $password === '') {
            throw new RuntimeException('invalid_credentials');
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, password_hash, email, display_name, photo_url FROM rider_users WHERE email = ? LIMIT 1',
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row === false || empty($row['password_hash']) || ! is_string($row['password_hash'])) {
            throw new RuntimeException('invalid_credentials');
        }
        if (! password_verify($password, (string) $row['password_hash'])) {
            throw new RuntimeException('invalid_credentials');
        }

        $this->pdo->beginTransaction();
        try {
            $out = $this->insertSession(
                (int) $row['id'],
                (string) $row['email'],
                $row['display_name'] !== null ? (string) $row['display_name'] : null,
                $row['photo_url'] !== null ? (string) $row['photo_url'] : null,
            );
            $this->pdo->commit();

            return $out;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @return array{sessionToken: string, user: array<string, mixed>}
     */
    private function insertSession(int $userId, string $email, ?string $displayName, ?string $photoUrl): array
    {
        $rawToken = bin2hex(random_bytes(32));
        $hash = hash('sha256', $rawToken);
        $expires = gmdate('Y-m-d H:i:s', time() + 30 * 24 * 3600);

        $stmt = $this->pdo->prepare(
            'INSERT INTO rider_sessions (rider_user_id, token_hash, expires_at) VALUES (?, ?, ?)',
        );
        $stmt->execute([$userId, $hash, $expires]);

        return [
            'sessionToken' => $rawToken,
            'user' => [
                'id' => $userId,
                'email' => $email,
                'displayName' => $displayName,
                'photoUrl' => $photoUrl,
            ],
        ];
    }

    private function upsertRiderUserFromGoogle(
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

        $chk = $this->pdo->prepare(
            'SELECT id, google_sub FROM rider_users WHERE email = ? LIMIT 1',
        );
        $chk->execute([$email]);
        $existing = $chk->fetch();
        if ($existing !== false) {
            if ($existing['google_sub'] !== null && (string) $existing['google_sub'] !== '') {
                throw new RuntimeException('email_linked_other_google');
            }
            throw new RuntimeException('email_has_password_account');
        }

        $ins = $this->pdo->prepare(
            'INSERT INTO rider_users (google_sub, password_hash, email, display_name, photo_url) VALUES (?, NULL, ?, ?, ?)',
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
