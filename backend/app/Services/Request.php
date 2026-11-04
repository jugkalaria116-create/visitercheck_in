<?php
declare(strict_types=1);

namespace App\Services;

final class Request
{
    public ?array $admin = null;
    public array $params = [];
    private ?array $body = null;

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = rawurldecode($path);
        // Support deployments in a sub-folder (e.g. /visitors/index.php)
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($base !== '' && str_starts_with($path, $base . '/')) {
            $path = substr($path, strlen($base));
        }
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php')) ?: '/';
        }
        $path = '/' . trim($path, '/');
        return new self($method, $path, $_GET);
    }

    public function body(): array
    {
        if ($this->body !== null) {
            return $this->body;
        }
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($ct, 'application/json')) {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw === false ? '' : $raw, true);
            if ($raw !== '' && !is_array($data)) {
                throw new HttpException(400, 'Request body is not valid JSON.');
            }
            return $this->body = $data ?? [];
        }
        return $this->body = $_POST;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }

    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
