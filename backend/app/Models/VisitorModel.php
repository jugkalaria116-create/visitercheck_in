<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use App\Services\Util;
use MongoDB\BSON\ObjectId;
use MongoDB\Collection;

final class VisitorModel
{
    public static function col(): Collection
    {
        return Database::col('visitors');
    }

    public static function find(ObjectId $id): ?array
    {
        return self::col()->findOne(['_id' => $id]) ?: null;
    }

    /** Full record for administrators (includes phone). */
    public static function adminView(array $v, ?string $displayedVisitorId): array
    {
        $id = (string) $v['_id'];
        return [
            'id' => $id,
            'name' => $v['name'] ?? '',
            'phone' => $v['phone'] ?? '',
            'photo_url' => '/api/media/visitor/' . $id,
            'status' => $v['status'] ?? 'completed',
            'display_status' => ($displayedVisitorId === $id) ? 'on_display' : 'shown',
            'created_at' => Util::iso($v['created_at'] ?? null),
            'expires_at' => Util::iso($v['expires_at'] ?? null),
            'consent_at' => Util::iso($v['consent_at'] ?? null),
            'retention_expires_at' => Util::iso($v['retention_expires_at'] ?? null),
        ];
    }
}
