<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;

require_once __DIR__ . '/Database.php';
// Shared hosts may not run Composer; do not rely on vendor autoload for RBAC.
require_once __DIR__ . '/RbacRepository.php';
require_once __DIR__ . '/RbacRuntime.php';

final class Auth
{
    private static bool $sessionRoleHydrated = false;

    public const SESSION_ADMIN_ID = 'admin_id';
    public const SESSION_ADMIN_EMAIL = 'admin_email';
    public const SESSION_ADMIN_ROLE = 'admin_role';
    public const SESSION_ADMIN_ROLE_ID = 'admin_role_id';

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $cookiePath = Config::basePath() ?: '/';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookiePath,
            'secure' => ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
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
     * @return array{0:int,1:string,2:string}|null admin id, email, role slug (for display)
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

    public static function currentRoleId(): ?int
    {
        self::startSession();
        $rid = $_SESSION[self::SESSION_ADMIN_ROLE_ID] ?? null;
        if (! is_int($rid) && ! is_numeric($rid)) {
            return null;
        }

        return (int) $rid;
    }

    /**
     * After RBAC deploy, older sessions may lack admin_role_id. Load from DB once per request.
     */
    public static function hydrateAdminRoleSessionIfNeeded(): void
    {
        if (self::$sessionRoleHydrated) {
            return;
        }
        self::$sessionRoleHydrated = true;
        self::startSession();
        $a = self::currentAdmin();
        if ($a === null) {
            return;
        }
        if (self::currentRoleId() !== null) {
            return;
        }
        try {
            $stmt = Database::pdo()->prepare(
                'SELECT a.role_id, r.slug AS role_slug FROM admins a '
                . 'INNER JOIN admin_roles r ON r.id = a.role_id WHERE a.id = ? LIMIT 1',
            );
            $stmt->execute([$a[0]]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row !== false && isset($row['role_id'], $row['role_slug'])) {
                $_SESSION[self::SESSION_ADMIN_ROLE_ID] = (int) $row['role_id'];
                $_SESSION[self::SESSION_ADMIN_ROLE] = (string) $row['role_slug'];
            }
        } catch (\Throwable) {
            // Schema not migrated or DB error — leave session; permission checks may fail
        }
    }

    public static function login(PDO $pdo, string $email, string $password): bool
    {
        $stmt = $pdo->prepare(
            'SELECT a.id, a.password_hash, a.role_id, r.slug AS role_slug '
            . 'FROM admins a INNER JOIN admin_roles r ON r.id = a.role_id '
            . 'WHERE a.email = ? LIMIT 1',
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if (! $row || ! password_verify($password, $row['password_hash'])) {
            return false;
        }
        self::startSession();
        session_regenerate_id(true);
        $_SESSION[self::SESSION_ADMIN_ID] = (int) $row['id'];
        $_SESSION[self::SESSION_ADMIN_EMAIL] = $email;
        $_SESSION[self::SESSION_ADMIN_ROLE_ID] = (int) $row['role_id'];
        $_SESSION[self::SESSION_ADMIN_ROLE] = (string) $row['role_slug'];

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
            header('Location: ' . Config::url('/admin/login'));
            exit;
        }
    }

    public static function requireSystemAdmin(): void
    {
        self::requirePermission('rbac.manage');
    }

    public static function can(string $permission): bool
    {
        self::hydrateAdminRoleSessionIfNeeded();
        $a = self::currentAdmin();
        $roleId = self::currentRoleId();
        if ($a === null || $roleId === null) {
            return false;
        }

        return RbacRuntime::can(Database::pdo(), $roleId, $permission);
    }

    public static function requirePermission(string $permission): void
    {
        if (self::currentAdmin() === null) {
            header('Location: ' . Config::url('/admin/login'));
            exit;
        }
        if (! self::can($permission)) {
            http_response_code(403);
            $forbiddenTitle = 'Access denied';
            $forbiddenMessage = 'You do not have permission to view this page.';
            require dirname(__DIR__) . '/public/admin/includes/forbidden_page.php';
            exit;
        }
    }
}
