<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\ListingFilters;

/**
 * Lectures de la boutique PUBLIQUE. Règle centrale (VISIBLE) : un produit n'est visible que s'il est actif
 * ET que sa catégorie est active ET (sous-catégorie) que sa catégorie parente est active.
 * Disponibilité : somme des stocks des variantes actives s'il y en a, sinon stock du produit.
 */
final class StorefrontRepository
{
    private const VISIBLE = 'p.is_active = 1 AND c.is_active = 1
        AND (c.parent_id IS NULL OR EXISTS (SELECT 1 FROM categories pcx WHERE pcx.id = c.parent_id AND pcx.is_active = 1))';
    private const AVAILABLE = 'CASE WHEN EXISTS (SELECT 1 FROM product_variants av WHERE av.product_id = p.id AND av.is_active = 1)
        THEN (SELECT COALESCE(SUM(sv.stock), 0) FROM product_variants sv WHERE sv.product_id = p.id AND sv.is_active = 1)
        ELSE p.stock END';
    private const MAIN_IMAGE = '(SELECT i.file_id FROM product_images i WHERE i.product_id = p.id ORDER BY i.is_main DESC, i.sort_order, i.id LIMIT 1)';
    private const EFFECTIVE_PRICE = 'COALESCE(p.promo_price, p.price)';
    private const FROM = 'FROM products p JOIN categories c ON c.id = p.category_id';

    public function __construct(private Database $db)
    {
    }

    /**
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function listing(ListingFilters $filters, int $page, int $perPage): array
    {
        $where = [self::VISIBLE];
        $params = [];

        if ($filters->q !== '') {
            $where[] = '(p.name_fr LIKE :q_fr OR p.name_ar LIKE :q_ar)';
            $params['q_fr'] = $params['q_ar'] = '%' . $this->escapeLike($filters->q) . '%';
        }
        if ($filters->category !== null) {
            $where[] = 'p.category_id IN (SELECT cc.id FROM categories cc WHERE cc.slug = :cat_slug
                OR cc.parent_id IN (SELECT pp.id FROM categories pp WHERE pp.slug = :cat_parent))';
            $params['cat_slug'] = $params['cat_parent'] = $filters->category;
        }
        if ($filters->minPrice !== null) {
            $where[] = self::EFFECTIVE_PRICE . ' >= :min_price';
            $params['min_price'] = $filters->minPrice;
        }
        if ($filters->maxPrice !== null) {
            $where[] = self::EFFECTIVE_PRICE . ' <= :max_price';
            $params['max_price'] = $filters->maxPrice;
        }
        if ($filters->inStock) {
            $where[] = '(' . self::AVAILABLE . ') > 0';
        }
        if ($filters->onlyPromo) {
            $where[] = 'p.promo_price IS NOT NULL';
        }
        $whereSql = implode(' AND ', $where);

        $orderParams = [];
        switch ($filters->effectiveSort()) {
            case 'relevance':
                $order = 'CASE WHEN p.name_fr LIKE :rel_fr OR p.name_ar LIKE :rel_ar THEN 0 ELSE 1 END, p.id DESC';
                $orderParams = ['rel_fr' => $this->escapeLike($filters->q) . '%', 'rel_ar' => $this->escapeLike($filters->q) . '%'];
                break;
            case 'price_asc':
                $order = self::EFFECTIVE_PRICE . ' ASC, p.id DESC';
                break;
            case 'price_desc':
                $order = self::EFFECTIVE_PRICE . ' DESC, p.id DESC';
                break;
            case 'name':
                $order = 'p.name_fr ASC, p.id DESC';
                break;
            default:
                $order = 'p.id DESC';
        }

        $limit = max(1, $perPage);
        $offset = max(0, ($page - 1) * $limit);
        $total = (int) $this->db->fetchValue('SELECT COUNT(*) ' . self::FROM . ' WHERE ' . $whereSql, $params);
        // LIMIT/OFFSET : entiers castés (les paramètres liés en chaîne sont refusés par les requêtes préparées natives).
        $rows = $this->db->fetchAll(
            'SELECT ' . $this->cardColumns() . ' ' . self::FROM . ' WHERE ' . $whereSql
            . ' ORDER BY ' . $order . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params + $orderParams,
        );

        return ['items' => $this->normalize($rows), 'total' => $total];
    }

    /** @return list<array<string,mixed>> */
    public function featured(int $limit): array
    {
        return $this->simpleList('p.is_featured = 1', 'p.id DESC', $limit);
    }

    /** @return list<array<string,mixed>> */
    public function latest(int $limit): array
    {
        return $this->simpleList('1 = 1', 'p.id DESC', $limit);
    }

    /** @return list<array<string,mixed>> */
    public function onSale(int $limit): array
    {
        return $this->simpleList('p.promo_price IS NOT NULL', 'p.id DESC', $limit);
    }

    /** @return list<array<string,mixed>> */
    public function similar(int $productId, int $categoryId, int $limit): array
    {
        return $this->simpleList('p.category_id = :similar_cat AND p.id <> :similar_id', 'p.is_featured DESC, p.id DESC', $limit, [
            'similar_cat' => $categoryId,
            'similar_id' => $productId,
        ]);
    }

