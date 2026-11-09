<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\RateLimiterInterface;

final class ArrayRateLimiter implements RateLimiterInterface
{
    /** @var array<string,int> */
    public array $hits = [];
    public bool $denyAll = false;

    public function attempt(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $this->hits[$key] = ($this->hits[$key] ?? 0) + 1;

        return !$this->denyAll && $this->hits[$key] <= $maxAttempts;
    }

    public function clear(string $key): void
    {
        unset($this->hits[$key]);
    }
}
