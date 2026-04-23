<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;
use PDOException;

/**
 * Creates or looks up a rider for manual console bookings (e.g. phone-first / SMS customers).
 */
final class ConsoleRiderService
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return array{ok: true, riderUserId: int}|array{ok: false, message: string}
     */
    public function resolveRiderForConsoleBooking(
        bool $createNew,
        string $existingEmail,
        string $newDisplayName,
        string $newPhoneDigits,
        ?string $newEmailOptional,
    ): array {
        $existingEmail = trim(strtolower($existingEmail));
        if (! $createNew) {
            if ($existingEmail === '' || ! filter_var($existingEmail, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'message' => 'Valid rider email required.'];
            }
            $st = $this->pdo->prepare('SELECT id FROM rider_users WHERE email = ? LIMIT 1');
            $st->execute([$existingEmail]);
            $id = $st->fetchColumn();
            if ($id === false) {
                return ['ok' => false, 'message' => 'No rider account with that email.'];
            }

            return ['ok' => true, 'riderUserId' => (int) $id];
        }

        $dn = trim($newDisplayName);
        if ($dn === '' || mb_strlen($dn) > 255) {
            return ['ok' => false, 'message' => 'Display name is required (max 255 characters).'];
        }

        $phone = self::normalizePhoneDigits($newPhoneDigits);
        if (SchemaInspector::columnExists($this->pdo, 'rider_users', 'phone') && $phone !== '') {
            $chk = $this->pdo->prepare('SELECT id FROM rider_users WHERE phone = ? LIMIT 1');
            $chk->execute([$phone]);
            $found = $chk->fetchColumn();
            if ($found !== false) {
                return ['ok' => true, 'riderUserId' => (int) $found];
            }
        }

        $email = $newEmailOptional !== null ? trim(strtolower($newEmailOptional)) : '';
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Optional email is not valid.'];
        }
        if ($email === '') {
            $email = 'r' . str_replace('.', '', uniqid('', true)) . '@example.com';
        }

        $chkE = $this->pdo->prepare('SELECT id FROM rider_users WHERE email = ? LIMIT 1');
        $chkE->execute([$email]);
        if ($chkE->fetchColumn() !== false) {
            return ['ok' => false, 'message' => 'That email is already in use. Use “existing rider” or a different address.'];
        }

        if (! SchemaInspector::columnExists($this->pdo, 'rider_users', 'password_hash')) {
            return ['ok' => false, 'message' => 'Rider account schema is too old to create console riders.'];
        }

        $hash = password_hash(
            bin2hex(random_bytes(12)),
            PASSWORD_DEFAULT,
        );
        if ($hash === false) {
            return ['ok' => false, 'message' => 'Could not create credentials.'];
        }

        $hasPhone = SchemaInspector::columnExists($this->pdo, 'rider_users', 'phone');
        $hasMc = SchemaInspector::columnExists($this->pdo, 'rider_users', 'must_change_password');
        $sql = 'INSERT INTO rider_users (google_sub, password_hash, email, display_name, photo_url'
            . ($hasMc ? ', must_change_password' : '')
            . ($hasPhone ? ', phone' : '')
            . ') VALUES (NULL, ?, ?, ?, NULL'
            . ($hasMc ? ', 1' : '')
            . ($hasPhone ? ', ?' : '')
            . ')';
        $params = [$hash, $email, $dn];
        if ($hasPhone) {
            $params[] = $phone !== '' ? $phone : null;
        }

        try {
            $ins = $this->pdo->prepare($sql);
            $ins->execute($params);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate')) {
                return ['ok' => false, 'message' => 'Could not create rider (duplicate email or phone).'];
            }
            throw $e;
        }

        $newId = (int) $this->pdo->lastInsertId();
        if ($newId < 1) {
            return ['ok' => false, 'message' => 'Rider was not created.'];
        }

        return ['ok' => true, 'riderUserId' => $newId];
    }

    public static function normalizePhoneDigits(string $raw): string
    {
        $d = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($d) > 20) {
            $d = substr($d, 0, 20);
        }

        return $d;
    }
}
