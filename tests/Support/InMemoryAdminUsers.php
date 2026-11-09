<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Repositories\AdminUserRepositoryInterface;

/** Dépôt en mémoire qui reproduit la logique SQL (verrouillage, compteurs) pour les tests unitaires. */
final class InMemoryAdminUsers implements AdminUserRepositoryInterface
{
    /** @var array<int,array<string,mixed>> */
    public array $rows = [];
    private int $nextId = 1;

    public function findByUsername(string $username): ?array
    {
        foreach ($this->rows as $row) {
            if (strcasecmp((string) $row['username'], $username) === 0) {
                return $row;
            }
        }

        return null;
    }

    public function findById(int $id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    public function create(string $username, string $passwordHash, bool $mustChangePassword): int
    {
        $id = $this->nextId++;
        $this->rows[$id] = [
            'id' => $id, 'username' => $username, 'password_hash' => $passwordHash,
            'must_change_password' => $mustChangePassword, 'failed_attempts' => 0, 'locked_until' => null,
        ];

        return $id;
    }

    public function recordFailure(int $id, int $maxAttempts, string $lockUntilUtc): void
    {
        if ($this->rows[$id]['failed_attempts'] + 1 >= $maxAttempts) {
            $this->rows[$id]['locked_until'] = $lockUntilUtc;
        }
        $this->rows[$id]['failed_attempts'] = min($this->rows[$id]['failed_attempts'] + 1, 1000);
    }

    public function recordSuccess(int $id, string $nowUtc): void
    {
        $this->rows[$id]['failed_attempts'] = 0;
        $this->rows[$id]['locked_until'] = null;
    }

    public function updatePassword(int $id, string $passwordHash, bool $mustChangePassword, string $nowUtc): void
    {
        $this->rows[$id]['password_hash'] = $passwordHash;
        $this->rows[$id]['must_change_password'] = $mustChangePassword;
    }

    public function updateHash(int $id, string $passwordHash): void
    {
        $this->rows[$id]['password_hash'] = $passwordHash;
    }

    public function unlock(int $id): void
    {
        $this->rows[$id]['failed_attempts'] = 0;
        $this->rows[$id]['locked_until'] = null;
    }
}
