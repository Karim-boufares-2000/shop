<?php

declare(strict_types=1);

namespace App\Core;

/** Jeton CSRF par session, comparé en temps constant. */
final class Csrf
{
    public const FIELD = '_csrf';
    private const KEY = '_csrf_token';

    public function __construct(private SessionInterface $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::KEY);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::KEY, $token);
        }

        return $token;
    }

    /** Ne crée jamais de jeton : une session sans jeton ne valide rien. */
    public function isValid(?string $submitted): bool
    {
        $token = $this->session->get(self::KEY);

        return is_string($token) && is_string($submitted) && $submitted !== '' && hash_equals($token, $submitted);
    }

    public function rotate(): void
    {
        $this->session->forget(self::KEY);
    }
}
