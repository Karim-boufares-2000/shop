<?php

declare(strict_types=1);

namespace App\Core;

/** Construit des URL relatives au dossier d'installation (XAMPP : /shop, domaine : "") et des URL absolues (SEO). */
final class UrlGenerator
{
    /** @param string $siteUrl URL publique du site sans "/" final (ex. https://exemple.com ou http://localhost/shop) */
    public function __construct(private string $basePath, private string $siteUrl = '')
    {
    }

    /** @param array<string,scalar> $query */
    public function to(string $path = '/', array $query = []): string
    {
        $url = $this->basePath . '/' . ltrim($path, '/');

        return $url . $this->queryString($query);
    }

    /** URL absolue pour les canoniques, le plan du site et les données structurées. @param array<string,scalar> $query */
    public function absolute(string $path = '/', array $query = []): string
    {
        if ($this->siteUrl === '') {
            return $this->to($path, $query);
        }

        return rtrim($this->siteUrl, '/') . '/' . ltrim($path, '/') . $this->queryString($query);
    }

    public function asset(string $path): string
    {
        return $this->to('assets/' . ltrim($path, '/'));
    }

    /** @param array<string,scalar> $query */
    private function queryString(array $query): string
    {
        return $query === [] ? '' : '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
