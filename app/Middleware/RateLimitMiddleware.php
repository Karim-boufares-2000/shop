<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Services\RateLimiterInterface;

/** Limite le nombre de requêtes par IP et par "bucket" (ex. login, checkout, tracking). */
final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RateLimiterInterface $limiter,
        private string $bucket,
        private int $maxAttempts,
        private int $windowSeconds,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!$this->limiter->attempt($this->bucket . '|' . $request->ip(), $this->maxAttempts, $this->windowSeconds)) {
            throw new HttpException(429, 'rate_limited', ['Retry-After' => (string) $this->windowSeconds]);
        }

        return $next($request);
    }
}
