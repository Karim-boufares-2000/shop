<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\UrlGenerator;
use PHPUnit\Framework\TestCase;

final class UrlGeneratorTest extends TestCase
{
    public function testRelativeUrlsUseBasePathAndEncodeQuery(): void
    {
        $urls = new UrlGenerator('/shop', 'http://localhost/shop');

        $this->assertSame('/shop/produits', $urls->to('/produits'));
        $this->assertSame('/shop/produits?q=robe%20%C3%A9t%C3%A9&page=2', $urls->to('produits', ['q' => 'robe été', 'page' => 2]));
        $this->assertSame('/shop/assets/css/app.css', $urls->asset('css/app.css'));
    }

    public function testAbsoluteUrls(): void
    {
        $urls = new UrlGenerator('/shop', 'https://exemple.com/shop/');

        $this->assertSame('https://exemple.com/shop/produit/robe', $urls->absolute('/produit/robe'));
        $this->assertSame('https://exemple.com/shop/', $urls->absolute('/'));
        $this->assertSame('https://exemple.com/shop/produits?page=2', $urls->absolute('/produits', ['page' => 2]));
    }

    public function testAbsoluteFallsBackToRelativeWithoutSiteUrl(): void
    {
        $this->assertSame('/produits', (new UrlGenerator(''))->absolute('/produits'));
    }

    public function testQueryValuesCannotInjectParameters(): void
    {
        $url = (new UrlGenerator('', 'https://x.test'))->absolute('/produits', ['q' => 'a&admin=1#frag']);

        $this->assertSame('https://x.test/produits?q=a%26admin%3D1%23frag', $url);
    }
}
