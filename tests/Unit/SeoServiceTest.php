<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\SeoService;
use PHPUnit\Framework\TestCase;

final class SeoServiceTest extends TestCase
{
    public function testExcerptCollapsesWhitespaceAndTruncatesOnCharacters(): void
    {
        $seo = new SeoService('DZD');

        $this->assertSame('Une robe élégante en coton.', $seo->excerpt("Une robe\n\n  élégante\ten coton. "));
        $long = $seo->excerpt(str_repeat('ع', 400), 155);
        $this->assertSame(155, mb_strlen($long));
        $this->assertStringEndsWith('…', $long);
    }

    public function testProductJsonLdUsesPromoPriceCurrencyAndAvailability(): void
    {
        $data = (new SeoService('DZD'))->productJsonLd(
            ['price' => '2000.00', 'promo_price' => '1500.00', 'available_stock' => 3],
            'Robe rouge',
            'Belle robe',
            'https://x.test/produit/robe-rouge',
            'https://x.test/media/products/a.jpg',
        );

        $this->assertSame('Product', $data['@type']);
        $this->assertSame('1500.00', $data['offers']['price']);
        $this->assertSame('DZD', $data['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $data['offers']['availability']);
        $this->assertSame('https://x.test/media/products/a.jpg', $data['image']);
    }

    public function testOutOfStockAndOptionalFields(): void
    {
        $data = (new SeoService('EUR'))->productJsonLd(['price' => '10.00', 'promo_price' => null, 'available_stock' => 0], 'X', '', 'https://x.test/p', null);

        $this->assertSame('https://schema.org/OutOfStock', $data['offers']['availability']);
        $this->assertSame('10.00', $data['offers']['price']);
        $this->assertArrayNotHasKey('description', $data);
        $this->assertArrayNotHasKey('image', $data);
    }
}
