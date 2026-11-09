<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class CategoryRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return list<array<string,mixed>> toutes les catégories avec le nombre de produits */
    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT c.id, c.parent_id, c.name_fr, c.name_ar, c.slug, c.is_active, c.sort_order,
                    (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS products_count
             FROM categories c
             ORDER BY c.sort_order, c.name_fr, c.id',
        );
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM categories WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    /** @param array<string,mixed> $data parent_id, name_fr, name_ar, slug, is_active, sort_order */
    public function create(array $data): int
    {
        $this->db->execute(
            'INSERT INTO categories (parent_id, name_fr, name_ar, slug, is_active, sort_order)
             VALUES (:parent_id, :name_fr, :name_ar, :slug, :is_active, :sort_order)',
            $data,
        );

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string,mixed> $data parent_id, name_fr, name_ar, is_active, sort_order (le slug ne change pas) */
    public function update(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE categories
             SET parent_id = :parent_id, name_fr = :name_fr, name_ar = :name_ar, is_active = :is_active, sort_order = :sort_order
             WHERE id = :id',
            $data + ['id' => $id],
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM categories WHERE id = :id', ['id' => $id]);
    }

    public function slugExists(string $slug): bool
    {
        return $this->db->fetchValue('SELECT 1 FROM categories WHERE slug = :slug LIMIT 1', ['slug' => $slug]) !== false;
    }

    public function countChildren(int $id): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM categories WHERE parent_id = :id', ['id' => $id]);
    }

    public function countProducts(int $id): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM products WHERE category_id = :id', ['id' => $id]);
    }
}
