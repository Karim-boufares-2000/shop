<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class ProductImageRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return list<array<string,mixed>> */
    public function listForProduct(int $productId): array
    {
        return $this->db->fetchAll(
            'SELECT id, product_id, file_id, is_main, sort_order FROM product_images
             WHERE product_id = :product_id ORDER BY is_main DESC, sort_order, id',
            ['product_id' => $productId],
        );
    }

    /**
     * Recherche liée au produit : empêche d'agir sur l'image d'un autre produit (IDOR).
     *
     * @return array<string,mixed>|null
     */
    public function find(int $productId, int $imageId): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, product_id, file_id, is_main FROM product_images WHERE id = :id AND product_id = :product_id LIMIT 1',
            ['id' => $imageId, 'product_id' => $productId],
        );
    }

    public function countForProduct(int $productId): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM product_images WHERE product_id = :id', ['id' => $productId]);
    }

    public function create(int $productId, string $fileId, bool $isMain, int $sortOrder): int
    {
        $this->db->execute(
            'INSERT INTO product_images (product_id, file_id, is_main, sort_order) VALUES (:product_id, :file_id, :is_main, :sort_order)',
            ['product_id' => $productId, 'file_id' => $fileId, 'is_main' => $isMain ? 1 : 0, 'sort_order' => $sortOrder],
        );

        return (int) $this->db->lastInsertId();
    }

    public function delete(int $imageId): void
    {
        $this->db->execute('DELETE FROM product_images WHERE id = :id', ['id' => $imageId]);
    }

    public function clearMain(int $productId): void
    {
        $this->db->execute('UPDATE product_images SET is_main = 0 WHERE product_id = :id', ['id' => $productId]);
    }

    public function setMain(int $imageId): void
    {
        $this->db->execute('UPDATE product_images SET is_main = 1 WHERE id = :id', ['id' => $imageId]);
    }

    /** @return int|null identifiant de la première image (par ordre) d'un produit */
    public function firstId(int $productId): ?int
    {
        $id = $this->db->fetchValue(
            'SELECT id FROM product_images WHERE product_id = :id ORDER BY sort_order, id LIMIT 1',
            ['id' => $productId],
        );

        return $id === false ? null : (int) $id;
    }

    /** @return list<string> identifiants de fichiers d'un produit (avant suppression du produit) */
    public function fileIds(int $productId): array
    {
        return array_map(
            static fn (array $row): string => (string) $row['file_id'],
            $this->db->fetchAll('SELECT file_id FROM product_images WHERE product_id = :id', ['id' => $productId]),
        );
    }
}
