<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\StorefrontRepository;
use App\Services\StorefrontService;
use App\Support\ListingFilters;
use PHPUnit\Framework\TestCase;
use Tests\Support\CatalogFixtures;
use Tests\Support\TestDatabase;

/** Base de TEST uniquement (variable TEST_DB_NAME). */
final class StorefrontRepositoryTest extends TestCase
{
    private CatalogFixtures $fx;
    private StorefrontRepository $repo;
    private int $femme;
    private int $robes;

    protected function setUp(): void
    {
        $db = TestDatabase::create();
        if ($db === null) {
            $this->markTestSkipped('TEST_DB_NAME non défini : test d\'intégration ignoré.');
        }
        $this->fx = new CatalogFixtures($db);
        $this->fx->clean();
        $this->repo = new StorefrontRepository($db);
        $this->femme = $this->fx->category('femme', 'Femme');
        $this->robes = $this->fx->category('robes', 'Robes', $this->femme);
    }

    /** @param list<array<string,mixed>> $rows @return list<string> */
    private function slugs(array $rows): array
    {
        return array_map(static fn (array $r): string => (string) $r['slug'], $rows);
    }

    private function search(string ...$pairs): array
    {
        $query = [];
        foreach ($pairs as $pair) {
            [$k, $v] = explode('=', $pair, 2);
            $query[$k] = $v;
        }

        return $this->repo->listing(ListingFilters::fromQuery($query), 1, 50);
    }

    public function testOnlyVisibleProductsAreReturned(): void
    {
        $this->fx->product($this->robes, 'visible', 'Visible');
        $this->fx->product($this->robes, 'inactif', 'Inactif', active: false);
        $hiddenCat = $this->fx->category('cachee', 'Cachée', null, false);
        $this->fx->product($hiddenCat, 'dans-cat-cachee', 'Dans catégorie cachée');
        $inactiveParent = $this->fx->category('parent-off', 'Parent off', null, false);
        $childOfInactive = $this->fx->category('enfant-actif', 'Enfant actif', $inactiveParent, true);
        $this->fx->product($childOfInactive, 'sous-parent-cache', 'Sous parent caché');

        $result = $this->search();

        $this->assertSame(['visible'], $this->slugs($result['items']));
        $this->assertSame(1, $result['total']);
        $this->assertNull($this->repo->findBySlug('inactif'));
        $this->assertNull($this->repo->findBySlug('dans-cat-cachee'));
        $this->assertNull($this->repo->findBySlug('sous-parent-cache'));
        $this->assertNotNull($this->repo->findBySlug('visible'));
        $this->assertNull($this->repo->findCategoryBySlug('cachee'));
        $this->assertNull($this->repo->findCategoryBySlug('enfant-actif'));
        $this->assertNotNull($this->repo->findCategoryBySlug('robes'));
    }

    public function testAvailabilityUsesActiveVariantsWhenPresent(): void
    {
        $plain = $this->fx->product($this->robes, 'simple', 'Simple', stock: 4);
        $empty = $this->fx->product($this->robes, 'epuise', 'Épuisé', stock: 0);
        $withVariants = $this->fx->product($this->robes, 'variantes', 'Variantes', stock: 99);
        $this->fx->variant($withVariants, 'M', 2);
        $this->fx->variant($withVariants, 'L', 3);
        $this->fx->variant($withVariants, 'XL', 50, false); // variante inactive : ignorée
        $onlyInactiveStock = $this->fx->product($this->robes, 'variante-vide', 'Variante vide', stock: 99);
        $this->fx->variant($onlyInactiveStock, 'S', 0);

        $items = [];
        foreach ($this->search()['items'] as $row) {
            $items[$row['slug']] = $row['available_stock'];
        }

        $this->assertSame(4, $items['simple']);
        $this->assertSame(0, $items['epuise']);
        $this->assertSame(5, $items['variantes']);      // 2 + 3, le stock du produit (99) n'est pas utilisé
        $this->assertSame(0, $items['variante-vide']);  // a des variantes actives : stock produit ignoré
        $this->assertSame(['simple', 'variantes'], array_reverse($this->slugs($this->search('in_stock=1')['items'])));
        $this->assertNotNull($plain);
        $this->assertNotNull($empty);
    }

    public function testPriceFilterAndSortUseThePromotionalPrice(): void
    {
        $this->fx->product($this->robes, 'cher', 'Cher', '3000.00');
        $this->fx->product($this->robes, 'promo', 'Promo', '2000.00', '900.00');
        $this->fx->product($this->robes, 'moyen', 'Moyen', '1500.00');

        $this->assertSame(['promo', 'moyen', 'cher'], $this->slugs($this->search('sort=price_asc')['items']));
        $this->assertSame(['cher', 'moyen', 'promo'], $this->slugs($this->search('sort=price_desc')['items']));
        $this->assertSame(['promo'], $this->slugs($this->search('max_price=1000')['items'])); // 900 effectif, pas 2000
        $this->assertSame(['moyen', 'cher'], $this->slugs($this->search('min_price=1500')['items'])); // les plus récents d'abord
        $this->assertSame(['promo'], $this->slugs($this->search('promo=1')['items']));
    }

