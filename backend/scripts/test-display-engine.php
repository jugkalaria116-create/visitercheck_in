<?php
declare(strict_types=1);

/**
 * Integration test of the display engine against a THROWAWAY database (dropped at the end).
 *   php scripts/test-display-engine.php
 */
$_ENV['MONGODB_DATABASE'] = 'vm_test_' . bin2hex(random_bytes(3));
require __DIR__ . '/bootstrap.php';

use App\Config\Database;
use App\Models\{GalleryModel, VisitorModel};
use App\Services\{DisplayService, SettingsService, Util};
use App\Validators\PhoneValidator;

$pass = 0;
$fail = 0;
$check = static function (string $label, bool $cond) use (&$pass, &$fail): void {
    $cond ? $pass++ : $fail++;
    echo ($cond ? '  PASS  ' : '  FAIL  ') . $label . "\n";
};
$mkVisitor = static function (string $name, int $ttlMs = 300000): array {
    $now = Util::nowMs();
    $doc = ['name' => $name, 'phone' => '+919876543210', 'photo_path' => 'visitors/' . str_repeat('a', 32) . '.jpg',
        'created_at' => Util::fromMs($now), 'expires_at' => Util::fromMs($now + $ttlMs), 'status' => 'active',
        'consent_at' => Util::fromMs($now), 'retention_expires_at' => null];
    $doc['_id'] = VisitorModel::col()->insertOne($doc)->getInsertedId();
    return $doc;
};
$activate = static fn(array $v) => DisplayService::activateVisitor($v['_id'], $v['created_at'], $v['expires_at']);

echo "Using database " . $_ENV['MONGODB_DATABASE'] . "\n\n== Phone validation\n";
$check('IN local 98765 43210', PhoneValidator::normalize('98765 43210', 'IN') === '+919876543210');
$check('IN +91 prefix', PhoneValidator::normalize('+91-98765-43210', 'IN') === '+919876543210');
$check('IN trunk 0', PhoneValidator::normalize('09876543210', 'IN') === '+919876543210');
$check('IN rejects 5xxxxxxxxx', PhoneValidator::normalize('5123456789', 'IN') === null);
$check('IN rejects letters', PhoneValidator::normalize('98765abcde', 'IN') === null);
$check('ANY accepts +4420...', PhoneValidator::normalize('+442071234567', 'ANY') === '+442071234567');

echo "\n== Empty gallery → branded fallback\n";
$s = DisplayService::current();
$check('mode is branded when no gallery images', $s['mode'] === 'branded' && $s['image_url'] === null);

echo "\n== Gallery rotation\n";
foreach (['one', 'two'] as $t) {
    GalleryModel::col()->insertOne(['title' => $t, 'image_path' => 'gallery/' . str_repeat('b', 32) . '.jpg',
        'mime_type' => 'image/jpeg', 'enabled' => true, 'created_at' => Util::now()]);
}
$s = DisplayService::current();
$check('random gallery image selected', $s['mode'] === 'random_image' && $s['image_url'] !== null);
$check('rotation time scheduled (2+ images)', $s['expires_at'] !== null);

echo "\n== Visitor takes over, newest replaces previous\n";
$a = $mkVisitor('Alice');
$activate($a);
$s1 = DisplayService::current();
$check('Alice displayed', $s1['mode'] === 'visitor' && $s1['visitor_name'] === 'Alice');
$b = $mkVisitor('Bob');
$activate($b);
$s2 = DisplayService::current();
$check('Bob replaces Alice immediately', $s2['visitor_name'] === 'Bob');
$check('version increased', $s2['version'] > $s1['version']);
$check('Alice marked completed', VisitorModel::find($a['_id'])['status'] === 'completed');
$check('same state on repeat read (refresh does not reset timer)', DisplayService::current()['expires_at'] === $s2['expires_at']);

echo "\n== Server-side expiry\n";
DisplayService::col()->updateOne(['_id' => 'main_display'], ['$set' => ['expires_at' => Util::fromMs(Util::nowMs() - 1000)]]);
$s3 = DisplayService::current();
$check('expired visitor → gallery image', $s3['mode'] === 'random_image' && $s3['visitor_name'] === null);
$check('Bob marked completed', VisitorModel::find($b['_id'])['status'] === 'completed');

echo "\n== Race: stale expiry must not overwrite a newer visitor\n";
$c = $mkVisitor('Carol', 1000);
$activate($c);
DisplayService::col()->updateOne(['_id' => 'main_display'], ['$set' => ['expires_at' => Util::fromMs(Util::nowMs() - 1)]]);
$stale = DisplayService::col()->findOne(['_id' => 'main_display']);        // a request reads "Carol, expired"…
$d = $mkVisitor('Dave');
$activate($d);                                                              // …Dave registers meanwhile…
DisplayService::reconcile($stale, Util::nowMs());                           // …stale request tries to expire Carol
$check('Dave still on screen', DisplayService::current()['visitor_name'] === 'Dave');
$check('Dave still active', VisitorModel::find($d['_id'])['status'] === 'active');

echo "\n== Admin deletes the displayed visitor\n";
VisitorModel::col()->deleteOne(['_id' => $d['_id']]);
$check('falls back to gallery', DisplayService::current()['mode'] === 'random_image');

echo "\n== Disabling all images\n";
GalleryModel::col()->updateMany([], ['$set' => ['enabled' => false]]);
$check('branded screen when nothing enabled', DisplayService::current()['mode'] === 'branded');

echo "\n== Settings\n";
SettingsService::update(['display_duration_seconds' => 120]);
$check('duration persisted', SettingsService::get()['display_duration_seconds'] === 120);

Database::db()->drop();
echo "\nTemporary database dropped.\n";
echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
