<?php
declare(strict_types=1);

/**
 * Creates (or resets the password of) an administrator. There is deliberately no public admin registration.
 *   php scripts/create-admin.php
 *   php scripts/create-admin.php --username=admin --password="a-long-passphrase" [--reset]
 */
require __DIR__ . '/bootstrap.php';

use App\Models\AdminModel;
use App\Services\Util;

$opts = getopt('', ['username::', 'password::', 'reset']);
$ask = static function (string $label, bool $hidden = false): string {
    echo $label;
    if ($hidden && stripos(PHP_OS, 'WIN') !== 0) {
        system('stty -echo');
        $v = trim((string) fgets(STDIN));
        system('stty echo');
        echo "\n";
        return $v;
    }
    return trim((string) fgets(STDIN));
};

$username = strtolower((string) ($opts['username'] ?? $ask('Username: ')));
if (!preg_match('/^[a-z0-9._-]{3,32}$/', $username)) {
    fwrite(STDERR, "Username must be 3-32 characters: a-z, 0-9, dot, dash, underscore.\n");
    exit(1);
}
if (stripos(PHP_OS, 'WIN') === 0 && !isset($opts['password'])) {
    echo "(Note: on Windows the password is visible while typing.)\n";
}
$password = (string) ($opts['password'] ?? $ask('Password (min 12 chars): ', true));
if (strlen($password) < 12) {
    fwrite(STDERR, "Password must be at least 12 characters.\n");
    exit(1);
}

$existing = AdminModel::col()->findOne(['username' => $username]);
if ($existing && !isset($opts['reset'])) {
    fwrite(STDERR, "User '$username' already exists. Re-run with --reset to change the password.\n");
    exit(1);
}
$hash = password_hash($password, PASSWORD_DEFAULT);
if ($existing) {
    AdminModel::col()->updateOne(['_id' => $existing['_id']], ['$set' => [
        'password_hash' => $hash, 'active' => true, 'failed_attempts' => 0], '$unset' => ['locked_until' => '']]);
    echo "Password updated for '$username'.\n";
} else {
    AdminModel::col()->insertOne([
        'username' => $username, 'password_hash' => $hash, 'role' => 'admin', 'active' => true,
        'created_at' => Util::now(), 'last_login_at' => null,
    ]);
    echo "Administrator '$username' created.\n";
}
