<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private string $basePath;

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $post
     * @param array<string,mixed> $server
     * @param array<string,mixed> $cookies
     * @param array<string,mixed> $files
     */
    public function __construct(
        private array $query = [],
        private array $post = [],
        private array $server = [],
        private array $cookies = [],
        private array $files = [],
        private bool $trustForwardedProto = false,
    ) {
        $this->basePath = $this->detectBasePath();
    }

    public static function fromGlobals(bool $trustForwardedProto = false): self
    {
        return new self($_GET, $_POST, $_SERVER, $_COOKIE, $_FILES, $trustForwardedProto);
    }

    public function method(): string
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    /** Préfixe d'installation, ex. "/shop" sous XAMPP, "" à la racine d'un domaine. */
    public function basePath(): string
    {
        return $this->basePath;
    }

    /** Chemin de la requête sans préfixe d'installation, décodé et normalisé. */
    public function path(): string
    {
        $path = $this->rawPath();
        if ($this->basePath !== '' && $this->hasPrefix($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath));
        }
        $path = rawurldecode($path);
        if (preg_match('/[\x00-\x1F\x7F]/', $path) === 1 || !mb_check_encoding($path, 'UTF-8')) {
            throw new HttpException(400, 'bad_path');
        }
        $path = '/' . trim($path, '/');

        return preg_replace('#/{2,}#', '/', $path) ?? '/';
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /** @return array<string,mixed> */
    public function queryAll(): array
    {
        return $this->query;
    }

    /** Nom d'hôte validé (jamais d'en-tête X-Forwarded-Host) ; "localhost" si invalide. */
    public function host(): string
    {
        foreach (['HTTP_HOST', 'SERVER_NAME'] as $key) {
            $value = $this->server[$key] ?? null;
            if (is_string($value) && preg_match('/^[A-Za-z0-9.-]+(:\d{1,5})?$/', $value) === 1) {
                return $value;
            }
        }

        return 'localhost';
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    /** @return array<string,mixed> */
    public function postAll(): array
    {
        return $this->post;
    }

    public function cookie(string $key): ?string
    {
        $value = $this->cookies[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /** @return array<string,mixed>|null */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    /**
     * Fichiers téléversés pour un champ (simple ou multiple), normalisés. Les vérifications de sécurité
     * (taille, type réel, contenu) sont faites par ImageProcessor, jamais ici.
     *
     * @return list<array{name:string,tmp_name:string,size:int,error:int}>
     */
    public function uploadedFiles(string $key): array
    {
        $entry = $this->files[$key] ?? null;
        if (!is_array($entry)) {
            return [];
        }
        if (is_array($entry['name'] ?? null)) {
            $files = [];
            foreach (array_keys($entry['name']) as $i) {
                $files[] = [
                    'name' => (string) ($entry['name'][$i] ?? ''),
                    'tmp_name' => (string) ($entry['tmp_name'][$i] ?? ''),
                    'size' => (int) ($entry['size'][$i] ?? 0),
                    'error' => (int) ($entry['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                ];
            }

            return $files;
        }

        return [[
            'name' => (string) ($entry['name'] ?? ''),
            'tmp_name' => (string) ($entry['tmp_name'] ?? ''),
            'size' => (int) ($entry['size'] ?? 0),
            'error' => (int) ($entry['error'] ?? UPLOAD_ERR_NO_FILE),
        ]];
    }

    public function header(string $name): ?string
    {
        $value = $this->server['HTTP_' . strtoupper(str_replace('-', '_', $name))] ?? null;

        return is_string($value) ? $value : null;
    }

    /** Adresse IP du client. X-Forwarded-For n'est volontairement jamais utilisé (falsifiable). */
    public function ip(): string
    {
        $ip = $this->server['REMOTE_ADDR'] ?? '';

        return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0';
    }

    public function userAgent(): string
    {
        $agent = $this->server['HTTP_USER_AGENT'] ?? '';

        return is_string($agent) ? mb_substr($agent, 0, 255) : '';
    }

    public function isSecure(): bool
    {
        $https = $this->server['HTTPS'] ?? '';
        if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
            return true;
        }
        if ((string) ($this->server['SERVER_PORT'] ?? '') === '443') {
            return true;
        }

        return $this->trustForwardedProto
            && strtolower((string) $this->header('X-Forwarded-Proto')) === 'https';
    }

    /**
     * Chemin interne sûr (anti open-redirect) lu dans POST puis GET.
     * Accepte uniquement "/quelque/chose" : refuse "//hote", "http://...", "\" et caractères de contrôle.
     */
    public function safeReturnPath(string $key, string $default = '/'): string
    {
        $value = $this->post($key) ?? $this->query($key);
        if (!is_string($value)) {
            return $default;
        }

        return preg_match('#^/(?![/\\\\])[^\x00-\x1F\x7F\\\\]*$#', $value) === 1 ? $value : $default;
    }

    private function rawPath(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        // Pas de parse_url() : il prend "//a/b" pour un hôte "a". Forme absolue (http://hôte/x) acceptée.
        $uri = preg_replace('~^[a-zA-Z][a-zA-Z0-9+.\-]*://[^/?#]*~', '', $uri) ?? $uri;
        $path = substr($uri, 0, strcspn($uri, '?#'));

        return $path !== '' ? $path : '/';
    }

    private function detectBasePath(): string
    {
        $script = (string) ($this->server['SCRIPT_NAME'] ?? '');
        $scriptDir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        $parentDir = rtrim(dirname($scriptDir), '/');
        $uriPath = $this->rawPath();

        foreach ([$scriptDir, $parentDir] as $candidate) {
            if ($candidate !== '' && $candidate !== '.' && $this->hasPrefix($uriPath, $candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private function hasPrefix(string $path, string $prefix): bool
    {
        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }
}