    /** @return array<string,mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT p.*, c.slug AS category_slug, c.name_fr AS category_name_fr, c.name_ar AS category_name_ar,
                    pc.slug AS parent_slug, pc.name_fr AS parent_name_fr, pc.name_ar AS parent_name_ar,
                    (' . self::AVAILABLE . ') AS available_stock
             ' . self::FROM . ' LEFT JOIN categories pc ON pc.id = c.parent_id
             WHERE p.slug = :slug AND ' . self::VISIBLE . ' LIMIT 1',
            ['slug' => $slug],
        );
        if ($row === null) {
            return null;
        }
        $row['available_stock'] = (int) $row['available_stock'];

        return $row;
    }

    /** @return list<array<string,mixed>> */
    public function images(int $productId): array
    {
        return $this->db->fetchAll(
            'SELECT id, file_id, is_main FROM product_images WHERE product_id = :id ORDER BY is_main DESC, sort_order, id',
            ['id' => $productId],
        );
    }

    /** @return list<array<string,mixed>> variantes actives uniquement */
    public function variants(int $productId): array
    {
        return $this->db->fetchAll(
            'SELECT id, label_fr, label_ar, price_override, stock FROM product_variants
             WHERE product_id = :id AND is_active = 1 ORDER BY sort_order, id',
            ['id' => $productId],
        );
    }

    /** @return array<string,mixed>|null catégorie visible (active, parent actif) */
    public function findCategoryBySlug(string $slug): ?array
    {
        return $this->db->fetchOne(
            'SELECT c.id, c.slug, c.name_fr, c.name_ar, c.parent_id, c.updated_at,
                    pc.slug AS parent_slug, pc.name_fr AS parent_name_fr, pc.name_ar AS parent_name_ar
             FROM categories c LEFT JOIN categories pc ON pc.id = c.parent_id
             WHERE c.slug = :slug AND c.is_active = 1 AND (c.parent_id IS NULL OR pc.is_active = 1) LIMIT 1',
            ['slug' => $slug],
        );
    }

    /**
     * Catégories actives en arbre à deux niveaux ; products_count = produits actifs (les principales incluent leurs enfants).
     *
     * @return list<array<string,mixed>>
     */
    public function categoryTree(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT c.id, c.parent_id, c.slug, c.name_fr, c.name_ar,
                    (SELECT COUNT(*) FROM products p WHERE p.is_active = 1 AND p.category_id = c.id) AS products_count
             FROM categories c WHERE c.is_active = 1 ORDER BY c.sort_order, c.name_fr, c.id',
        );
        $children = [];
        foreach ($rows as $row) {
            if ($row['parent_id'] !== null) {
                $children[(int) $row['parent_id']][] = $row;
            }
        }
        $tree = [];
        foreach ($rows as $row) {
            if ($row['parent_id'] !== null) {
                continue;
            }
            $row['children'] = $children[(int) $row['id']] ?? [];
            $row['products_count'] = (int) $row['products_count'] + array_sum(array_map(static fn (array $c): int => (int) $c['products_count'], $row['children']));
            $tree[] = $row;
        }

        return $tree;
    }

    /**
     * Entrées du plan du site : uniquement du contenu visible.
     *
     * @return array{products:list<array<string,mixed>>,categories:list<array<string,mixed>>}
     */
    public function sitemapEntries(): array
    {
        return [
            'products' => $this->db->fetchAll(
                'SELECT p.slug, p.updated_at ' . self::FROM . ' WHERE ' . self::VISIBLE . ' ORDER BY p.id LIMIT 45000',
            ),
            'categories' => $this->db->fetchAll(
                'SELECT c.slug, c.updated_at FROM categories c LEFT JOIN categories pc ON pc.id = c.parent_id
                 WHERE c.is_active = 1 AND (c.parent_id IS NULL OR pc.is_active = 1) ORDER BY c.id LIMIT 4000',
            ),
        ];
    }

    /**
     * @param array<string,mixed> $params
     * @return list<array<string,mixed>>
     */
    private function simpleList(string $condition, string $order, int $limit, array $params = []): array
    {
        return $this->normalize($this->db->fetchAll(
            'SELECT ' . $this->cardColumns() . ' ' . self::FROM . ' WHERE ' . self::VISIBLE . ' AND ' . $condition
            . ' ORDER BY ' . $order . ' LIMIT ' . max(1, (int) $limit),
            $params,
        ));
    }

    private function cardColumns(): string
    {
        return 'p.id, p.slug, p.name_fr, p.name_ar, p.price, p.promo_price, p.is_featured, p.created_at,
                c.slug AS category_slug, c.name_fr AS category_name_fr, c.name_ar AS category_name_ar,
                (' . self::AVAILABLE . ') AS available_stock, ' . self::MAIN_IMAGE . ' AS main_image';
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function normalize(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['available_stock'] = (int) $row['available_stock'];
        }

        return $rows;
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
