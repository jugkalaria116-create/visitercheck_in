<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use App\Services\Util;
use MongoDB\BSON\ObjectId;
use MongoDB\Collection;

final class GalleryModel
{
    public static function col(): Collection
    {
        return Database::col('gallery_images');
    }

    public static function find(ObjectId $id): ?array
    {
        return self::col()->findOne(['_id' => $id]) ?: null;
    }

    public static function view(array $g): array
    {
        $id = (string) $g['_id'];
        return [
            'id' => $id,
            'title' => $g['title'] ?? '',
            'enabled' => (bool) ($g['enabled'] ?? false),
            'mime_type' => $g['mime_type'] ?? '',
            'url' => '/api/media/gallery/' . $id,
            'created_at' => Util::iso($g['created_at'] ?? null),
        ];
    }
}
