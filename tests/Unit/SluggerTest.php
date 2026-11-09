<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Slugger;
use PHPUnit\Framework\TestCase;

final class SluggerTest extends TestCase
{
    public function testAccentsAndPunctuation(): void
    {
        $this->assertSame('robe-d-ete-elegante', Slugger::slugify("Robe d'été Élégante"));
        $this->assertSame('parfum-homme', Slugger::slugify('Parfum — Homme'));
        $this->assertSame('coeur-ca', Slugger::slugify('Cœur çà'));
    }

    public function testArabicOnlyFallsBack(): void
    {
        $this->assertSame('item', Slugger::slugify('عطر'));
        $this->assertSame('item', Slugger::slugify('   '));
    }

    public function testMaxLengthWithoutTrailingDash(): void
    {
        $slug = Slugger::slugify(str_repeat('abc ', 100), 20);

        $this->assertLessThanOrEqual(20, strlen($slug));
        $this->assertStringEndsNotWith('-', $slug);
    }

    public function testSlugContainsOnlySafeCharacters(): void
    {
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', Slugger::slugify('<script>alert(1)</script> ../etc/passwd'));
    }

    public function testUniqueAppendsSuffix(): void
    {
        $taken = ['robes', 'robes-2'];

        $this->assertSame('robes-3', Slugger::unique('robes', static fn (string $s): bool => in_array($s, $taken, true)));
        $this->assertSame('chaussures', Slugger::unique('chaussures', static fn (string $s): bool => in_array($s, $taken, true)));
    }

    public function testUniqueRespectsMaxLength(): void
    {
        $base = str_repeat('a', 20);
        $slug = Slugger::unique($base, static fn (string $s): bool => $s === $base, 20);

        $this->assertSame(20, strlen($slug));
        $this->assertStringEndsWith('-2', $slug);
    }
}
