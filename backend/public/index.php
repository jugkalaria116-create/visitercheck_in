<?php
declare(strict_types=1);

/**
 * Front controller.
 *  - /api/*  -> JSON REST API
 *  - anything else -> compiled React app (public/index.html), so client-side routes work on refresh
 */

$publicDir = __DIR__;
$root = dirname(__DIR__);

// PHP built-in server (php -S): let it serve real static files (built JS/CSS/assets) itself.
if (PHP_SAPI === 'cli-server') {
    $p = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $f = realpath($publicDir . $p);
    if ($p !== '/' && $f !== false && is_file($f) && str_starts_with($f, $publicDir) && !str_ends_with($f, '.php')) {
        return false;
    }
}

if (!is_file($root . '/vendor/autoload.php')) {
    http_response_code(500);
    header('Content-Type: text/plain');
    exit("Dependencies missing. Run `composer install` inside the backend folder.\n");
}
require $root . '/vendor/autoload.php';

use App\Config\Env;
use App\Routes\Router;
use App\Services\{HttpException, Request, Response};

Env::load($root);
date_default_timezone_set(Env::get('APP_TIMEZONE', 'UTC') ?: 'UTC');
ini_set('display_errors', '0');
error_reporting(E_ALL);
@ini_set('memory_limit', '512M');

Response::securityHeaders();
$request = Request::capture();

// ---- SPA ----
if (!str_starts_with($request->path, '/api/') && $request->path !== '/api') {
    $index = $publicDir . '/index.html';
    if (is_file($index)) {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-cache');
        readfile($index);
    } else {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Frontend is not built yet. Run `npm install && npm run build` inside the frontend folder.\n";
    }
    exit;
}

// ---- API ----
try {
    $router = new Router();
    (require $root . '/app/Routes/api.php')($router);
    $router->dispatch($request);
} catch (HttpException $e) {
    Response::error($e);
} catch (\MongoDB\Driver\Exception\ConnectionException | \MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
    error_log('MongoDB unavailable: ' . $e->getMessage());
    Response::error(new HttpException(503, 'The database is temporarily unavailable.', [], 'database_unavailable'));
} catch (\Throwable $e) {
    error_log(sprintf('Unhandled %s: %s in %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
    $msg = Env::isLocal() ? $e->getMessage() : 'Something went wrong on the server.';
    Response::error(new HttpException(500, $msg, [], 'server_error'));
}
