<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Models\AdminModel;
use App\Services\HttpException;
use App\Services\Request;
use App\Services\Session;
use App\Services\Util;

final class AuthMiddleware
{
    /** Active administrator for the current session, or null. Releases the session lock. */
    public static function currentAdmin(): ?array
    {
        $id = Session::adminId();
        Session::release();   // $_SESSION stays readable; avoids serialising parallel requests
        $oid = Util::oid($id);
        if ($oid === null) {
            return null;
        }
        return AdminModel::col()->findOne(['_id' => $oid, 'active' => true]) ?: null;
    }

    public static function handle(Request $r): void
    {
        $admin = self::currentAdmin();
        if ($admin === null) {
            throw new HttpException(401, 'Please sign in to continue.', [], 'unauthenticated');
        }
        $r->admin = $admin;
    }
}
