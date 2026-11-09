<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Translator;
use PHPUnit\Framework\TestCase;

final class TranslatorTest extends TestCase
{
    private function lang(): string
    {
        return dirname(__DIR__, 2) . '/lang';
    }

    public function testReturnsTranslationAndReplacesParams(): void
    {
        $t = new Translator($this->lang(), 'fr');
        $this->assertStringContainsString('3', $t->get('validation.min', ['min' => 3]));
    }

    public function testUnknownKeyReturnsKey(): void
    {
        $this->assertSame('nope.key', (new Translator($this->lang(), 'fr'))->get('nope.key'));
    }

    public function testDirection(): void
    {
        $this->assertSame('rtl', (new Translator($this->lang(), 'ar'))->direction());
        $this->assertSame('ltr', (new Translator($this->lang(), 'fr'))->direction());
    }

    public function testUnsupportedLocaleIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Translator($this->lang(), '../etc');
    }

    public function testAllFrenchKeysExistInArabic(): void
    {
        $fr = require $this->lang() . '/fr.php';
        $ar = require $this->lang() . '/ar.php';

        $this->assertSame([], array_values(array_diff(array_keys($fr), array_keys($ar))));
        $this->assertSame([], array_values(array_diff(array_keys($ar), array_keys($fr))));
    }
}
