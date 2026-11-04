<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Services\{Request, Response, Util};

final class HealthController
{
    public function show(Request $r): void
    {
        $up = Database::ping();
        Response::json(['status' => $up ? 'ok' : 'degraded', 'database' => $up ? 'up' : 'down', 'time' => Util::iso(Util::now())], $up ? 200 : 503);
    }
}
