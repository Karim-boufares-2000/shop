<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\SessionInterface;

final class ArraySession implements SessionInterface
{
    /** @var array<string,mixed> */
    private array $data = [];
    public int $regenerations = 0;

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->data[$key] ?? $default;
        unset($this->data[$key]);

        return $value;
    }

    public function regenerate(): void
    {
        $this->regenerations++;
    }

    public function destroy(): void
    {
        $this->data = [];
    }
}
