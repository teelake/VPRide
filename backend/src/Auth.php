<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;

final class Auth
{
    public const SESSION_ADMIN_ID = 'admin_id';
    public const SESSION_ADMIN_EMAIL = 'admin_email';
    public const SESSION_ADMIN_ROLE = 'admin_role';

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_start();
    }

    public static function csrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public static function validateCsrf(?string $token): bool
    {
        self::startSession();
        return is_string($token)
            && isset($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $token);
    }

    /**
     * @return array{0:int,1:string,2:string}|null admin id, email, role
     */
    public static function currentAdmin(): ?array
    {
        self::startSession();
        $id = $_SESSION[self::SESSION_ADMIN_ID] ?? null;
        $email = $_SESSION[self::SESSION_ADMIN_EMAIL] ?? null;
        $role = $_SESSION[self::SESSION_ADMIN_ROLE] ?? null;
        if (! is_int($id) && ! is_numeric($id)) {
            return null;
        }
        if (! is_string($email) || ! is_string($role)) {
            return null;
        }

        return [(int) $id, $email, $role];
    }

    public static function login(PDO $pdo, string $email, string $password): bool
    {
        $stmt = $pdo->prepare('SELECT id, password_hash, role FROM admins WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if (! $row || ! password_verify($password, $row['password_hash'])) {
            return false;
        }
        self::startSession();
        session_regenerate_id(true);
        $_SESSION[self::SESSION_ADMIN_ID] = (int) $row['id'];
        $_SESSION[self::SESSION_ADMIN_EMAIL] = $email;
        $_SESSION[self::SESSION_ADMIN_ROLE] = (string) $row['role'];

        return true;
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
    }

    public static function requireLogin(): void
    {
        if (self::currentAdmin() === null) {
            header('Location: /admin/login.php');
            exit;
        }
    }

    public static function requireSystemAdmin(): void
    {
        $a = self::currentAdmin();
        if ($a === null || $a[2] !== 'system_admin') {
            http_response_code(403);
            echo 'Forbidden: system administrator role required.';
            exit;
        }
    }
}
