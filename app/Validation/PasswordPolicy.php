<?php

declare(strict_types=1);

namespace App\Validation;

/** Politique de mot de passe administrateur. Retourne une clé de traduction d'erreur, ou null si valide. */
final class PasswordPolicy
{
    public const MIN_LENGTH = 12;
    public const MAX_BYTES = 72; // limite de bcrypt ; appliquée aussi à Argon2id par cohérence
    private const MIN_DISTINCT_CHARS = 6;

    public static function check(string $password, string $username): ?string
    {
        if (mb_strlen($password, 'UTF-8') < self::MIN_LENGTH) {
            return 'password.too_short';
        }
        if (strlen($password) > self::MAX_BYTES) {
            return 'password.too_long';
        }
        $lower = mb_strtolower($password, 'UTF-8');
        $user = mb_strtolower($username, 'UTF-8');
        if ($user !== '' && mb_strlen($user, 'UTF-8') >= 3 && str_contains($lower, $user)) {
            return 'password.contains_username';
        }
        if (count(array_unique(mb_str_split($password, 1, 'UTF-8'))) < self::MIN_DISTINCT_CHARS) {
            return 'password.too_simple';
        }

        return null;
    }
}
