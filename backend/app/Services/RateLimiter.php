<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Operation\FindOneAndUpdate;

/** Fixed-window rate limiter stored in MongoDB (works across PHP processes). */
final class RateLimiter
{
    public static function hit(string $name, int $limit, int $windowSeconds, string $ip): void
    {
        $bucket = intdiv(time(), $windowSeconds);
        $key = $name . ':' . $ip . ':' . $bucket;
        $doc = null;
        for ($i = 0; $i < 2 && $doc === null; $i++) {
            try {
                $doc = Database::col('rate_limits')->findOneAndUpdate(
                    ['key' => $key],
                    [
                        '$inc' => ['count' => 1],
                        '$setOnInsert' => ['expire_at' => new UTCDateTime(($bucket + 2) * $windowSeconds * 1000)],
                    ],
                    ['upsert' => true, 'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
                );
            } catch (\Throwable $e) {
                if (!Util::isDuplicateKey($e)) {
                    throw $e;
                }
            }
        }
        if (($doc['count'] ?? 0) > $limit) {
            $retry = max(1, ($bucket + 1) * $windowSeconds - time());
            header('Retry-After: ' . $retry);
            throw new HttpException(429, 'Too many requests. Please wait a moment and try again.', [], 'rate_limited');
        }
    }
}
