<?php
declare(strict_types=1);

namespace App\Config;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database as MongoDatabase;

final class Database
{
    private static ?Client $client = null;

    public static function client(): Client
    {
        return self::$client ??= new Client(
            Env::get('MONGODB_URI', 'mongodb://127.0.0.1:27017'),
            ['serverSelectionTimeoutMS' => 5000, 'connectTimeoutMS' => 5000],
            ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]
        );
    }

    public static function db(): MongoDatabase
    {
        return self::client()->selectDatabase(Env::get('MONGODB_DATABASE', 'visitor_management'));
    }

    public static function col(string $name): Collection
    {
        return self::db()->selectCollection($name);
    }

    public static function ping(): bool
    {
        try {
            self::client()->selectDatabase('admin')->command(['ping' => 1]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
