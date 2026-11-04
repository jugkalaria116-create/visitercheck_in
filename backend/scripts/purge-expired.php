<?php
declare(strict_types=1);

/** Deletes visitor records and photo files whose retention period has ended. Schedule daily (cron / Windows Task Scheduler). */
require __DIR__ . '/bootstrap.php';

$n = \App\Services\RetentionService::purgeExpired(5000);
echo "Purged $n expired visitor record(s) and their photo files.\n";
