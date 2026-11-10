<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductImageRepository;
use App\Repositories\ProductRepository;
use App\Repositories\VariantRepository;

/** Données de test du catalogue (base de TEST uniquement). */
final class CatalogFixtures
{
    private CategoryRepository $categories;
    private ProductRepository $products;
    private VariantRepository $variants;
    private ProductImageRepository $images;
    private int $counter = 0;

    public function __construct(private Database $db)
    {
        $this->categories = new CategoryRepository($db);
        $this->products = new ProductRepository($db);
        $this->variants = new VariantRepository($db);
        $this->images = new ProductImageRepository($db);
    }

    /** Vide le catalogue en respectant les clés étrangères (enfants avant parents). */
    public function clean(): void
    {
        foreach (['product_variants', 'product_images', 'products'] as $table) {
            $this->db->execute('DELETE FROM ' . $table);
        }
        $this->db->execute('DELETE FROM categories WHERE parent_id IS NOT NULL');
        $this->db->execute('DELETE FROM categories');
        $this->db->execute('DELETE FROM audit_log');
    }

    public function category(string $slug, string $nameFr, ?int $parentId = null, bool $active = true, ?string $nameAr = null): int
    {
        return $this->categories->create([
            'parent_id' => $parentId, 'name_fr' => $nameFr, 'name_ar' => $nameAr, 'slug' => $slug,
            'is_active' => $active ? 1 : 0, 'sort_order' => $this->counter++,
        ]);
    }

    public function product(
        int $categoryId,
        string $slug,
        string $nameFr,
        string $price = '1000.00',
        ?string $promo = null,
        int $stock = 5,
        bool $active = true,
        bool $featured = false,
        ?string $nameAr = null,
        ?string $descriptionFr = null,
    ): int {
        return $this->products->create([
            'category_id' => $categoryId, 'name_fr' => $nameFr, 'name_ar' => $nameAr, 'slug' => $slug,
            'description_fr' => $descriptionFr, 'description_ar' => null,
            'price' => $price, 'promo_price' => $promo, 'stock' => $stock,
            'is_active' => $active ? 1 : 0, 'is_featured' => $featured ? 1 : 0,
        ]);
    }

    public function variant(int $productId, string $label, int $stock, bool $active = true, ?string $priceOverride = null): int
    {
        return $this->variants->create([
            'product_id' => $productId, 'label_fr' => $label, 'label_ar' => null, 'sku' => null,
            'price_override' => $priceOverride, 'stock' => $stock, 'is_active' => $active ? 1 : 0,
        ]);
    }

    /** Enregistre une ligne d'image (sans fichier réel) : suffisant pour tester le rendu HTML. */
    public function image(int $productId, ?string $fileId = null): string
    {
        $fileId ??= bin2hex(random_bytes(16));
        $this->images->create($productId, $fileId, true, 0);

        return $fileId;
    }
}
