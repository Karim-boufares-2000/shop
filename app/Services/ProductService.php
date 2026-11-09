<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Translator;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductImageRepository;
use App\Repositories\ProductRepository;
use App\Support\Money;
use App\Support\Slugger;
use App\Validation\Validator;

final class ProductService
{
    public function __construct(
        private ProductRepository $products,
        private CategoryRepository $categories,
        private ProductImageRepository $images,
        private ImageProcessor $processor,
        private Validator $validator,
        private Translator $translator,
        private AuditLoggerInterface $audit,
    ) {
    }

    /**
     * @param array{q?:string,category_id?:int,status?:string} $filters
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function search(array $filters, string $sort, int $page, int $perPage): array
    {
        return $this->products->search($filters, $sort, $page, $perPage);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->products->find($id);
    }

    /**
     * @param array<string,mixed> $input
     * @return array{id:?int,errors:array<string,string>}
     */
    public function save(?int $id, array $input, int $adminId, string $ip): array
    {
        $result = $this->validator->validate($input, [
            'name_fr' => 'required|string|min:2|max:200',
            'name_ar' => 'nullable|string|max:200',
            'description_fr' => 'nullable|string|max:5000',
            'description_ar' => 'nullable|string|max:5000',
            'category_id' => 'required|int|min_value:1',
            'price' => 'required|money',
            'promo_price' => 'nullable|money',
            'stock' => 'required|int|min_value:0|max_value:1000000',
        ]);
        $errors = $result->firstErrors();
        $data = $result->validated();

        if (!isset($errors['category_id']) && $this->categories->find((int) $data['category_id']) === null) {
            $errors['category_id'] = $this->translator->get('product.error.category_invalid');
        }
        if (!isset($errors['price']) && !isset($errors['promo_price']) && ($data['promo_price'] ?? null) !== null) {
            if (Money::toCents((string) $data['promo_price']) >= Money::toCents((string) $data['price'])) {
                $errors['promo_price'] = $this->translator->get('product.error.promo_not_lower');
            }
        }
        if ($errors !== []) {
            return ['id' => null, 'errors' => $errors];
        }

        $fields = [
            'category_id' => (int) $data['category_id'],
            'name_fr' => (string) $data['name_fr'],
            'name_ar' => $data['name_ar'] ?? null,
            'description_fr' => $data['description_fr'] ?? null,
            'description_ar' => $data['description_ar'] ?? null,
            'price' => (string) $data['price'],
            'promo_price' => $data['promo_price'] ?? null,
            'stock' => (int) $data['stock'],
            'is_active' => ($input['is_active'] ?? '') === '1' ? 1 : 0,
            'is_featured' => ($input['is_featured'] ?? '') === '1' ? 1 : 0,
        ];

        if ($id === null) {
            $slug = Slugger::unique(Slugger::slugify($fields['name_fr'], 220), fn (string $s): bool => $this->products->slugExists($s), 220);
            $id = $this->products->create($fields + ['slug' => $slug]);
            $this->audit->log('product.created', $adminId, 'product', (string) $id, [], $ip);
        } else {
            $this->products->update($id, $fields);
            $this->audit->log('product.updated', $adminId, 'product', (string) $id, [], $ip);
        }

        return ['id' => $id, 'errors' => []];
    }

    public function toggleActive(int $id, int $adminId, string $ip): bool
    {
        if ($this->products->find($id) === null) {
            return false;
        }
        $this->products->toggleActive($id);
        $this->audit->log('product.toggled', $adminId, 'product', (string) $id, [], $ip);

        return true;
    }

    /** Supprime le produit (variantes et lignes d'images en cascade) puis ses fichiers image. */
    public function delete(int $id, int $adminId, string $ip): bool
    {
        if ($this->products->find($id) === null) {
            return false;
        }
        $fileIds = $this->images->fileIds($id);
        $this->products->delete($id);
        foreach ($fileIds as $fileId) {
            $this->processor->delete($fileId);
        }
        $this->audit->log('product.deleted', $adminId, 'product', (string) $id, [], $ip);

        return true;
    }
}
