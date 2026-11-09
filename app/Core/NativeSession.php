<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session PHP durcie : cookie HttpOnly/SameSite, mode strict, expiration d'inactivité et absolue.
 * Session PARESSEUSE : aucune session n'est créée tant qu'on n'écrit rien dedans, et une lecture
 * sans cookie de session ne démarre rien (pas de fichier ni de Set-Cookie pour les visiteurs anonymes).
 */
final class NativeSession implements SessionInterface
{
    public function __construct(
        private string $savePath,
        private bool $secure,
        private int $idleTimeout = 1800,
        private int $absoluteTimeout = 28800,
        private string $cookiePath = '/',
        private string $name = 'shop_session',
    ) {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (is_dir($this->savePath) && is_writable($this->savePath)) {
            session_save_path($this->savePath);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', (string) $this->absoluteTimeout);
        session_cache_limiter(''); // PHP n'impose pas "no-store" ; Cache-Control est géré par l'application
        session_name($this->name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $this->cookiePath,
            'secure' => $this->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        $this->enforceTimeouts();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->resume() ? ($_SESSION[$key] ?? $default) : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return $this->resume() && isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        if ($this->resume()) {
            unset($_SESSION[$key]);
        }
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        if (!$this->resume()) {
            return $default;
        }
        $value = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);

        return $value;
    }

    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        if (!$this->resume()) {
            return;
        }
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
        $_SESSION['_last'] = time();
    }

    /** Reprend une session existante (cookie présent) sans jamais en créer une nouvelle. */
    private function resume(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        if (!isset($_COOKIE[$this->name])) {
            return false;
        }
        $this->start();

        return true;
    }

    private function enforceTimeouts(): void
    {
        $now = time();
        $created = $_SESSION['_created'] ?? null;
        $last = $_SESSION['_last'] ?? null;

        if (!is_int($created)) {
            $_SESSION['_created'] = $now;
        } elseif ($now - $created > $this->absoluteTimeout || $now - (is_int($last) ? $last : $created) > $this->idleTimeout) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['_created'] = $now;
        }
        $_SESSION['_last'] = $now;
    }
}
