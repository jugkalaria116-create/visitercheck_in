<?php
declare(strict_types=1);

namespace App\Config;

use Dotenv\Dotenv;

final class Env
{
    private static bool $loaded = false;

    public static function load(string $root): void
    {
        if (self::$loaded) {
            return;
        }
        if (is_file($root . '/.env')) {
            Dotenv::createImmutable($root)->safeLoad();
        }
        self::$loaded = true;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $v = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($v === false || $v === null || $v === '') {
            return $default;
        }
        // strip trailing inline comments such as "false   # note" (only when preceded by whitespace)
        $v = trim((string) preg_replace('/\s+#.*$/', '', (string) $v));
        return $v === '' ? $default : $v;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null) {
            return $default;
        }
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default): int
    {
        $v = self::get($key);
        return ($v !== null && ctype_digit($v)) ? (int) $v : $default;
    }

    public static function isLocal(): bool
    {
        return self::get('APP_ENV', 'production') === 'local';
    }
}
