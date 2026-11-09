<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Translator;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductImageRepository;
use App\Repositories\ProductRepository;
use App\Repositories\VariantRepository;
use App\Services\CategoryService;
use App\Services\ImageProcessor;
use App\Services\ProductImageService;
use App\Services\ProductService;
use App\Services\VariantService;
use App\Validation\PhoneNumber;
use App\Validation\Validator;
use PHPUnit\Framework\TestCase;
use Tests\Support\RecordingAudit;
use Tests\Support\TestDatabase;
use Tests\Support\TestImages;

/** Base de TEST uniquement (variable TEST_DB_NAME). Vide les tables du catalogue à chaque test. */
final class CatalogTest extends TestCase
{
    private Database $db;
    private string $mediaDir;
    private RecordingAudit $audit;
    private CategoryService $categories;
    private ProductService $products;
    private VariantService $variants;
    private ProductImageService $images;
    private ProductRepository $productRepo;
    private CategoryRepository $categoryRepo;
    private ProductImageRepository $imageRepo;

    protected function setUp(): void
    {
        // Assigné AVANT le skip : PHPUnit exécute tearDown() même quand setUp() saute le test.
        $this->mediaDir = sys_get_temp_dir() . '/shop_media_' . bin2hex(random_bytes(4));
        $db = TestDatabase::create();
        if ($db === null) {
            $this->markTestSkipped('TEST_DB_NAME non défini : test d\'intégration ignoré.');
        }
        $this->db = $db;
        $this->cleanDatabase();
        $translator = new Translator(dirname(__DIR__, 2) . '/lang', 'fr');
        $validator = new Validator($translator, new PhoneNumber('213', ['/^0[567]\d{8}$/']));
        $this->audit = new RecordingAudit();
        $processor = new ImageProcessor($this->mediaDir, false);

        $this->categoryRepo = new CategoryRepository($this->db);
        $this->productRepo = new ProductRepository($this->db);
        $this->imageRepo = new ProductImageRepository($this->db);
        $variantRepo = new VariantRepository($this->db);

        $this->categories = new CategoryService($this->categoryRepo, $validator, $translator, $this->audit);
        $this->products = new ProductService($this->productRepo, $this->categoryRepo, $this->imageRepo, $processor, $validator, $translator, $this->audit);
        $this->variants = new VariantService($variantRepo, $validator, $translator, $this->audit);
        $this->images = new ProductImageService($this->imageRepo, $processor, $this->db, $this->audit);
    }

