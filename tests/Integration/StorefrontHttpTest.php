<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Kernel;
use App\Core\Request;
use App\Core\Response;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Tests\Support\CatalogFixtures;
use Tests\Support\TestDatabase;

/**
 * Parcours HTTP complet de la boutique publique sur la base de TEST (TEST_DB_NAME), APP_URL = https://shop.test.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class StorefrontHttpTest extends TestCase
{
    private Kernel $kernel;

    protected function setUp(): void
    {
        $db = TestDatabase::create();
        if ($db === null) {
            $this->markTestSkipped('TEST_DB_NAME non défini : test d\'intégration ignoré.');
        }
        $fx = new CatalogFixtures($db);
        $fx->clean();

        $femme = $fx->category('femme', 'Femme', null, true, 'نساء');
        $robes = $fx->category('robes', 'Robes', $femme);
        $cachee = $fx->category('cachee', 'Cachée', null, false);

        $rouge = $fx->product($robes, 'robe-rouge', 'Robe rouge', '2000.00', '1500.00', 3, true, true, 'فستان أحمر', "Première ligne\nSeconde ligne");
        $fx->image($rouge, str_repeat('a', 32));
        $fx->variant($rouge, 'M / Rouge', 2);
        $fx->product($robes, 'sac-inactif', 'Sac inactif', active: false);
        $fx->product($cachee, 'chemise-cachee', 'Chemise cachée');
        $fx->product($robes, 'script-alert-1-script', '<script>alert(1)</script>');
        for ($i = 1; $i <= 14; $i++) {
            $fx->product($robes, 'article-' . $i, 'Article ' . $i, (string) (100 + $i) . '.00');
        }

        $this->kernel = Kernel::boot(dirname(__DIR__, 2), [
            'DB_HOST' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'DB_PORT' => getenv('TEST_DB_PORT') ?: '3306',
            'DB_NAME' => (string) getenv('TEST_DB_NAME'),
            'DB_USER' => getenv('TEST_DB_USER') ?: '',
            'DB_PASS' => getenv('TEST_DB_PASS') ?: '',
            'APP_URL' => 'https://shop.test',
            'APP_DEBUG' => 'false',
            'APP_ENV' => 'production',
        ]);
    }

    /** @param array<string,mixed> $query @param array<string,mixed> $cookies */
    private function get(string $path, array $query = [], array $cookies = []): Response
    {
        $uri = $path . ($query === [] ? '' : '?' . http_build_query($query));

        return $this->kernel->handle(new Request($query, [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri, 'SCRIPT_NAME' => '/index.php', 'HTTP_HOST' => 'shop.test'], $cookies));
    }

    private function plain(string $html): string
    {
        return str_replace("\u{202F}", ' ', $html);
    }

    public function testHomePageShowsOnlyPublicContentWithSeoAndSecurityHeaders(): void
    {
        $response = $this->get('/');
        $body = $response->body();

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('Robe rouge', $body);
        $this->assertStringNotContainsString('Sac inactif', $body);
        $this->assertStringNotContainsString('Chemise cachée', $body);
        $this->assertStringContainsString('<link rel="canonical" href="https://shop.test/">', $body);
        $this->assertStringContainsString('property="og:title"', $body);
        $this->assertStringContainsString('href="/categorie/femme"', $body);           // menu : catégories actives
        $this->assertStringNotContainsString('/categorie/cachee', $body);
        $this->assertStringContainsString('<html lang="fr" dir="ltr">', $body);
        $this->assertSame('private, no-cache', $response->header('Cache-Control'));
        $this->assertNotNull($response->header('Content-Security-Policy'));
        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
    }

    public function testProductPageContentAndStructuredData(): void
    {
        $response = $this->get('/produit/robe-rouge');
        $body = $this->plain($response->body());

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('<title>Robe rouge — Ma Boutique</title>', $body);
        $this->assertStringContainsString('1 500,00', $body);
        $this->assertStringContainsString('2 000,00', $body);
        $this->assertStringContainsString('-25%', $body);
        $this->assertStringContainsString('Première ligne<br>', $body);
        $this->assertStringContainsString('M / Rouge', $body);
        $this->assertStringContainsString('<link rel="canonical" href="https://shop.test/produit/robe-rouge">', $body);
        $this->assertStringContainsString('/categorie/femme', $body);   // fil d'Ariane : parent
        $this->assertStringContainsString('/categorie/robes', $body);
        $this->assertStringContainsString('/media/products/' . str_repeat('a', 32) . '.jpg', $body);

        $this->assertSame(1, preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $body, $m));
        $json = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Product', $json['@type']);
        $this->assertSame('1500.00', $json['offers']['price']);
        $this->assertSame('DZD', $json['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $json['offers']['availability']);
    }

    public function testProductNamesAreEscapedEverywhere(): void
    {
        $body = $this->get('/produit/script-alert-1-script')->body();

        $this->assertStringNotContainsString('<script>alert(1)', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        $this->assertStringContainsString('\u003Cscript\u003Ealert(1)\u003C/script\u003E', $body); // JSON-LD : < et > échappés
        $this->assertStringNotContainsString('<script>alert(1)', $this->get('/')->body());
    }

    public function testHiddenOrInvalidProductsAreIndistinguishable404s(): void
    {
        foreach (['/produit/sac-inactif', '/produit/chemise-cachee', '/produit/inexistant', '/produit/Robe-Rouge', '/produit/robe_rouge', '/produit/a%20b'] as $path) {
            $this->assertSame(404, $this->get($path)->status(), $path);
        }
    }

    public function testArabicLocaleShowsArabicContentRtl(): void
    {
        $body = $this->get('/produit/robe-rouge', [], ['shop_locale' => 'ar'])->body();

        $this->assertStringContainsString('dir="rtl"', $body);
        $this->assertStringContainsString('فستان أحمر', $body);
        $this->assertStringContainsString('نساء', $body);
    }

    public function testListingPaginationAndCanonical(): void
    {
        $page1 = $this->get('/produits');
        $this->assertSame(200, $page1->status());
        $this->assertSame(12, substr_count($page1->body(), 'class="product-card"'));
        $this->assertStringContainsString('rel="next"', $page1->body());
        $this->assertStringContainsString('<link rel="canonical" href="https://shop.test/produits">', $page1->body());
        $this->assertStringNotContainsString('name="robots"', $page1->body());

        $page2 = $this->get('/produits', ['page' => '2']);
        $this->assertSame(4, substr_count($page2->body(), 'class="product-card"'));   // 16 visibles au total
        $this->assertStringContainsString('<link rel="canonical" href="https://shop.test/produits?page=2">', $page2->body());

        $beyond = $this->get('/produits', ['page' => '999']);
        $this->assertSame(200, $beyond->status());
        $this->assertSame(4, substr_count($beyond->body(), 'class="product-card"'));
    }

    public function testFilteredListingsAreNotIndexedAndAreSafe(): void
    {
        $search = $this->get('/produits', ['q' => 'robe']);
        $this->assertStringContainsString('<meta name="robots" content="noindex,follow">', $search->body());
        $this->assertStringContainsString('<link rel="canonical" href="https://shop.test/produits">', $search->body());
        $this->assertSame(1, substr_count($search->body(), 'class="product-card"'));

        $cheap = $this->get('/produits', ['max_price' => '105', 'sort' => 'price_asc']);
        $this->assertSame(5, substr_count($cheap->body(), 'class="product-card"')); // articles 1 à 5

        $this->assertSame(0, substr_count($this->get('/produits', ['q' => "' OR 1=1 --"])->body(), 'class="product-card"'));
        $this->assertSame(200, $this->get('/produits', ['sort' => 'p.id; DROP TABLE products', 'page' => 'abc', 'category' => '../x'])->status());
        $this->assertSame(200, $this->get('/produits', ['q' => ['a'], 'min_price' => ['x']])->status());
    }

    public function testCategoryPages(): void
    {
        $femme = $this->get('/categorie/femme');
        $this->assertSame(200, $femme->status());
        $this->assertSame(12, substr_count($femme->body(), 'class="product-card"')); // inclut les produits de "Robes"

        $robes = $this->get('/categorie/robes');
        $this->assertStringContainsString('/categorie/femme', $robes->body()); // fil d'Ariane
        $this->assertStringContainsString('https://shop.test/categorie/robes', $robes->body());

        $this->assertSame(404, $this->get('/categorie/cachee')->status());
        $this->assertSame(404, $this->get('/categorie/inconnue')->status());
        $this->assertSame(404, $this->get('/categorie/Femme')->status());
    }

    public function testSitemapListsOnlyPublicUrls(): void
    {
        $response = $this->get('/sitemap.xml');
        $xml = $response->body();

        $this->assertSame(200, $response->status());
        $this->assertStringStartsWith('application/xml', (string) $response->header('Content-Type'));
        $this->assertSame('public, max-age=3600', $response->header('Cache-Control'));
        $this->assertStringContainsString('<loc>https://shop.test/produit/robe-rouge</loc>', $xml);
        $this->assertStringContainsString('<loc>https://shop.test/categorie/robes</loc>', $xml);
        $this->assertStringContainsString('<loc>https://shop.test/</loc>', $xml);
        foreach (['sac-inactif', 'chemise-cachee', 'categorie/cachee'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $xml);
        }
        $this->assertStringNotContainsString('/admin', $xml);

        if (function_exists('simplexml_load_string')) {
            libxml_use_internal_errors(true);
            $this->assertNotFalse(simplexml_load_string($xml), 'le plan du site doit être un XML valide');
        }
    }

    public function testRobotsTxt(): void
    {
        $response = $this->get('/robots.txt');

        $this->assertSame(200, $response->status());
        $this->assertStringStartsWith('text/plain', (string) $response->header('Content-Type'));
        $this->assertStringContainsString("Disallow: /admin\n", $response->body());
        $this->assertStringContainsString('Sitemap: https://shop.test/sitemap.xml', $response->body());
    }

    public function testAdministrationRemainsProtected(): void
    {
        $this->assertSame(303, $this->get('/admin/products')->status());
    }
}
