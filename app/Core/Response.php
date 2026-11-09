<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /** @var array<string,string> */
    private array $headers = [];
    /** @var list<array{name:string,value:string,options:array<string,mixed>}> */
    private array $cookies = [];

    /** @param array<string,string> $headers */
    public function __construct(private string $body = '', private int $status = 200, array $headers = [])
    {
        foreach ($headers as $name => $value) {
            $this->headers[self::clean($name)] = self::clean($value);
        }
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /** @param array<mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

        return new self(json_encode($data, $flags), $status, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    /** $location doit être un chemin interne déjà construit (voir Request::safeReturnPath). */
    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[self::clean($name)] = self::clean($value);

        return $clone;
    }

    /** @param array<string,mixed> $options expires, path, secure, httponly, samesite */
    public function withCookie(string $name, string $value, array $options = []): self
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            throw new \InvalidArgumentException('Nom de cookie invalide.');
        }
        $clone = clone $this;
        $clone->cookies[] = ['name' => $name, 'value' => $value, 'options' => $options];

        return $clone;
    }

    /** @return list<array{name:string,value:string,options:array<string,mixed>}> */
    public function cookies(): array
    {
        return $this->cookies;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
            foreach ($this->cookies as $cookie) {
                setcookie($cookie['name'], $cookie['value'], $cookie['options']);
            }
        }
        echo $this->body;
    }

    /** Supprime CR/LF : empêche l'injection d'en-têtes HTTP. */
    private static function clean(string $value): string
    {
        return str_replace(["\r", "\n", "\0"], '', $value);
    }
}
