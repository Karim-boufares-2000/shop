<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Request;
use App\Core\Response;
use App\Core\UrlGenerator;
use App\Services\StorefrontService;

/** sitemap.xml et robots.txt : ne publient que du contenu public (visible). */
final class SeoController
{
    public function __construct(private StorefrontService $storefront, private UrlGenerator $urls)
    {
    }

    /** @param array<string,string> $params */
    public function sitemap(Request $request, array $params): Response
    {
        $entries = $this->storefront->sitemap();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $xml .= $this->url('/', null);
        $xml .= $this->url('/produits', null);
        foreach ($entries['categories'] as $category) {
            $xml .= $this->url('/categorie/' . $category['slug'], (string) $category['updated_at']);
        }
        foreach ($entries['products'] as $product) {
            $xml .= $this->url('/produit/' . $product['slug'], (string) $product['updated_at']);
        }
        $xml .= '</urlset>' . "\n";

        return new Response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /** @param array<string,string> $params */
    public function robots(Request $request, array $params): Response
    {
        $body = "User-agent: *\n"
            . 'Disallow: ' . $this->urls->to('/admin') . "\n"
            . 'Disallow: ' . $this->urls->to('/langue/') . "\n"
            . 'Sitemap: ' . $this->urls->absolute('/sitemap.xml') . "\n";

        return new Response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function url(string $path, ?string $updatedAt): string
    {
        $location = htmlspecialchars($this->urls->absolute($path), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $lastmod = $updatedAt !== null ? '<lastmod>' . substr($updatedAt, 0, 10) . '</lastmod>' : '';

        return '  <url><loc>' . $location . '</loc>' . $lastmod . '</url>' . "\n";
    }
}
