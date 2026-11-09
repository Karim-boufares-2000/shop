<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    #[DataProvider('validAmounts')]
    public function testParseNormalizes(string $input, string $expected): void
    {
        $this->assertSame($expected, Money::parse($input));
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function validAmounts(): array
    {
        return [
            'entier' => ['1500', '1500.00'],
            'point' => ['1500.5', '1500.50'],
            'virgule' => ['1500,50', '1500.50'],
            'espace milliers' => ['1 500,50', '1500.50'],
            'espace insécable' => ["1\u{00A0}500", '1500.00'],
            'chiffres arabes' => ['٢٥٠٠', '2500.00'],
            'zéro' => ['0', '0.00'],
            'zéros en tête' => ['007', '7.00'],
            'inférieur à 1' => ['0.5', '0.50'],
            'maximum' => ['9999999999.99', '9999999999.99'],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function testParseRejectsInvalid(string $input): void
    {
        $this->assertNull(Money::parse($input));
    }

    /** @return array<string,array{0:string}> */
    public static function invalidAmounts(): array
    {
        return [
            'vide' => [''],
            'lettres' => ['abc'],
            'négatif' => ['-5'],
            'ambigu 1,500' => ['1,500'],
            'trois décimales' => ['1.234'],
            'trop grand' => ['12345678901'],
            'double séparateur' => ['1..5'],
            'notation scientifique' => ['1e3'],
            'symbole' => ['1500 DA'],
        ];
    }

    public function testCentsConversionsAreExact(): void
    {
        $this->assertSame(150050, Money::toCents('1500.50'));
        $this->assertSame('1500.50', Money::fromCents(150050));
        $this->assertSame('0.05', Money::fromCents(5));
        $this->assertSame(1, Money::toCents('0.01'));
    }

    public function testNoFloatRoundingProblems(): void
    {
        // 0.1 + 0.2 pose problème en float ; en centimes entiers, non.
        $this->assertSame(30, Money::toCents('0.10') + Money::toCents('0.20'));
    }

    public function testFormat(): void
    {
        $this->assertSame('1 500,50', str_replace("\u{202F}", ' ', Money::format('1500.50')));
        $this->assertSame('0,05', Money::format('0.05'));
    }
}
