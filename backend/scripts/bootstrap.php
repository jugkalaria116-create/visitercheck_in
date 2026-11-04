<?php
declare(strict_types=1);

// Shared bootstrap for CLI scripts.
if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}
$root = dirname(__DIR__);
if (!is_file($root . '/vendor/autoload.php')) {
    fwrite(STDERR, "Run `composer install` in the backend folder first.\n");
    exit(1);
}
require $root . '/vendor/autoload.php';
\App\Config\Env::load($root);
date_default_timezone_set(\App\Config\Env::get('APP_TIMEZONE', 'UTC') ?: 'UTC');
