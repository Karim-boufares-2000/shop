<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Translator;
use App\Core\UrlGenerator;
use App\Core\View;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArraySession;

final class ViewTest extends TestCase
{
    private function view(string $locale = 'fr'): View
    {
        $root = dirname(__DIR__, 2);

        return new View(
            $root . '/app/Views',
            new UrlGenerator('/shop', 'https://x.test/shop'),
            new Translator($root . '/lang', $locale),
            new Csrf(new ArraySession()),
            new Request([], [], ['REQUEST_URI' => '/shop/produits', 'SCRIPT_NAME' => '/shop/public/index.php']),
        );
    }

    public function testLocalizedPrefersArabicWithFallbackToFrench(): void
    {
        $row = ['name_fr' => 'Robe', 'name_ar' => 'فستان'];

        $this->assertSame('فستان', $this->view('ar')->localized($row, 'name'));
        $this->assertSame('Robe', $this->view('fr')->localized($row, 'name'));
        $this->assertSame('Robe', $this->view('ar')->localized(['name_fr' => 'Robe', 'name_ar' => ''], 'name'));
        $this->assertSame('Robe', $this->view('ar')->localized(['name_fr' => 'Robe', 'name_ar' => null], 'name'));
        $this->assertSame('', $this->view('fr')->localized([], 'name'));
    }

    public function testTextEscapesHtmlButKeepsLineBreaks(): void
    {
        $html = $this->view()->text("Ligne 1\n<script>alert(1)</script>\"'");

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('<br>', $html);
    }

    public function testEscapeCoversQuotes(): void
    {
        $this->assertSame('&quot;x&quot; &#039;y&#039; &lt;b&gt; &amp;', $this->view()->e('"x" \'y\' <b> &'));
    }

    public function testDiscountPercentUsesExactCents(): void
    {
        $view = $this->view();

        $this->assertSame(25, $view->discountPercent('2000.00', '1500.00'));
        $this->assertSame(33, $view->discountPercent('150.00', '100.00'));
        $this->assertSame(0, $view->discountPercent('0.00', '0.00'));
    }

    public function testMediaAbsoluteAndMoneyHelpers(): void
    {
        $view = $this->view();

        $this->assertSame('/shop/media/products/abc.jpg', $view->mediaUrl('abc'));
        $this->assertSame('/shop/media/products/abc_t.jpg', $view->mediaUrl('abc', true));
        $this->assertSame('https://x.test/shop/produit/robe', $view->absoluteUrl('/produit/robe'));
        $this->assertSame('1 500,50', str_replace("\u{202F}", ' ', $view->money('1500.50')));
    }

    public function testSharedDataIsLazyAndComputedOnce(): void
    {
        $view = $this->view();
        $calls = 0;
        $view->share('menu', function () use (&$calls): array {
            $calls++;

            return ['a'];
        });

        $this->assertSame(0, $calls);
        $this->assertSame(['a'], $view->shared('menu'));
        $this->assertSame(['a'], $view->shared('menu'));
        $this->assertSame(1, $calls);
        $this->assertNull($view->shared('inconnu'));
    }

    public function testTemplateNameCannotEscapeViewsDirectory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->view()->render('../../.env', [], null);
    }
}
