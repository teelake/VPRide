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

    /** Length of admin-emailed temporary passwords (must be >= MIN_PASSWORD_LEN). */
    private const PROVISIONED_PASSWORD_LENGTH = 8;

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
        $name = self::displayNameFromGoogleClaims($claims);
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
        $dn = trim((string) ($displayName ?? ''));
        if ($dn === '') {
            throw new RuntimeException('display_name_required');
        }
        if (mb_strlen($dn) > 255) {
            throw new RuntimeException('display_name_too_long');
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
                'INSERT INTO rider_users (google_sub, password_hash, email, display_name, photo_url, must_change_password) '
                . 'VALUES (NULL, ?, ?, ?, NULL, 0)',
            );
            $ins->execute([$hash, $email, $dn]);
            $userId = (int) $this->pdo->lastInsertId();
            $out = $this->insertSession(
                $userId,
                $email,
                $dn,
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
     * Creates a rider_users row with a random password (no session). Intended for admin fleet onboarding.
     * Caller may wrap in a transaction together with fleet_drivers insert/update.
     *
     * @return array{userId: int, plainPassword: string}
     */
    public function createPasswordUserWithGeneratedPassword(string $email, string $displayName): array
    {
        $email = trim(strtolower($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('invalid_email');
        }
        $dn = trim($displayName);
        if ($dn === '') {
            throw new RuntimeException('display_name_required');
        }
        if (mb_strlen($dn) > 255) {
            throw new RuntimeException('display_name_too_long');
        }
        $plain = self::generateRandomPassword();
        if (strlen($plain) !== self::PROVISIONED_PASSWORD_LENGTH) {
            throw new RuntimeException('password_generation_failed');
        }
        $hash = password_hash($plain, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new RuntimeException('hash_failed');
        }

        $chk = $this->pdo->prepare('SELECT id, google_sub, password_hash FROM rider_users WHERE email = ? LIMIT 1');
        $chk->execute([$email]);
        $row = $chk->fetch();
        if ($row !== false) {
            throw new RuntimeException('email_taken');
        }

        try {
            $ins = $this->pdo->prepare(
                'INSERT INTO rider_users (google_sub, password_hash, email, display_name, photo_url, must_change_password, driver_account_only) '
                . 'VALUES (NULL, ?, ?, ?, NULL, 1, 1)',
            );
            $ins->execute([$hash, $email, $dn]);

            return [
                'userId' => (int) $this->pdo->lastInsertId(),
                'plainPassword' => $plain,
            ];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate')) {
                throw new RuntimeException('email_taken', 0, $e);
            }
            throw $e;
        }
    }

    private static function generateRandomPassword(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $len = strlen($alphabet);
        $out = '';
        for ($i = 0; $i < self::PROVISIONED_PASSWORD_LENGTH; $i++) {
            $out .= $alphabet[random_int(0, $len - 1)];
        }

        return $out;
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

    /**
     * Google ID tokens may expose `name`, or `given_name` + `family_name`. A non-empty display name is required for sign-up.
     *
     * @param  object{name?: string, given_name?: string, family_name?: string}  $claims
     */
    private static function displayNameFromGoogleClaims(object $claims): string
    {
        $name = isset($claims->name) && is_string($claims->name) ? trim($claims->name) : '';
        if ($name !== '') {
            return mb_substr($name, 0, 255);
        }
        $given = isset($claims->given_name) && is_string($claims->given_name) ? trim($claims->given_name) : '';
        $family = isset($claims->family_name) && is_string($claims->family_name) ? trim($claims->family_name) : '';
        $combined = trim($given . ' ' . $family);
        if ($combined !== '') {
            return mb_substr($combined, 0, 255);
        }

        throw new RuntimeException('name_required');
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
            'INSERT INTO rider_users (google_sub, password_hash, email, display_name, photo_url, must_change_password) '
            . 'VALUES (?, NULL, ?, ?, ?, 0)',
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

    /**
     * Email/password accounts only (excludes Google-only riders with no password).
     */
    public function findRiderIdWithPasswordByEmail(string $email): ?int
    {
        $email = trim(strtolower($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT id FROM rider_users WHERE email = ? AND password_hash IS NOT NULL AND password_hash != \'\' LIMIT 1',
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return (int) $row['id'];
    }

    public function setPasswordFromReset(int $riderUserId, string $newPlain): void
    {
        if (strlen($newPlain) < self::MIN_PASSWORD_LEN) {
            throw new RuntimeException('password_too_short');
        }
        $newHash = password_hash($newPlain, PASSWORD_DEFAULT);
        if ($newHash === false) {
            throw new RuntimeException('hash_failed');
        }
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE rider_users SET password_hash = ?, must_change_password = 0 WHERE id = ?',
            );
            $stmt->execute([$newHash, $riderUserId]);
            $rev = $this->pdo->prepare(
                'UPDATE rider_sessions SET revoked_at = UTC_TIMESTAMP() WHERE rider_user_id = ? AND revoked_at IS NULL',
            );
            $rev->execute([$riderUserId]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function updateDisplayName(int $riderUserId, string $displayName): void
    {
        if ($riderUserId < 1) {
            throw new RuntimeException('invalid_user');
        }
        $dn = trim($displayName);
        if ($dn === '') {
            throw new RuntimeException('display_name_required');
        }
        if (mb_strlen($dn) > 255) {
            throw new RuntimeException('display_name_too_long');
        }
        $stmt = $this->pdo->prepare('UPDATE rider_users SET display_name = ? WHERE id = ?');
        $stmt->execute([$dn, $riderUserId]);
        if ($stmt->rowCount() < 1) {
            $chk = $this->pdo->prepare('SELECT id FROM rider_users WHERE id = ? LIMIT 1');
            $chk->execute([$riderUserId]);
            if ($chk->fetchColumn() === false) {
                throw new RuntimeException('user_not_found');
            }
        }
    }

    /**
     * Verifies current password, sets a new one, revokes all sessions, returns a fresh session (same shape as login).
     *
     * @return array{sessionToken: string, user: array<string, mixed>}
     */
    public function changePasswordAuthenticated(int $riderUserId, string $currentPlain, string $newPlain): array
    {
        if ($riderUserId < 1) {
            throw new RuntimeException('invalid_user');
        }
        if ($currentPlain === '' || $newPlain === '') {
            throw new RuntimeException('invalid_credentials');
        }
        if (strlen($newPlain) < self::MIN_PASSWORD_LEN) {
            throw new RuntimeException('password_too_short');
        }
        if ($currentPlain === $newPlain) {
            throw new RuntimeException('password_unchanged');
        }
        $sel = $this->pdo->prepare(
            'SELECT id, email, display_name, photo_url, password_hash FROM rider_users WHERE id = ? LIMIT 1',
        );
        $sel->execute([$riderUserId]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new RuntimeException('user_not_found');
        }
        $hash = $row['password_hash'] ?? null;
        if ($hash === null || $hash === '' || ! is_string($hash)) {
            throw new RuntimeException('no_password_account');
        }
        if (! password_verify($currentPlain, $hash)) {
            throw new RuntimeException('invalid_credentials');
        }
        $newHash = password_hash($newPlain, PASSWORD_DEFAULT);
        if ($newHash === false) {
            throw new RuntimeException('hash_failed');
        }

        $this->pdo->beginTransaction();
        try {
            $up = $this->pdo->prepare(
                'UPDATE rider_users SET password_hash = ?, must_change_password = 0 WHERE id = ?',
            );
            $up->execute([$newHash, $riderUserId]);
            $rev = $this->pdo->prepare(
                'UPDATE rider_sessions SET revoked_at = UTC_TIMESTAMP() WHERE rider_user_id = ? AND revoked_at IS NULL',
            );
            $rev->execute([$riderUserId]);
            $email = (string) $row['email'];
            $dn = $row['display_name'] !== null ? (string) $row['display_name'] : null;
            $photo = $row['photo_url'] !== null ? (string) $row['photo_url'] : null;
            $out = $this->insertSession($riderUserId, $email, $dn, $photo);
            $this->pdo->commit();

            return $out;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Mobile /me user object (requires must_change_password, driver_account_only — run migrations).
     *
     * @return array<string, mixed>|null
     */
    public function getUserPayloadForMe(int $riderUserId): ?array
    {
        if ($riderUserId < 1) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT id, email, display_name, photo_url, password_hash, must_change_password, driver_account_only FROM rider_users WHERE id = ? LIMIT 1',
        );
        $st->execute([$riderUserId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $h = $row['password_hash'] ?? null;
        $hasPassword = is_string($h) && $h !== '';
        $mc = $row['must_change_password'] ?? 0;
        $dao = $row['driver_account_only'] ?? 0;

        return [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'displayName' => $row['display_name'] !== null ? (string) $row['display_name'] : null,
            'photoUrl' => $row['photo_url'] !== null ? (string) $row['photo_url'] : null,
            'hasPassword' => $hasPassword,
            'mustChangePassword' => (int) $mc === 1,
            'driverAccountOnly' => (int) $dao === 1,
        ];
    }

    public function setPhotoUrl(int $riderUserId, string $photoUrl): void
    {
        if ($riderUserId < 1) {
            throw new RuntimeException('invalid_user');
        }
        $url = trim($photoUrl);
        if ($url === '' || strlen($url) > 512) {
            throw new RuntimeException('invalid_photo_url');
        }
        $stmt = $this->pdo->prepare('UPDATE rider_users SET photo_url = ? WHERE id = ?');
        $stmt->execute([$url, $riderUserId]);
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
