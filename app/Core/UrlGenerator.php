<?php

declare(strict_types=1);

namespace App\Core;

/** Construit des URL relatives au dossier d'installation (XAMPP : /shop, domaine : ""). */
final class UrlGenerator
{
    public function __construct(private string $basePath)
    {
    }

    /** @param array<string,scalar> $query */
    public function to(string $path = '/', array $query = []): string
    {
        $url = $this->basePath . '/' . ltrim($path, '/');
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $url;
    }

    public function asset(string $path): string
    {
        return $this->to('assets/' . ltrim($path, '/'));
    }
}
