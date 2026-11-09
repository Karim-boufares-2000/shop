<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class ProductRepository
{
    /** Liste blanche des tris : jamais de colonne issue directement de l'utilisateur. */
    public const SORTS = [
        'newest' => 'p.id DESC',
        'name' => 'p.name_fr ASC, p.id DESC',
        'price_asc' => 'p.price ASC, p.id DESC',
        'price_desc' => 'p.price DESC, p.id DESC',
        'stock_asc' => 'p.stock ASC, p.id DESC',
    ];

    public function __construct(private Database $db)
    {
    }

    /**
     * @param array{q?:string,category_id?:int,status?:string} $filters
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function search(array $filters, string $sort, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];

        $query = (string) ($filters['q'] ?? '');
        if ($query !== '') {
            $like = '%' . addcslashes($query, '%_\\') . '%';
            $where[] = '(p.name_fr LIKE :q_fr OR p.name_ar LIKE :q_ar)';
            $params['q_fr'] = $like;
            $params['q_ar'] = $like;
        }
        $categoryId = (int) ($filters['category_id'] ?? 0);
        if ($categoryId > 0) {
            $where[] = 'p.category_id IN (SELECT id FROM categories WHERE id = :cat_self OR parent_id = :cat_parent)';
            $params['cat_self'] = $categoryId;
            $params['cat_parent'] = $categoryId;
        }
        $status = (string) ($filters['status'] ?? '');
        if ($status === 'active') {
            $where[] = 'p.is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'p.is_active = 0';
        }

        $whereSql = implode(' AND ', $where);
        $order = self::SORTS[$sort] ?? self::SORTS['newest'];
        $limit = max(1, $perPage);
        $offset = max(0, ($page - 1) * $limit);

        $total = (int) $this->db->fetchValue('SELECT COUNT(*) FROM products p WHERE ' . $whereSql, $params);
        // LIMIT/OFFSET : entiers castés (les paramètres liés en chaîne sont refusés par les requêtes préparées natives).
        $items = $this->db->fetchAll(
            'SELECT p.id, p.category_id, p.name_fr, p.name_ar, p.slug, p.price, p.promo_price, p.stock, p.is_active, p.is_featured,
                    c.name_fr AS category_name,
                    (SELECT i.file_id FROM product_images i WHERE i.product_id = p.id ORDER BY i.is_main DESC, i.sort_order, i.id LIMIT 1) AS main_image,
                    (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id) AS variant_count
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE ' . $whereSql . '
             ORDER BY ' . $order . '
             LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params,
        );

        return ['items' => $items, 'total' => $total];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM products WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    /**
     * @param array<string,mixed> $data category_id, name_fr, name_ar, slug, description_fr, description_ar,
     *                                  price, promo_price, stock, is_active, is_featured
     */
    public function create(array $data): int
    {
        $this->db->execute(
            'INSERT INTO products (category_id, name_fr, name_ar, slug, description_fr, description_ar, price, promo_price, stock, is_active, is_featured)
             VALUES (:category_id, :name_fr, :name_ar, :slug, :description_fr, :description_ar, :price, :promo_price, :stock, :is_active, :is_featured)',
            $data,
        );

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string,mixed> $data mêmes clés que create(), sans slug (le slug est stable) */
    public function update(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE products
             SET category_id = :category_id, name_fr = :name_fr, name_ar = :name_ar,
                 description_fr = :description_fr, description_ar = :description_ar,
                 price = :price, promo_price = :promo_price, stock = :stock,
                 is_active = :is_active, is_featured = :is_featured
             WHERE id = :id',
            $data + ['id' => $id],
        );
    }

    public function toggleActive(int $id): void
    {
        $this->db->execute('UPDATE products SET is_active = 1 - is_active WHERE id = :id', ['id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM products WHERE id = :id', ['id' => $id]);
    }

    public function slugExists(string $slug): bool
    {
        return $this->db->fetchValue('SELECT 1 FROM products WHERE slug = :slug LIMIT 1', ['slug' => $slug]) !== false;
    }
}