    /**
     * Vide le catalogue en respectant les clés étrangères (aucune n'est désactivée) :
     * enfants avant parents (la FK auto-référencée de categories est en ON DELETE RESTRICT).
     * Les catégories sont limitées à deux niveaux : supprimer d'abord les sous-catégories suffit.
     */
    private function cleanDatabase(): void
    {
        foreach (['product_variants', 'product_images', 'products'] as $table) {
            $this->db->execute('DELETE FROM ' . $table);
        }
        $this->db->execute('DELETE FROM categories WHERE parent_id IS NOT NULL');
        $this->db->execute('DELETE FROM categories');
        $this->db->execute('DELETE FROM audit_log');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->mediaDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->mediaDir);
    }

    private function category(string $name = 'Robes', ?int $parent = null): int
    {
        $result = $this->categories->save(null, ['name_fr' => $name, 'parent_id' => $parent === null ? '' : (string) $parent, 'is_active' => '1'], 1, '127.0.0.1');
        $this->assertSame([], $result['errors']);

        return (int) $result['id'];
    }

    /** @param array<string,mixed> $overrides */
    private function product(int $categoryId, array $overrides = []): int
    {
        $result = $this->products->save(null, $overrides + [
            'name_fr' => 'Robe d\'été', 'category_id' => (string) $categoryId, 'price' => '1500', 'stock' => '5', 'is_active' => '1',
        ], 1, '127.0.0.1');
        $this->assertSame([], $result['errors']);

        return (int) $result['id'];
    }

    // ---------- Catégories ----------

    public function testCategorySlugsAreUniqueAndAscii(): void
    {
        $a = $this->category('Chaussures été');
        $b = $this->category('Chaussures été');

        $this->assertSame('chaussures-ete', $this->categoryRepo->find($a)['slug']);
        $this->assertSame('chaussures-ete-2', $this->categoryRepo->find($b)['slug']);
    }

    public function testCategoriesAreLimitedToTwoLevels(): void
    {
        $top = $this->category('Femme');
        $child = $this->category('Robes', $top);

        $this->assertNotNull($child);
        $result = $this->categories->save(null, ['name_fr' => 'Mini robes', 'parent_id' => (string) $child], 1, '127.0.0.1');
        $this->assertArrayHasKey('parent_id', $result['errors']);

        $this->assertArrayHasKey('parent_id', $this->categories->save($top, ['name_fr' => 'Femme', 'parent_id' => (string) $top], 1, '127.0.0.1')['errors']);
        $this->assertArrayHasKey('parent_id', $this->categories->save($top, ['name_fr' => 'Femme', 'parent_id' => (string) $child], 1, '127.0.0.1')['errors']);
        $this->assertArrayHasKey('parent_id', $this->categories->save(null, ['name_fr' => 'X', 'parent_id' => '99999'], 1, '127.0.0.1')['errors']);
    }

    public function testCategoryTreeStructure(): void
    {
        $top = $this->category('Femme');
        $this->category('Robes', $top);
        $this->category('Hommes');

        $tree = $this->categories->tree();

        $this->assertCount(2, $tree);
        $femme = array_values(array_filter($tree, static fn (array $c): bool => $c['name_fr'] === 'Femme'))[0];
        $this->assertCount(1, $femme['children']);
    }

    public function testCategoryDeletionRules(): void
    {
        $top = $this->category('Femme');
        $child = $this->category('Robes', $top);
        $this->product($child);

        $this->assertSame('category.error.has_children', $this->categories->delete($top, 1, '127.0.0.1'));
        $this->assertSame('category.error.has_products', $this->categories->delete($child, 1, '127.0.0.1'));

        $empty = $this->category('Vide');
        $this->assertNull($this->categories->delete($empty, 1, '127.0.0.1'));
        $this->assertNull($this->categoryRepo->find($empty));
    }

    // ---------- Produits ----------

    public function testProductIsCreatedWithNormalizedPrices(): void
    {
        $category = $this->category();
        $id = $this->product($category, ['price' => '1 500,5', 'promo_price' => '1200', 'stock' => '12']);
        $row = $this->productRepo->find($id);

        $this->assertSame('1500.50', $row['price']);
        $this->assertSame('1200.00', $row['promo_price']);
        $this->assertSame(12, (int) $row['stock']);
        $this->assertSame('robe-d-ete', $row['slug']);
        $this->assertContains('product.created', $this->audit->actions());
    }

    public function testInvalidProductInputsAreRejected(): void
    {
        $category = $this->category();
        $base = ['name_fr' => 'Robe', 'category_id' => (string) $category, 'price' => '1500', 'stock' => '5'];

        $cases = [
            'prix invalide' => [['price' => 'abc'], 'price'],
            'prix négatif' => [['price' => '-10'], 'price'],
            'promo >= prix' => [['promo_price' => '1500'], 'promo_price'],
            'promo > prix' => [['promo_price' => '2000'], 'promo_price'],
            'stock négatif' => [['stock' => '-1'], 'stock'],
            'stock décimal' => [['stock' => '1.5'], 'stock'],
            'stock énorme' => [['stock' => '99999999'], 'stock'],
            'catégorie inconnue' => [['category_id' => '99999'], 'category_id'],
            'nom vide' => [['name_fr' => ''], 'name_fr'],
            'nom trop long' => [['name_fr' => str_repeat('a', 201)], 'name_fr'],
        ];
        foreach ($cases as $label => [$override, $field]) {
            $result = $this->products->save(null, $override + $base, 1, '127.0.0.1');
            $this->assertArrayHasKey($field, $result['errors'], $label);
            $this->assertNull($result['id'], $label);
        }
        $this->assertSame(0, $this->productRepo->search([], 'newest', 1, 20)['total']);
    }

    public function testUpdateKeepsSlugAndToggleDelete(): void
    {
        $category = $this->category();
        $id = $this->product($category);

        $result = $this->products->save($id, ['name_fr' => 'Nouveau nom', 'category_id' => (string) $category, 'price' => '2000', 'stock' => '1'], 1, '127.0.0.1');
        $this->assertSame([], $result['errors']);
        $row = $this->productRepo->find($id);
        $this->assertSame('robe-d-ete', $row['slug']); // adresse stable
        $this->assertSame('Nouveau nom', $row['name_fr']);
        $this->assertSame(0, (int) $row['is_active']); // case décochée dans l'update

        $this->assertTrue($this->products->toggleActive($id, 1, '127.0.0.1'));
        $this->assertSame(1, (int) $this->productRepo->find($id)['is_active']);

        $this->assertTrue($this->products->delete($id, 1, '127.0.0.1'));
        $this->assertNull($this->productRepo->find($id));
        $this->assertFalse($this->products->delete($id, 1, '127.0.0.1'));
    }

    public function testDatabaseConstraintsRejectInvalidRowsEvenWithoutPhpValidation(): void
    {
        $category = $this->category();
        $base = [
            'category_id' => $category, 'name_fr' => 'X', 'name_ar' => null, 'slug' => 'x', 'description_fr' => null, 'description_ar' => null,
            'price' => '10.00', 'promo_price' => null, 'stock' => 1, 'is_active' => 1, 'is_featured' => 0,
        ];

        $this->expectException(\PDOException::class);
        $this->productRepo->create(['stock' => -1] + $base); // INT UNSIGNED + mode strict
    }

    public function testSearchFiltersSortingAndPagination(): void
    {
        $top = $this->category('Femme');
        $child = $this->category('Robes', $top);
        $other = $this->category('Hommes');
        for ($i = 1; $i <= 25; $i++) {
            $this->product($child, ['name_fr' => sprintf('Robe %02d', $i), 'price' => (string) (100 + $i)]);
        }
        $this->product($other, ['name_fr' => 'Chemise', 'price' => '50', 'is_active' => '0']);

        $page1 = $this->productRepo->search(['category_id' => $top], 'name', 1, 20);
        $page2 = $this->productRepo->search(['category_id' => $top], 'name', 2, 20);
        $this->assertSame(25, $page1['total']); // le filtre sur la catégorie principale inclut ses sous-catégories
        $this->assertCount(20, $page1['items']);
        $this->assertCount(5, $page2['items']);
        $this->assertSame('Robe 01', $page1['items'][0]['name_fr']);

        $this->assertSame(1, $this->productRepo->search(['status' => 'inactive'], 'newest', 1, 20)['total']);
        $this->assertSame('Robe 25', $this->productRepo->search([], 'price_desc', 1, 20)['items'][0]['name_fr']);
        $this->assertSame('Robe 01', $this->productRepo->search(['q' => 'Robe'], 'price_asc', 1, 20)['items'][0]['name_fr']);
    }

    public function testSearchIsSafeAgainstInjectionWildcardsAndBadSort(): void
    {
        $category = $this->category();
        $this->product($category, ['name_fr' => 'Promo 50% off']);
        $this->product($category, ['name_fr' => 'Promo 50 off']);

        $this->assertSame(0, $this->productRepo->search(['q' => "x' OR '1'='1"], 'newest', 1, 20)['total']);
        $this->assertSame(1, $this->productRepo->search(['q' => '%'], 'newest', 1, 20)['total']); // % littéral
        $this->assertSame(1, $this->productRepo->search(['q' => '50%'], 'newest', 1, 20)['total']);
        $this->assertSame(2, $this->productRepo->search([], 'name; DROP TABLE products', 1, 20)['total']); // tri hors liste blanche
        $this->assertSame(2, $this->productRepo->search([], 'newest', 1, 20)['total']);
    }

    // ---------- Variantes ----------

    public function testVariantRulesAndIdorProtection(): void
    {
        $category = $this->category();
        $p1 = $this->product($category);
        $p2 = $this->product($category, ['name_fr' => 'Autre']);

        $ok = $this->variants->save($p1, null, ['label_fr' => 'M / Noir', 'sku' => 'ROBE-M-N', 'price_override' => '1700', 'stock' => '3', 'is_active' => '1'], 1, '127.0.0.1');
        $this->assertSame([], $ok['errors']);

        $dup = $this->variants->save($p2, null, ['label_fr' => 'L', 'sku' => 'ROBE-M-N', 'stock' => '1'], 1, '127.0.0.1');
        $this->assertArrayHasKey('sku', $dup['errors']);
        $this->assertArrayHasKey('sku', $this->variants->save($p1, null, ['label_fr' => 'L', 'sku' => 'a b', 'stock' => '1'], 1, '127.0.0.1')['errors']);
        $this->assertArrayHasKey('price_override', $this->variants->save($p1, null, ['label_fr' => 'L', 'price_override' => 'x', 'stock' => '1'], 1, '127.0.0.1')['errors']);
        $this->assertArrayHasKey('stock', $this->variants->save($p1, null, ['label_fr' => 'L', 'stock' => '-3'], 1, '127.0.0.1')['errors']);

        $variantId = (int) $ok['id'];
        $this->assertFalse($this->variants->delete($p2, $variantId, 1, '127.0.0.1')); // variante d'un autre produit
        $this->assertTrue($this->variants->delete($p1, $variantId, 1, '127.0.0.1'));
    }

    public function testSameVariantCanKeepItsOwnSkuOnUpdate(): void
    {
        $p = $this->product($this->category());
        $id = (int) $this->variants->save($p, null, ['label_fr' => 'M', 'sku' => 'SKU-1', 'stock' => '1'], 1, '127.0.0.1')['id'];

        $this->assertSame([], $this->variants->save($p, $id, ['label_fr' => 'M modifié', 'sku' => 'SKU-1', 'stock' => '2'], 1, '127.0.0.1')['errors']);
    }

    public function testVariantsAreCappedPerProduct(): void
    {
        $p = $this->product($this->category());
        for ($i = 1; $i <= VariantService::MAX_PER_PRODUCT; $i++) {
            $this->assertSame([], $this->variants->save($p, null, ['label_fr' => 'V' . $i, 'stock' => '1'], 1, '127.0.0.1')['errors']);
        }

        $this->assertArrayHasKey('label_fr', $this->variants->save($p, null, ['label_fr' => 'de trop', 'stock' => '1'], 1, '127.0.0.1')['errors']);
    }

    // ---------- Images ----------

    public function testImageLifecycleMainSelectionAndCleanup(): void
    {
        if (!TestImages::available()) {
            $this->markTestSkipped('Extensions GD et fileinfo requises.');
        }
        $p = $this->product($this->category());

        $result = $this->images->upload($p, [
            TestImages::upload(TestImages::png(300, 300), 'a.png'),
            TestImages::upload(TestImages::jpeg(400, 300), 'b.jpg'),
        ], 1, '127.0.0.1');
        $this->assertSame(2, $result['uploaded']);
        $this->assertSame([], $result['errors']);

        $list = $this->images->list($p);
        $this->assertSame(1, (int) $list[0]['is_main']); // la première image devient principale
        $second = (int) $list[1]['id'];
        $firstFile = (string) $list[0]['file_id'];
        $this->assertFileExists($this->mediaDir . '/' . $firstFile . '.jpg');

        $this->assertTrue($this->images->setMain($p, $second, 1, '127.0.0.1'));
        $mains = array_filter($this->images->list($p), static fn (array $i): bool => (int) $i['is_main'] === 1);
        $this->assertCount(1, $mains);
        $this->assertSame($second, (int) array_values($mains)[0]['id']);

        // Suppression de l'image principale : l'autre est promue, les fichiers disparaissent
        $this->assertTrue($this->images->delete($p, $second, 1, '127.0.0.1'));
        $remaining = $this->images->list($p);
        $this->assertCount(1, $remaining);
        $this->assertSame(1, (int) $remaining[0]['is_main']);

        // Suppression du produit : les fichiers restants disparaissent aussi
        $remainingFile = (string) $remaining[0]['file_id'];
        $this->assertFileExists($this->mediaDir . '/' . $remainingFile . '.jpg');
        $this->products->delete($p, 1, '127.0.0.1');
        $this->assertFileDoesNotExist($this->mediaDir . '/' . $remainingFile . '.jpg');
        $this->assertFileDoesNotExist($this->mediaDir . '/' . $remainingFile . '_t.jpg');
    }

    public function testImageActionsCannotCrossProducts(): void
    {
        if (!TestImages::available()) {
            $this->markTestSkipped('Extensions GD et fileinfo requises.');
        }
        $category = $this->category();
        $p1 = $this->product($category);
        $p2 = $this->product($category, ['name_fr' => 'Autre']);
        $this->images->upload($p1, [TestImages::upload(TestImages::png(300, 300))], 1, '127.0.0.1');
        $imageId = (int) $this->images->list($p1)[0]['id'];

        $this->assertFalse($this->images->delete($p2, $imageId, 1, '127.0.0.1'));
        $this->assertFalse($this->images->setMain($p2, $imageId, 1, '127.0.0.1'));
        $this->assertCount(1, $this->images->list($p1));
    }

    public function testInvalidUploadsStoreNothing(): void
    {
        if (!TestImages::available()) {
            $this->markTestSkipped('Extensions GD et fileinfo requises.');
        }
        $p = $this->product($this->category());

        $result = $this->images->upload($p, [TestImages::upload(TestImages::raw('<?php echo 1;'), 'shell.php.jpg')], 1, '127.0.0.1');

        $this->assertSame(0, $result['uploaded']);
        $this->assertSame(['upload.invalid_type'], $result['errors']);
        $this->assertSame([], $this->images->list($p));
        $this->assertSame([], glob($this->mediaDir . '/*') ?: []);

        $none = $this->images->upload($p, [TestImages::upload('', '', UPLOAD_ERR_NO_FILE)], 1, '127.0.0.1');
        $this->assertSame(['upload.none'], $none['errors']);
    }

    public function testImageCountIsCappedPerProduct(): void
    {
        if (!TestImages::available()) {
            $this->markTestSkipped('Extensions GD et fileinfo requises.');
        }
        $p = $this->product($this->category());
        $files = [];
        for ($i = 0; $i < ProductImageService::MAX_PER_PRODUCT + 2; $i++) {
            $files[] = TestImages::upload(TestImages::png(120, 120));
        }

        $result = $this->images->upload($p, $files, 1, '127.0.0.1');

        $this->assertSame(ProductImageService::MAX_PER_PRODUCT, $result['uploaded']);
        $this->assertContains('upload.too_many', $result['errors']);
        $this->assertSame(ProductImageService::MAX_PER_PRODUCT, $this->imageRepo->countForProduct($p));
    }
}
