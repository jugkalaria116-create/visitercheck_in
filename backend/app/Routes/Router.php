<?php
declare(strict_types=1);

namespace App\Routes;

use App\Services\HttpException;
use App\Services\Request;

final class Router
{
    /** @var array<int,array{0:string,1:string,2:array,3:array}> */
    private array $routes = [];

    public function add(string $method, string $pattern, array $handler, array $middleware = []): void
    {
        $regex = '#^' . preg_replace('#:([a-z_]+)#', '(?P<$1>[^/]+)', $pattern) . '$#';
        $this->routes[] = [$method, $regex, $handler, $middleware];
    }

    public function get(string $p, array $h, array $m = []): void { $this->add('GET', $p, $h, $m); }
    public function post(string $p, array $h, array $m = []): void { $this->add('POST', $p, $h, $m); }
    public function patch(string $p, array $h, array $m = []): void { $this->add('PATCH', $p, $h, $m); }
    public function delete(string $p, array $h, array $m = []): void { $this->add('DELETE', $p, $h, $m); }

    public function dispatch(Request $r): void
    {
        $pathMatched = false;
        foreach ($this->routes as [$method, $regex, $handler, $middleware]) {
            if (!preg_match($regex, $r->path, $m)) {
                continue;
            }
            $pathMatched = true;
            $reqMethod = $r->method === 'HEAD' ? 'GET' : $r->method;
            if ($method !== $reqMethod) {
                continue;
            }
            $r->params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            foreach ($middleware as $mw) {
                $mw::handle($r);
            }
            [$class, $fn] = $handler;
            (new $class())->$fn($r);
            return;
        }
        throw $pathMatched
            ? new HttpException(405, 'Method not allowed.', [], 'method_not_allowed')
            : new HttpException(404, 'Endpoint not found.', [], 'not_found');
    }
}
