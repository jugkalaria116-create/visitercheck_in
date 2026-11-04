<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use App\Services\Util;
use MongoDB\Collection;

final class DisplayClientModel
{
    public const ONLINE_SECONDS = 30;     // heartbeat is sent every 10 s
    public const RECENT_SECONDS = 600;    // "recently disconnected" window

    public static function col(): Collection
    {
        return Database::col('display_clients');
    }

    public static function view(array $c, int $nowMs): array
    {
        $seen = Util::ms($c['last_seen_at'] ?? null);
        $age = $seen === null ? null : (int) max(0, floor(($nowMs - $seen) / 1000));
        $status = 'offline';
        if ($age !== null && $age <= self::ONLINE_SECONDS) {
            $status = 'online';
        } elseif ($age !== null && $age <= self::RECENT_SECONDS) {
            $status = 'recently_disconnected';
        }
        return [
            'id' => (string) $c['_id'],
            'display_name' => $c['display_name'] ?? 'Display',
            'status' => $status,
            'last_seen_at' => Util::iso($c['last_seen_at'] ?? null),
            'seconds_since_seen' => $age,
            'created_at' => Util::iso($c['created_at'] ?? null),
            'current_version' => $c['current_version'] ?? null,
            'current_mode' => $c['current_mode'] ?? null,
        ];
    }
}
