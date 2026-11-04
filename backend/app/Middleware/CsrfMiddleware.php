<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\HttpException;
use App\Services\Request;
use App\Services\Session;

final class CsrfMiddleware
{
    public static function handle(Request $r): void
    {
        if (in_array($r->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }
        Session::start();
        $token = (string) ($_SESSION['csrf'] ?? '');
        $sent = (string) ($r->header('X-CSRF-Token') ?? '');
        if ($token === '' || !hash_equals($token, $sent)) {
            throw new HttpException(403, 'Your session security token is missing or expired. Please retry.', [], 'csrf_invalid');
        }
    }
}