    public function testSearchRelevanceAndLikeEscaping(): void
    {
        $this->fx->product($this->robes, 'contient', 'Belle robe', nameAr: null);
        $this->fx->product($this->robes, 'commence', 'Robe rouge');
        $this->fx->product($this->robes, 'arabe', 'Autre', nameAr: 'فستان أحمر');
        $this->fx->product($this->robes, 'pourcent', 'Promo 50% off');
        $this->fx->product($this->robes, 'sans-pourcent', 'Promo 50 off');

        $this->assertSame(['commence', 'contient'], $this->slugs($this->search('q=robe')['items'])); // préfixe d'abord
        $this->assertSame(['arabe'], $this->slugs($this->search('q=' . 'فستان')['items']));          // recherche dans le nom arabe
        $this->assertSame(['pourcent'], $this->slugs($this->search('q=%')['items']));                 // % littéral
        $this->assertSame(['pourcent'], $this->slugs($this->search('q=50%')['items']));
        $this->assertSame(0, $this->search("q=x' OR '1'='1")['total']);
    }

    public function testCategoryFilterIncludesChildrenAndIgnoresUnknownSlugs(): void
    {
        $hommes = $this->fx->category('hommes', 'Hommes');
        $this->fx->product($this->femme, 'sur-parent', 'Sur parent');
        $this->fx->product($this->robes, 'sur-enfant', 'Sur enfant');
        $this->fx->product($hommes, 'chemise', 'Chemise');

        $this->assertEqualsCanonicalizing(['sur-parent', 'sur-enfant'], $this->slugs($this->search('category=femme')['items']));
        $this->assertSame(['sur-enfant'], $this->slugs($this->search('category=robes')['items']));
        $this->assertSame(['chemise'], $this->slugs($this->search('category=hommes')['items']));
        $this->assertSame(0, $this->search('category=inconnue')['total']);
    }

    public function testPaginationAndClampingToLastPage(): void
    {
        for ($i = 1; $i <= 14; $i++) {
            $this->fx->product($this->robes, 'article-' . $i, 'Article ' . $i);
        }
        $service = new StorefrontService($this->repo);
        $filters = ListingFilters::fromQuery([]);

        $first = $service->listing($filters, 1);
        $second = $service->listing($filters, 2);
        $beyond = $service->listing($filters, 99);
        $negative = $service->listing($filters, -4);

        $this->assertCount(12, $first['items']);
        $this->assertSame(14, $first['total']);
        $this->assertSame(2, $first['pages']);
        $this->assertCount(2, $second['items']);
        $this->assertSame(2, $beyond['page']);
        $this->assertSame($this->slugs($second['items']), $this->slugs($beyond['items']));
        $this->assertSame(1, $negative['page']);
    }

    public function testHomeListsAndSimilarProducts(): void
    {
        $a = $this->fx->product($this->robes, 'a', 'A', featured: true);
        $this->fx->product($this->robes, 'b', 'B', '1000.00', '800.00');
        $this->fx->product($this->robes, 'c', 'C', active: false, featured: true);
        $other = $this->fx->category('hommes', 'Hommes');
        $this->fx->product($other, 'd', 'D');

        $this->assertSame(['a'], $this->slugs($this->repo->featured(8)));
        $this->assertSame(['b'], $this->slugs($this->repo->onSale(8)));
        $this->assertSame(['d', 'b', 'a'], $this->slugs($this->repo->latest(8)));
        $this->assertSame(['b'], $this->slugs($this->repo->similar($a, $this->robes, 4))); // même catégorie, sans lui-même
    }

    public function testCategoryTreeCountsAndHidesInactive(): void
    {
        $this->fx->product($this->femme, 'p1', 'P1');
        $this->fx->product($this->robes, 'p2', 'P2');
        $this->fx->product($this->robes, 'p3', 'P3', active: false);
        $this->fx->category('cachee', 'Cachée', null, false);
        $this->fx->category('robes-off', 'Robes off', $this->femme, false);

        $tree = $this->repo->categoryTree();

        $this->assertCount(1, $tree);
        $this->assertSame('femme', $tree[0]['slug']);
        $this->assertSame(2, $tree[0]['products_count']); // p1 + p2 (p3 inactif, sous-catégorie inactive exclue)
        $this->assertSame(['robes'], array_map(static fn (array $c): string => (string) $c['slug'], $tree[0]['children']));
    }

    public function testProductPageDataAndSitemap(): void
    {
        $id = $this->fx->product($this->robes, 'robe-rouge', 'Robe rouge');
        $this->fx->image($id);
        $this->fx->variant($id, 'M', 1);
        $this->fx->variant($id, 'L', 1, false);
        $this->fx->product($this->robes, 'cachee', 'Cachée', active: false);

        $product = $this->repo->findBySlug('robe-rouge');
        $this->assertSame('femme', $product['parent_slug']);
        $this->assertSame('robes', $product['category_slug']);
        $this->assertCount(1, $this->repo->images($id));
        $this->assertCount(1, $this->repo->variants($id)); // variante inactive masquée

        $sitemap = $this->repo->sitemapEntries();
        $this->assertSame(['robe-rouge'], $this->slugs($sitemap['products']));
        $this->assertEqualsCanonicalizing(['femme', 'robes'], $this->slugs($sitemap['categories']));
    }
}
