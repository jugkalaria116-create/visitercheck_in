<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Models\GalleryModel;
use App\Models\VisitorModel;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Operation\FindOneAndUpdate;

/**
 * Single source of truth for what every TV shows: the `display_state` document.
 *
 * Concurrency model
 *  - A new visitor registration is an unconditional atomic update (newest committed registration wins).
 *  - Server-driven transitions (expiry, gallery rotation) are compare-and-set on `version`, so a stale
 *    expiry can never overwrite a visitor that registered in the meantime.
 */
final class DisplayService
{
    public const ID = 'main_display';

    public static function col(): Collection
    {
        return Database::col('display_state');
    }

    public static function ensure(): void
    {
        $now = Util::now();
        try {
            self::col()->updateOne(
                ['_id' => self::ID],
                ['$setOnInsert' => [
                    'mode' => 'random_image', 'visitor_id' => null, 'image_id' => null,
                    'started_at' => $now, 'expires_at' => null, 'version' => 1, 'updated_at' => $now,
                ]],
                ['upsert' => true]
            );
        } catch (\MongoDB\Driver\Exception\Exception) {
            // Lost an upsert race with another request; the document exists now.
        }
    }

    /** Authoritative, reconciled state ready to be sent to TVs. */
    public static function current(): array
    {
        self::ensure();
        $state = null;
        for ($i = 0; $i < 4; $i++) {
            $state = self::col()->findOne(['_id' => self::ID]);
            if (!$state || !self::reconcile($state, Util::nowMs())) {
                break;
            }
        }
        $state = self::col()->findOne(['_id' => self::ID]);
        return self::present($state);
    }

    /**
     * Apply a server-side transition if one is due.
     * @return bool true when a transition was attempted (caller should re-read the state)
     */
    public static function reconcile(array $s, int $nowMs): bool
    {
        $settings = SettingsService::get();

        if (($s['mode'] ?? '') === 'visitor') {
            $vid = $s['visitor_id'] ?? null;
            $expires = Util::ms($s['expires_at'] ?? null);
            $alive = $vid instanceof ObjectId
                && VisitorModel::col()->countDocuments(['_id' => $vid, 'status' => 'active'], ['limit' => 1]) > 0;
            if (!$alive || ($expires !== null && $expires <= $nowMs)) {
                self::toFallback($s, $settings, $nowMs);
                return true;
            }
            return false;
        }

        // random_image mode
        $enabled = GalleryModel::col()->countDocuments(['enabled' => true]);
        if ($settings['fallback_mode'] === 'branded' || $enabled === 0) {
            if (($s['image_id'] ?? null) !== null || ($s['expires_at'] ?? null) !== null) {
                self::cas($s, ['mode' => 'random_image', 'visitor_id' => null, 'image_id' => null,
                    'started_at' => Util::fromMs($nowMs), 'expires_at' => null]);
                return true;
            }
            return false;
        }
        $imageId = $s['image_id'] ?? null;
        $imageOk = $imageId instanceof ObjectId
            && GalleryModel::col()->countDocuments(['_id' => $imageId, 'enabled' => true], ['limit' => 1]) > 0;
        $expires = Util::ms($s['expires_at'] ?? null);
        $due = $expires !== null && $expires <= $nowMs;
        if (!$imageOk || $due || ($expires === null && $enabled > 1)) {
            self::toFallback($s, $settings, $nowMs);
            return true;
        }
        return false;
    }

    /** Move to (or rotate within) the fallback mode using compare-and-set on the observed version. */
    private static function toFallback(array $s, array $settings, int $nowMs): void
    {
        $prevImage = ($s['image_id'] ?? null) instanceof ObjectId ? $s['image_id'] : null;
        $image = $settings['fallback_mode'] === 'branded' ? null : self::pickImage($prevImage);
        $enabled = $image ? GalleryModel::col()->countDocuments(['enabled' => true]) : 0;
        $next = ($image && $enabled > 1) ? Util::fromMs($nowMs + $settings['gallery_rotation_seconds'] * 1000) : null;

        $won = self::cas($s, [
            'mode' => 'random_image', 'visitor_id' => null, 'image_id' => $image ? $image['_id'] : null,
            'started_at' => Util::fromMs($nowMs), 'expires_at' => $next,
        ]);
        if ($won && ($s['mode'] ?? '') === 'visitor' && ($s['visitor_id'] ?? null) instanceof ObjectId) {
            VisitorModel::col()->updateOne(['_id' => $s['visitor_id'], 'status' => 'active'], ['$set' => ['status' => 'completed']]);
        }
    }

