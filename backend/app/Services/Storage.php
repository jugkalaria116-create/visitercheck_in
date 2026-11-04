<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Env;

/** Filesystem storage kept outside the web root. Only generated names are ever accepted. */
final class Storage
{
    public static function root(): string
    {
        $p = Env::get('STORAGE_PATH') ?: dirname(__DIR__, 2) . '/storage';
        return rtrim(str_replace('\\', '/', $p), '/');
    }

    public static function dir(string $sub): string
    {
        $dir = self::root() . '/' . $sub;
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create storage directory.');
        }
        return $dir;
    }

    /** Resolve a stored relative path ("visitors/<32 hex>.jpg") to an absolute path, or null. */
    public static function resolve(?string $rel): ?string
    {
        if ($rel === null || !preg_match('#^(visitors|gallery)/[a-f0-9]{32}\.(jpg|png|webp)$#', $rel)) {
            return null;
        }
        $full = self::root() . '/' . $rel;
        return is_file($full) ? $full : null;
    }

    public static function delete(?string $rel): void
    {
        $f = self::resolve($rel);
        if ($f !== null) {
            @unlink($f);
        }
    }
}
