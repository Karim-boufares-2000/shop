<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Validation\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
    private function phone(): PhoneNumber
    {
        return new PhoneNumber('213', ['/^0[567]\d{8}$/', '/^0[2-4]\d{7,8}$/']);
    }

    #[DataProvider('validNumbers')]
    public function testNormalizesValidNumbers(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->phone()->normalize($input));
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function validNumbers(): array
    {
        return [
            'national avec espaces' => ['0555 12 34 56', '0555123456'],
            'international +' => ['+213555123456', '0555123456'],
            'international 00' => ['00213 555 12 34 56', '0555123456'],
            'international avec (0)' => ['+213 (0)555123456', '0555123456'],
            'chiffres arabes-indic' => ['٠٥٥٥١٢٣٤٥٦', '0555123456'],
            'tirets et points' => ['0661-23.45.67', '0661234567'],
            'fixe' => ['021 23 45 67', '021234567'],
        ];
    }

    #[DataProvider('invalidNumbers')]
    public function testRejectsInvalidNumbers(string $input): void
    {
        $this->assertNull($this->phone()->normalize($input));
    }

    /** @return array<string,array{0:string}> */
    public static function invalidNumbers(): array
    {
        return [
            'vide' => [''],
            'lettres' => ['abc'],
            'trop court' => ['055512345'],
            'trop long' => ['05551234567'],
            'préfixe 01 inconnu' => ['0155123456'],
            'mélange' => ['0555abc456'],
            'autre pays' => ['+33612345678'],
        ];
    }
}