    private static function cas(array $s, array $set): bool
    {
        $set['updated_at'] = Util::now();
        $res = self::col()->findOneAndUpdate(
            ['_id' => self::ID, 'version' => $s['version']],
            ['$set' => $set, '$inc' => ['version' => 1]],
            ['returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
        );
        return $res !== null;
    }

    public static function pickImage(?ObjectId $exclude = null): ?array
    {
        $run = static function (?ObjectId $ex): ?array {
            $match = ['enabled' => true];
            if ($ex !== null) {
                $match['_id'] = ['$ne' => $ex];
            }
            foreach (GalleryModel::col()->aggregate([['$match' => $match], ['$sample' => ['size' => 1]]]) as $doc) {
                return $doc;
            }
            return null;
        };
        return $run($exclude) ?? ($exclude !== null ? $run(null) : null);
    }

    /** A newly registered visitor immediately replaces whatever is on screen. */
    public static function activateVisitor(ObjectId $visitorId, \MongoDB\BSON\UTCDateTime $startedAt, \MongoDB\BSON\UTCDateTime $expiresAt): void
    {
        self::ensure();
        $before = self::col()->findOneAndUpdate(
            ['_id' => self::ID],
            [
                '$set' => [
                    'mode' => 'visitor', 'visitor_id' => $visitorId, 'image_id' => null,
                    'started_at' => $startedAt, 'expires_at' => $expiresAt, 'updated_at' => Util::now(),
                ],
                '$inc' => ['version' => 1],
            ],
            ['returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_BEFORE, 'upsert' => true]
        );
        $prev = $before['visitor_id'] ?? null;
        if ($prev instanceof ObjectId && (string) $prev !== (string) $visitorId) {
            VisitorModel::col()->updateOne(['_id' => $prev, 'status' => 'active'], ['$set' => ['status' => 'completed']]);
        }
    }

    /** Explicit admin choice: re-base the running visitor's timer on a new duration. */
    public static function applyDurationToCurrent(int $seconds): void
    {
        self::ensure();
        $s = self::col()->findOne(['_id' => self::ID]);
        if (!$s || ($s['mode'] ?? '') !== 'visitor') {
            return;
        }
        $started = Util::ms($s['started_at'] ?? null) ?? Util::nowMs();
        self::cas($s, ['expires_at' => Util::fromMs($started + $seconds * 1000)]);
    }

    public static function present(array $s): array
    {
        $settings = SettingsService::get();
        $version = (int) ($s['version'] ?? 0);
        $out = [
            'mode' => 'branded',
            'version' => $version,
            'visitor_name' => null,
            'image_url' => null,
            'expires_at' => Util::iso($s['expires_at'] ?? null),
            'started_at' => Util::iso($s['started_at'] ?? null),
            'server_time' => Util::iso(Util::now()),
            'polling_interval_seconds' => $settings['polling_interval_seconds'],
        ];
        if (($s['mode'] ?? '') === 'visitor' && ($s['visitor_id'] ?? null) instanceof ObjectId) {
            $v = VisitorModel::find($s['visitor_id']);
            if ($v) {
                $out['mode'] = 'visitor';
                $out['visitor_name'] = $v['name'];
                $out['image_url'] = '/api/media/visitor/' . $s['visitor_id'] . '?v=' . $version;
            }
        } elseif (($s['image_id'] ?? null) instanceof ObjectId) {
            $out['mode'] = 'random_image';
            $out['image_url'] = '/api/media/gallery/' . $s['image_id'] . '?v=' . $version;
        }
        return $out;
    }
}
