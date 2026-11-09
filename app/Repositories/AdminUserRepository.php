<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class AdminUserRepository implements AdminUserRepositoryInterface
{
    private const COLUMNS = 'id, username, password_hash, must_change_password, failed_attempts, locked_until';

    public function __construct(private Database $db)
    {
    }

    public function findByUsername(string $username): ?array
    {
        return $this->normalize($this->db->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM admin_users WHERE username = :username LIMIT 1',
            ['username' => $username],
        ));
    }

    public function findById(int $id): ?array
    {
        return $this->normalize($this->db->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM admin_users WHERE id = :id LIMIT 1',
            ['id' => $id],
        ));
    }

    public function create(string $username, string $passwordHash, bool $mustChangePassword): int
    {
        $this->db->execute(
            'INSERT INTO admin_users (username, password_hash, must_change_password)
             VALUES (:username, :password_hash, :must_change)',
            ['username' => $username, 'password_hash' => $passwordHash, 'must_change' => $mustChangePassword ? 1 : 0],
        );

        return (int) $this->db->lastInsertId();
    }

    public function recordFailure(int $id, int $maxAttempts, string $lockUntilUtc): void
    {
        // locked_until est évalué AVANT l'incrément (MySQL évalue les affectations de gauche à droite).
        $this->db->execute(
            'UPDATE admin_users
             SET locked_until = IF(failed_attempts + 1 >= :max_attempts, :lock_until, locked_until),
                 failed_attempts = LEAST(failed_attempts + 1, 1000)
             WHERE id = :id',
            ['max_attempts' => $maxAttempts, 'lock_until' => $lockUntilUtc, 'id' => $id],
        );
    }

    public function recordSuccess(int $id, string $nowUtc): void
    {
        $this->db->execute(
            'UPDATE admin_users SET failed_attempts = 0, locked_until = NULL, last_login_at = :now WHERE id = :id',
            ['now' => $nowUtc, 'id' => $id],
        );
    }

    public function updatePassword(int $id, string $passwordHash, bool $mustChangePassword, string $nowUtc): void
    {
        $this->db->execute(
            'UPDATE admin_users
             SET password_hash = :password_hash, must_change_password = :must_change, password_changed_at = :now
             WHERE id = :id',
            ['password_hash' => $passwordHash, 'must_change' => $mustChangePassword ? 1 : 0, 'now' => $nowUtc, 'id' => $id],
        );
    }

    public function updateHash(int $id, string $passwordHash): void
    {
        $this->db->execute('UPDATE admin_users SET password_hash = :password_hash WHERE id = :id', ['password_hash' => $passwordHash, 'id' => $id]);
    }

    public function unlock(int $id): void
    {
        $this->db->execute('UPDATE admin_users SET failed_attempts = 0, locked_until = NULL WHERE id = :id', ['id' => $id]);
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array<string,mixed>|null
     */
    private function normalize(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['must_change_password'] = (bool) (int) $row['must_change_password'];
        $row['failed_attempts'] = (int) $row['failed_attempts'];

        return $row;
    }
}
