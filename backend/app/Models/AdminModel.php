<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use MongoDB\Collection;

final class AdminModel
{
    public static function col(): Collection
    {
        return Database::col('admins');
    }

    public static function publicView(array $a): array
    {
        return ['id' => (string) $a['_id'], 'username' => $a['username'], 'role' => $a['role'] ?? 'admin'];
    }
}
