<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Translator;
use App\Repositories\VariantRepository;
use App\Validation\Validator;

final class VariantService
{
    public const MAX_PER_PRODUCT = 50;

    public function __construct(
        private VariantRepository $variants,
        private Validator $validator,
        private Translator $translator,
        private AuditLoggerInterface $audit,
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function list(int $productId): array
    {
        return $this->variants->listForProduct($productId);
    }

    /**
     * Le produit et (pour une modification) la variante doivent avoir été vérifiés par l'appelant.
     *
     * @param array<string,mixed> $input
     * @return array{id:?int,errors:array<string,string>}
     */
    public function save(int $productId, ?int $variantId, array $input, int $adminId, string $ip): array
    {
        $result = $this->validator->validate($input, [
            'label_fr' => 'required|string|min:1|max:100',
            'label_ar' => 'nullable|string|max:100',
            'sku' => 'nullable|string|max:64|regex:/^[A-Za-z0-9._-]+$/',
            'price_override' => 'nullable|money',
            'stock' => 'required|int|min_value:0|max_value:1000000',
        ]);
        $errors = $result->firstErrors();
        $data = $result->validated();

        $sku = isset($data['sku']) ? (string) $data['sku'] : null;
        if (!isset($errors['sku']) && $sku !== null && $this->variants->skuExists($sku, $variantId ?? 0)) {
            $errors['sku'] = $this->translator->get('variant.error.sku_taken');
        }
        if ($variantId === null && $this->variants->countForProduct($productId) >= self::MAX_PER_PRODUCT) {
            $errors['label_fr'] = $this->translator->get('variant.error.too_many');
        }
        if ($errors !== []) {
            return ['id' => null, 'errors' => $errors];
        }

        $fields = [
            'label_fr' => (string) $data['label_fr'],
            'label_ar' => $data['label_ar'] ?? null,
            'sku' => $sku,
            'price_override' => $data['price_override'] ?? null,
            'stock' => (int) $data['stock'],
            'is_active' => ($input['is_active'] ?? '') === '1' ? 1 : 0,
        ];

        if ($variantId === null) {
            $variantId = $this->variants->create($fields + ['product_id' => $productId]);
            $this->audit->log('variant.created', $adminId, 'product', (string) $productId, ['variant_id' => $variantId], $ip);
        } else {
            $this->variants->update($variantId, $fields);
            $this->audit->log('variant.updated', $adminId, 'product', (string) $productId, ['variant_id' => $variantId], $ip);
        }

        return ['id' => $variantId, 'errors' => []];
    }

    public function delete(int $productId, int $variantId, int $adminId, string $ip): bool
    {
        if ($this->variants->find($productId, $variantId) === null) {
            return false;
        }
        $this->variants->delete($variantId);
        $this->audit->log('variant.deleted', $adminId, 'product', (string) $productId, ['variant_id' => $variantId], $ip);

        return true;
    }
}
