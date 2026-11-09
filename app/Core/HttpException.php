<?php

declare(strict_types=1);

namespace App\Core;

/** Erreur HTTP "attendue" (404, 403, 429...) : convertie en page d'erreur par ErrorHandler. */
final class HttpException extends \RuntimeException
{
    /** @param array<string,string> $headers */
    public function __construct(
        private int $statusCode,
        string $message = '',
        private array $headers = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }
}
