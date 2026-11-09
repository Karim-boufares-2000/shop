<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Montants manipulés comme CHAÎNES décimales ("1500.50") ou centimes entiers : jamais de float pour le calcul.
 */
final class Money
{
    /** @return string|null montant normalisé "1500.50" (2 décimales, 10 chiffres entiers max) ou null si invalide */
    public static function parse(string $raw): ?string
    {
        $value = Digits::toAscii(trim($raw));
        $value = str_replace([' ', "\u{00A0}", "\u{202F}"], '', $value);
        // Séparateur décimal "," ou "." ; "1,500" (ambigu) est refusé volontairement.
        if (preg_match('/^(\d{1,10})(?:[.,](\d{1,2}))?$/', $value, $m) !== 1) {
            return null;
        }
        $integer = ltrim($m[1], '0');

        return ($integer === '' ? '0' : $integer) . '.' . str_pad($m[2] ?? '', 2, '0');
    }

    /** @param string $amount montant normalisé par parse() */
    public static function toCents(string $amount): int
    {
        return (int) str_replace('.', '', $amount);
    }

    public static function fromCents(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Affichage "1 500,50" (espace insécable fine) ; pour l'affichage uniquement. */
    public static function format(string $amount): string
    {
        $cents = self::toCents($amount);

        return number_format(intdiv($cents, 100), 0, ',', "\u{202F}") . ',' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
