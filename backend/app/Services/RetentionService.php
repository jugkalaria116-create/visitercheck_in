<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Models\VisitorModel;
use MongoDB\BSON\ObjectId;

/**
 * Deletes visitor records AND their photo files once `retention_expires_at` has passed.
 * Runs lazily (at most once per hour, claimed atomically) and via scripts/purge-expired.php.
 */
final class RetentionService
{
    public static function purgeExpired(int $limit = 500): int
    {
        $current = DisplayService::col()->findOne(['_id' => DisplayService::ID]);
        $filter = ['retention_expires_at' => ['$lte' => Util::now()]];
        if (($current['visitor_id'] ?? null) instanceof ObjectId) {
            $filter['_id'] = ['$ne' => $current['visitor_id']];   // never delete the visitor on screen
        }
        $ids = [];
        foreach (VisitorModel::col()->find($filter, ['limit' => $limit, 'projection' => ['photo_path' => 1]]) as $v) {
            Storage::delete($v['photo_path'] ?? null);
            $ids[] = $v['_id'];
        }
        if ($ids) {
            VisitorModel::col()->deleteMany(['_id' => ['$in' => $ids]]);
        }
        return count($ids);
    }

    public static function maybePurge(): void
    {
        $now = Util::nowMs();
        $claimed = Database::col('settings')->updateOne(
            ['_id' => 'display_settings', '$or' => [
                ['last_purge_at' => ['$lt' => Util::fromMs($now - 3600_000)]],
                ['last_purge_at' => ['$exists' => false]],
            ]],
            ['$set' => ['last_purge_at' => Util::fromMs($now)]]
        );
        if ($claimed->getModifiedCount() === 1) {
            self::purgeExpired();
        }
    }
}
