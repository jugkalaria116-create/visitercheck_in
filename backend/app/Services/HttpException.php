<?php
declare(strict_types=1);

namespace App\Services;

final class HttpException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly array $errors = [],
        public readonly ?string $errorCode = null
    ) {
        parent::__construct($message);
    }
}
