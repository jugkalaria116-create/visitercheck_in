<?php
declare(strict_types=1);

/** Verifies the MongoDB connection, creates indexes and seeds `settings` + `display_state`. Safe to re-run. */
require __DIR__ . '/bootstrap.php';

use App\Config\{Database, Env};
use App\Services\{DisplayService, SettingsService, Util};

echo "Connecting to " . Env::get('MONGODB_DATABASE', 'visitor_management') . " ...\n";
if (!Database::ping()) {
    fwrite(STDERR, "FAILED: cannot reach MongoDB. Check MONGODB_URI in backend/.env and that mongod is running.\n");
    exit(1);
}
echo "  connection OK\n";

$indexes = [
    'admins' => [
        [['username' => 1], ['unique' => true, 'name' => 'uniq_username']],
    ],
    'visitors' => [
        [['created_at' => -1], ['name' => 'created_desc']],
        [['status' => 1, 'created_at' => -1], ['name' => 'status_created']],
        [['submission_id' => 1], ['unique' => true, 'name' => 'uniq_submission',
            'partialFilterExpression' => ['submission_id' => ['$type' => 'string']]]],
        [['retention_expires_at' => 1], ['name' => 'retention']],
    ],
    'gallery_images' => [
        [['enabled' => 1, 'created_at' => -1], ['name' => 'enabled_created']],
    ],
    'display_clients' => [
        [['last_seen_at' => -1], ['name' => 'last_seen_desc']],
        [['client_token_hash' => 1], ['unique' => true, 'name' => 'uniq_client_token']],
    ],
    'rate_limits' => [
        [['key' => 1], ['unique' => true, 'name' => 'uniq_key']],
        [['expire_at' => 1], ['expireAfterSeconds' => 0, 'name' => 'ttl_expire']],
    ],
];
foreach ($indexes as $collection => $list) {
    foreach ($list as [$keys, $opts]) {
        $name = Database::col($collection)->createIndex($keys, $opts);
        echo "  index {$collection}.{$name}\n";
    }
}

DisplayService::ensure();
echo "  display_state ready (main_display)\n";

$defaults = SettingsService::defaults();
$defaults['updated_at'] = Util::now();
Database::col('settings')->updateOne(['_id' => 'display_settings'], ['$setOnInsert' => $defaults], ['upsert' => true]);
echo "  settings ready (display_settings)\n";
echo "Done. Next: php scripts/create-admin.php\n";
