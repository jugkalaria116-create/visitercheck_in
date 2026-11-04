<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Config\Env;

final class SettingsService
{
    private static ?array $cache = null;

    public static function defaults(): array
    {
        $base = rtrim(Env::get('FRONTEND_URL') ?? Env::get('APP_URL', 'http://localhost:8000'), '/');
        return [
            'display_duration_seconds' => 300,
            'polling_interval_seconds' => 2,
            'gallery_rotation_seconds' => 30,
            'fallback_mode' => 'random_image',
            'registration_enabled' => true,
            'registration_url' => $base . '/register',
            'phone_country' => 'IN',
            'visitor_retention_days' => Env::int('VISITOR_RETENTION_DAYS', 90),
        ];
    }

    public static function get(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $defaults = self::defaults();
        $doc = Database::col('settings')->findOne(['_id' => 'display_settings']);
        $out = $defaults;
        if ($doc) {
            foreach ($defaults as $k => $d) {
                if (!isset($doc[$k])) {
                    continue;
                }
                $out[$k] = is_int($d) ? (int) $doc[$k] : (is_bool($d) ? (bool) $doc[$k] : (string) $doc[$k]);
            }
        }
        $out['updated_at'] = Util::iso($doc['updated_at'] ?? null);
        return self::$cache = $out;
    }

    public static function update(array $changes): array
    {
        $changes['updated_at'] = Util::now();
        Database::col('settings')->updateOne(['_id' => 'display_settings'], ['$set' => $changes], ['upsert' => true]);
        self::$cache = null;
        return self::get();
    }

    public static function reset(): void
    {
        self::$cache = null;
    }

    /** Only what anonymous visitors/TVs may know. */
    public static function publicView(): array
    {
        $s = self::get();
        return [
            'registration_enabled' => $s['registration_enabled'],
            'phone_country' => $s['phone_country'],
            'polling_interval_seconds' => $s['polling_interval_seconds'],
            'max_upload_bytes' => Env::int('UPLOAD_MAX_SIZE', 8388608),
            'display_duration_seconds' => $s['display_duration_seconds'],
        ];
    }
}
