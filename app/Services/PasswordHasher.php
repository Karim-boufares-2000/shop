<?php

declare(strict_types=1);

namespace App\Services;

/** Hachage des mots de passe : Argon2id si disponible, sinon l'algorithme par défaut de PHP (bcrypt). */
final class PasswordHasher
{
    public function hash(string $password): string
    {
        return password_hash($password, $this->algorithm());
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, $this->algorithm());
    }

    /** Dépense un temps comparable à une vérification (utilisateur inconnu) : limite l'énumération par le temps de réponse. */
    public function burn(string $password): void
    {
        password_hash($password, $this->algorithm());
    }

    private function algorithm(): string
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }
}
