<?php

declare(strict_types=1);

namespace App\Services;

interface RateLimiterInterface
{
    /** @return bool true si la tentative est autorisée, false si la limite est dépassée */
    public function attempt(string $key, int $maxAttempts, int $windowSeconds): bool;

    public function clear(string $key): void;
}
