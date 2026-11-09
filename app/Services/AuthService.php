<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Csrf;
use App\Core\SessionInterface;
use App\Enums\LoginOutcome;
use App\Repositories\AdminUserRepositoryInterface;
use App\Validation\PasswordPolicy;

/**
 * Authentification administrateur.
 *  - Réponse identique pour "utilisateur inconnu", "mauvais mot de passe" et "compte verrouillé".
 *  - Limitation par nom d'utilisateur (ici) + par IP (middleware de la route).
 *  - Verrouillage temporaire après N échecs ; session régénérée à la connexion ;
 *    session invalidée si le mot de passe change (empreinte du hachage conservée en session).
 */
final class AuthService
{
    private const SESSION_ADMIN = 'admin_id';
    private const SESSION_STAMP = 'admin_stamp';
    private const MAX_USERNAME_LENGTH = 50;
    private const MAX_INPUT_BYTES = 1000;
    private const USER_ATTEMPTS = 10;
    private const WINDOW_SECONDS = 900;

    private bool $resolved = false;
    /** @var array<string,mixed>|null */
    private ?array $current = null;

    public function __construct(
        private AdminUserRepositoryInterface $admins,
        private SessionInterface $session,
        private Csrf $csrf,
        private RateLimiterInterface $limiter,
        private AuditLoggerInterface $audit,
        private PasswordHasher $hasher,
        private int $maxAttempts = 5,
        private int $lockMinutes = 15,
    ) {
    }

    public function attempt(string $username, string $password, string $ip): LoginOutcome
    {
        $username = trim($username);
        $tooLong = mb_strlen($username) > self::MAX_USERNAME_LENGTH || strlen($password) > self::MAX_INPUT_BYTES;
        $usernameHash = $this->audit->hashValue(mb_strtolower($username));

        if ($username === '' || $tooLong) {
            $this->hasher->burn($tooLong ? '' : $password);
            $this->audit->log('auth.login_failed', null, null, null, ['reason' => 'malformed'], $ip);

            return LoginOutcome::Invalid;
        }

        $bucket = 'login_user|' . mb_strtolower($username);
        if (!$this->limiter->attempt($bucket, self::USER_ATTEMPTS, self::WINDOW_SECONDS)) {
            $this->audit->log('auth.login_throttled', null, null, null, ['username_hash' => $usernameHash], $ip);

            return LoginOutcome::Throttled;
        }

        $admin = $this->admins->findByUsername($username);
        if ($admin === null) {
            $this->hasher->burn($password);
            $this->audit->log('auth.login_failed', null, null, null, ['username_hash' => $usernameHash], $ip);

            return LoginOutcome::Invalid;
        }

        $id = (int) $admin['id'];
        if ($this->isLocked($admin)) {
            $this->audit->log('auth.login_blocked', $id, 'admin', (string) $id, [], $ip);

            return LoginOutcome::Locked;
        }

        $hash = (string) $admin['password_hash'];
        if (!$this->hasher->verify($password, $hash)) {
            $lockUntil = gmdate('Y-m-d H:i:s', time() + $this->lockMinutes * 60);
            $this->admins->recordFailure($id, $this->maxAttempts, $lockUntil);
            $this->audit->log('auth.login_failed', $id, 'admin', (string) $id, [], $ip);

            return LoginOutcome::Invalid;
        }

        if ($this->hasher->needsRehash($hash)) {
            $hash = $this->hasher->hash($password);
            $this->admins->updateHash($id, $hash);
        }
        $this->admins->recordSuccess($id, gmdate('Y-m-d H:i:s'));

        $this->session->regenerate();          // anti fixation de session
        $this->csrf->rotate();
        $this->session->set(self::SESSION_ADMIN, $id);
        $this->session->set(self::SESSION_STAMP, $this->stamp($hash));
        $this->limiter->clear($bucket);
        $this->reset();
        $this->audit->log('auth.login', $id, 'admin', (string) $id, [], $ip);

        return LoginOutcome::Success;
    }

    /** @return array<string,mixed>|null administrateur connecté (rechargé depuis la base) */
    public function currentAdmin(): ?array
    {
        if ($this->resolved) {
            return $this->current;
        }
        $this->resolved = true;

        $id = $this->session->get(self::SESSION_ADMIN);
        if (!is_int($id)) {
            return $this->current = null;
        }
        $admin = $this->admins->findById($id);
        $stamp = $this->session->get(self::SESSION_STAMP);
        if ($admin === null || !is_string($stamp) || !hash_equals($stamp, $this->stamp((string) $admin['password_hash']))) {
            $this->session->forget(self::SESSION_ADMIN);
            $this->session->forget(self::SESSION_STAMP);

            return $this->current = null;
        }

        return $this->current = $admin;
    }

    public function logout(string $ip): void
    {
        $admin = $this->currentAdmin();
        if ($admin !== null) {
            $this->audit->log('auth.logout', (int) $admin['id'], 'admin', (string) $admin['id'], [], $ip);
        }
        $this->session->destroy();
        $this->csrf->rotate();
        $this->reset();
    }

    /** @return string|null clé de traduction de l'erreur, null en cas de succès */
    public function changePassword(int $adminId, string $current, string $new, string $ip): ?string
    {
        $bucket = 'password_change|' . $adminId;
        if (!$this->limiter->attempt($bucket, 5, self::WINDOW_SECONDS)) {
            return 'auth.throttled';
        }
        $admin = $this->admins->findById($adminId);
        if ($admin === null) {
            return 'auth.login_failed';
        }

        $hash = (string) $admin['password_hash'];
        if (strlen($current) > self::MAX_INPUT_BYTES || !$this->hasher->verify($current, $hash)) {
            $this->audit->log('auth.password_change_failed', $adminId, 'admin', (string) $adminId, [], $ip);

            return 'auth.current_password_wrong';
        }
        if ($new === $current) {
            return 'auth.password_same';
        }
        $policyError = PasswordPolicy::check($new, (string) $admin['username']);
        if ($policyError !== null) {
            return $policyError;
        }

        $newHash = $this->hasher->hash($new);
        $this->admins->updatePassword($adminId, $newHash, false, gmdate('Y-m-d H:i:s'));
        $this->session->regenerate();
        $this->csrf->rotate();
        $this->session->set(self::SESSION_STAMP, $this->stamp($newHash)); // les autres sessions deviennent invalides
        $this->limiter->clear($bucket);
        $this->reset();
        $this->audit->log('auth.password_changed', $adminId, 'admin', (string) $adminId, [], $ip);

        return null;
    }

    /** @param array<string,mixed> $admin */
    private function isLocked(array $admin): bool
    {
        $until = $admin['locked_until'] ?? null;
        if (!is_string($until) || $until === '') {
            return false;
        }
        $timestamp = strtotime($until . ' UTC');

        return $timestamp !== false && $timestamp > time();
    }

    private function stamp(string $passwordHash): string
    {
        return hash('sha256', $passwordHash);
    }

    private function reset(): void
    {
        $this->resolved = false;
        $this->current = null;
    }
}
