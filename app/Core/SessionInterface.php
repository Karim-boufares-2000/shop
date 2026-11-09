<?php

declare(strict_types=1);

namespace App\Core;

interface SessionInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function has(string $key): bool;

    public function forget(string $key): void;

    /** Lit puis supprime (messages flash). */
    public function pull(string $key, mixed $default = null): mixed;

    /** Nouvel identifiant de session (à appeler après authentification). */
    public function regenerate(): void;

    public function destroy(): void;
}
