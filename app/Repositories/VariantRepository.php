<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class VariantRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return list<array<string,mixed>> */
    public function listForProduct(int $productId): array
    {
        return $this->db->fetchAll(
            'SELECT id, product_id, label_fr, label_ar, sku, price_override, stock, is_active, sort_order
             FROM product_variants WHERE product_id = :id ORDER BY sort_order, id',
            ['id' => $productId],
        );
    }

    /**
     * Lié au produit (anti-IDOR).
     *
     * @return array<string,mixed>|null
     */
    public function find(int $productId, int $variantId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM product_variants WHERE id = :id AND product_id = :product_id LIMIT 1',
            ['id' => $variantId, 'product_id' => $productId],
        );
    }

    public function countForProduct(int $productId): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM product_variants WHERE product_id = :id', ['id' => $productId]);
    }

    /** @param array<string,mixed> $data product_id, label_fr, label_ar, sku, price_override, stock, is_active */
    public function create(array $data): int
    {
        $this->db->execute(
            'INSERT INTO product_variants (product_id, label_fr, label_ar, sku, price_override, stock, is_active)
             VALUES (:product_id, :label_fr, :label_ar, :sku, :price_override, :stock, :is_active)',
            $data,
        );

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string,mixed> $data label_fr, label_ar, sku, price_override, stock, is_active */
    public function update(int $variantId, array $data): void
    {
        $this->db->execute(
            'UPDATE product_variants
             SET label_fr = :label_fr, label_ar = :label_ar, sku = :sku, price_override = :price_override,
                 stock = :stock, is_active = :is_active
             WHERE id = :id',
            $data + ['id' => $variantId],
        );
    }

    public function delete(int $variantId): void
    {
        $this->db->execute('DELETE FROM product_variants WHERE id = :id', ['id' => $variantId]);
    }

    public function skuExists(string $sku, int $ignoreVariantId): bool
    {
        return $this->db->fetchValue(
            'SELECT 1 FROM product_variants WHERE sku = :sku AND id <> :id LIMIT 1',
            ['sku' => $sku, 'id' => $ignoreVariantId],
        ) !== false;
    }
}
