<?php

declare(strict_types=1);

namespace App\Services;

/** Utilitaires SEO : extraits de description et données structurées schema.org. */
final class SeoService
{
    public function __construct(private string $currencyCode)
    {
    }

    /** Texte brut condensé pour <meta name="description"> (sans retours à la ligne, tronqué proprement). */
    public function excerpt(string $text, int $maxLength = 155): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $maxLength - 1)) . '…';
    }

    /**
     * @param array<string,mixed> $product ligne produit (price, promo_price, available_stock)
     * @return array<string,mixed>
     */
    public function productJsonLd(array $product, string $name, string $description, string $url, ?string $imageUrl): array
    {
        $price = $product['promo_price'] ?? $product['price'];
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $name,
            'url' => $url,
            'offers' => [
                '@type' => 'Offer',
                'url' => $url,
                'price' => (string) $price,
                'priceCurrency' => $this->currencyCode,
                'availability' => (int) $product['available_stock'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            ],
        ];
        if ($description !== '') {
            $data['description'] = $description;
        }
        if ($imageUrl !== null) {
            $data['image'] = $imageUrl;
        }

        return $data;
    }
}
