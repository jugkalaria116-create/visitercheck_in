<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\AdminModel;
use App\Services\{HttpException, RateLimiter, Request, Response, Session, Util};
use MongoDB\Operation\FindOneAndUpdate;

final class AuthController
{
    private const MAX_FAILURES = 5;
    private const LOCK_SECONDS = 900;

    /** GET /api/admin/me — also hands out the CSRF token (needed before login). */
    public function me(Request $r): void
    {
        $csrf = Session::csrf();
        $admin = AuthMiddleware::currentAdmin();
        Response::json(['data' => [
            'authenticated' => $admin !== null,
            'admin' => $admin ? AdminModel::publicView($admin) : null,
            'csrf_token' => $csrf,
        ]]);
    }

    public function login(Request $r): void
    {
        RateLimiter::hit('login', 15, 900, $r->ip());
        $b = $r->body();
        $username = strtolower(trim((string) ($b['username'] ?? '')));
        $password = (string) ($b['password'] ?? '');
        if ($username === '' || $password === '' || strlen($password) > 256) {
            throw new HttpException(422, 'Enter your username and password.', [], 'validation_failed');
        }

        $admin = AdminModel::col()->findOne(['username' => $username]);
        // Always run a hash verification so timing does not reveal whether the account exists.
        $ok = password_verify($password, $admin['password_hash'] ?? password_hash('invalid-' . random_int(1, PHP_INT_MAX), PASSWORD_DEFAULT));

        if ($admin && ($lock = Util::ms($admin['locked_until'] ?? null)) !== null && $lock > Util::nowMs()) {
            throw new HttpException(429, 'This account is temporarily locked. Try again in a few minutes.', [], 'account_locked');
        }
        if (!$admin || !($admin['active'] ?? false) || !$ok) {
            if ($admin) {
                $after = AdminModel::col()->findOneAndUpdate(['_id' => $admin['_id']], ['$inc' => ['failed_attempts' => 1]],
                    ['returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER]);
                if (($after['failed_attempts'] ?? 0) >= self::MAX_FAILURES) {
                    AdminModel::col()->updateOne(['_id' => $admin['_id']], ['$set' => [
                        'failed_attempts' => 0, 'locked_until' => Util::fromMs(Util::nowMs() + self::LOCK_SECONDS * 1000),
                    ]]);
                }
            }
            throw new HttpException(401, 'Invalid username or password.', [], 'invalid_credentials');
        }

        $set = ['last_login_at' => Util::now(), 'failed_attempts' => 0];
        if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
            $set['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        AdminModel::col()->updateOne(['_id' => $admin['_id']], ['$set' => $set, '$unset' => ['locked_until' => '']]);

        Session::start();
        session_regenerate_id(true);               // prevent session fixation
        $_SESSION['admin_id'] = (string) $admin['_id'];
        $csrf = Session::rotateCsrf();
        Response::json(['data' => ['admin' => AdminModel::publicView($admin), 'csrf_token' => $csrf]]);
    }

    public function logout(Request $r): void
    {
        Session::destroy();
        Response::json(['data' => ['logged_out' => true]]);
    }
}
