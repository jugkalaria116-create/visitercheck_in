<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Env;

final class Response
{
    public static function securityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; "
            . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
            . "font-src 'self' https://fonts.gstatic.com data:; "
            . "img-src 'self' data: blob:; media-src 'self' blob: https://d8j0ntlcm91z4.cloudfront.net; "
            . "connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
        if (Env::bool('SESSION_SECURE_COOKIE', false)) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        header_remove('X-Powered-By');
    }

    public static function noStore(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        self::noStore();
        if ($status === 204) {
            exit;
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        exit;
    }

    public static function error(HttpException $e): never
    {
        self::json([
            'error' => [
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
                'fields' => (object) $e->errors,
            ],
        ], $e->status);
    }
}
