<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Env;

final class Session
{
    public const NAME = 'vms_session';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', '28800');
        session_name(self::NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => Env::bool('SESSION_SECURE_COOKIE', false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function csrf(): string
    {
        self::start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function rotateCsrf(): string
    {
        self::start();
        return $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    /** Returns the admin id stored in the session, without creating a session for anonymous visitors. */
    public static function adminId(): ?string
    {
        if (!isset($_COOKIE[self::NAME])) {
            return null;
        }
        self::start();
        $id = $_SESSION['admin_id'] ?? null;
        return is_string($id) ? $id : null;
    }

    public static function release(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        $p = session_get_cookie_params();
        setcookie(self::NAME, '', [
            'expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'],
            'httponly' => true, 'samesite' => 'Lax',
        ]);
        session_destroy();
    }
}
