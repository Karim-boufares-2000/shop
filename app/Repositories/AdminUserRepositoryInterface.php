<?php

declare(strict_types=1);

namespace App\Repositories;

interface AdminUserRepositoryInterface
{
    /**
     * Clés : id (int), username, password_hash, must_change_password (bool), failed_attempts (int), locked_until (?string UTC).
     *
     * @return array<string,mixed>|null
     */
    public function findByUsername(string $username): ?array;

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array;

    public function create(string $username, string $passwordHash, bool $mustChangePassword): int;

    /** Incrémente l'échec ; verrouille jusqu'à $lockUntilUtc si le total atteint $maxAttempts. */
    public function recordFailure(int $id, int $maxAttempts, string $lockUntilUtc): void;

    public function recordSuccess(int $id, string $nowUtc): void;

    public function updatePassword(int $id, string $passwordHash, bool $mustChangePassword, string $nowUtc): void;

    /** Remplace le hachage sans toucher aux autres champs (migration d'algorithme). */
    public function updateHash(int $id, string $passwordHash): void;

    public function unlock(int $id): void;
}
