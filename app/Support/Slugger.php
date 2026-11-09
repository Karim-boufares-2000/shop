<?php

declare(strict_types=1);

namespace App\Support;

/** Génération d'adresses lisibles (slugs ASCII) à partir du nom français. */
final class Slugger
{
    private const ACCENTS = [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ì' => 'i',
        'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ò' => 'o', 'õ' => 'o',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
        'ç' => 'c', 'ñ' => 'n', 'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae',
    ];

    public static function slugify(string $text, int $maxLength = 150): string
    {
        $text = strtr(mb_strtolower(trim($text), 'UTF-8'), self::ACCENTS);
        $text = trim(preg_replace('/[^a-z0-9]+/', '-', $text) ?? '', '-');
        $text = rtrim(substr($text, 0, $maxLength), '-');

        return $text !== '' ? $text : 'item';
    }

    /**
     * Rend le slug unique en ajoutant -2, -3...
     *
     * @param callable(string):bool $exists
     */
    public static function unique(string $base, callable $exists, int $maxLength = 150): string
    {
        $slug = $base;
        for ($i = 2; $exists($slug); $i++) {
            if ($i > 1000) {
                throw new \RuntimeException('Impossible de générer un slug unique.');
            }
            $suffix = '-' . $i;
            $slug = rtrim(substr($base, 0, $maxLength - strlen($suffix)), '-') . $suffix;
        }

        return $slug;
    }
}
