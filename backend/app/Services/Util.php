<?php
declare(strict_types=1);

namespace App\Services;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

final class Util
{
    public static function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    public static function now(): UTCDateTime
    {
        return new UTCDateTime(self::nowMs());
    }

    public static function fromMs(int $ms): UTCDateTime
    {
        return new UTCDateTime($ms);
    }

    public static function ms(mixed $d): ?int
    {
        return $d instanceof UTCDateTime ? (int) (string) $d : null;
    }

    public static function iso(mixed $d): ?string
    {
        return $d instanceof UTCDateTime ? $d->toDateTime()->format('Y-m-d\TH:i:s.v\Z') : null;
    }

    public static function oid(mixed $s): ?ObjectId
    {
        if (!is_string($s) || !preg_match('/^[a-f0-9]{24}$/i', $s)) {
            return null;
        }
        try {
            return new ObjectId($s);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function escapeRegex(string $s): string
    {
        return preg_quote($s, '/');
    }

    public static function isDuplicateKey(\Throwable $e): bool
    {
        return str_contains($e->getMessage(), 'E11000') || $e->getCode() === 11000;
    }
}
