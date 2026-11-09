<?php

declare(strict_types=1);

namespace App\Validation;

use App\Support\Digits;

/** Normalisation et validation de numéros de téléphone selon les règles configurables du marché. */
final class PhoneNumber
{
    /** @param list<string> $nationalPatterns  Expressions régulières appliquées au numéro national (0 initial). */
    public function __construct(private string $dialCode, private array $nationalPatterns)
    {
    }

    /** @return string|null numéro national canonique (ex. 0555123456) ou null si invalide */
    public function normalize(string $raw): ?string
    {
        $number = Digits::toAscii($raw);
        $number = preg_replace('/[\s.\-()\/]/u', '', $number) ?? '';
        if ($number === '') {
            return null;
        }

        $international = null;
        if (str_starts_with($number, '+' . $this->dialCode)) {
            $international = substr($number, strlen($this->dialCode) + 1);
        } elseif (str_starts_with($number, '00' . $this->dialCode)) {
            $international = substr($number, strlen($this->dialCode) + 2);
        }
        if ($international !== null) {
            $number = str_starts_with($international, '0') ? $international : '0' . $international;
        }

        if (!ctype_digit($number)) {
            return null;
        }
        foreach ($this->nationalPatterns as $pattern) {
            if (preg_match($pattern, $number) === 1) {
                return $number;
            }
        }

        return null;
    }
}
