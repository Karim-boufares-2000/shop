<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\ProductImageRepository;

final class ProductImageService
{
    public const MAX_PER_PRODUCT = 10;

    public function __construct(
        private ProductImageRepository $images,
        private ImageProcessor $processor,
        private Database $db,
        private AuditLoggerInterface $audit,
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function list(int $productId): array
    {
        return $this->images->listForProduct($productId);
    }

    /**
     * Le produit doit avoir été vérifié par l'appelant.
     *
     * @param list<array{name:string,tmp_name:string,size:int,error:int}> $files
     * @return array{uploaded:int,errors:list<string>} clés de traduction des erreurs
     */
    public function upload(int $productId, array $files, int $adminId, string $ip): array
    {
        $count = $this->images->countForProduct($productId);
        $uploaded = 0;
        $errors = [];

        foreach ($files as $file) {
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($count + $uploaded >= self::MAX_PER_PRODUCT) {
                $errors[] = 'upload.too_many';
                break;
            }
            try {
                $fileId = $this->processor->store($file);
            } catch (UploadException $e) {
                $errors[] = $e->key();
                continue;
            }
            try {
                $isMain = ($count + $uploaded) === 0;
                $this->images->create($productId, $fileId, $isMain, $count + $uploaded);
            } catch (\Throwable $e) {
                $this->processor->delete($fileId); // pas de fichier orphelin si l'écriture en base échoue
                throw $e;
            }
            $uploaded++;
        }

        if ($uploaded === 0 && $errors === []) {
            $errors[] = 'upload.none';
        }
        if ($uploaded > 0) {
            $this->audit->log('product.images_added', $adminId, 'product', (string) $productId, ['count' => $uploaded], $ip);
        }

        return ['uploaded' => $uploaded, 'errors' => array_values(array_unique($errors))];
    }

    public function delete(int $productId, int $imageId, int $adminId, string $ip): bool
    {
        $image = $this->images->find($productId, $imageId);
        if ($image === null) {
            return false;
        }
        $this->db->transaction(function () use ($productId, $imageId, $image): void {
            $this->images->delete($imageId);
            if ((int) $image['is_main'] === 1) {
                $next = $this->images->firstId($productId);
                if ($next !== null) {
                    $this->images->setMain($next);
                }
            }
        });
        $this->processor->delete((string) $image['file_id']);
        $this->audit->log('product.image_deleted', $adminId, 'product', (string) $productId, ['image_id' => $imageId], $ip);

        return true;
    }

    public function setMain(int $productId, int $imageId, int $adminId, string $ip): bool
    {
        if ($this->images->find($productId, $imageId) === null) {
            return false;
        }
        $this->db->transaction(function () use ($productId, $imageId): void {
            $this->images->clearMain($productId);
            $this->images->setMain($imageId);
        });
        $this->audit->log('product.image_main', $adminId, 'product', (string) $productId, ['image_id' => $imageId], $ip);

        return true;
    }
}
