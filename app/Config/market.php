<?php

declare(strict_types=1);

/*
 * Règles du marché ciblé (par défaut : Algérie).
 * Pour un autre pays : changer l'indicatif et les motifs de numéros nationaux.
 * Motifs : numéro national avec 0 initial, chiffres uniquement.
 */
return [
    'country'        => 'DZ',
    'dial_code'      => '213',
    'currency_code'  => 'DZD', // code ISO 4217 pour les données structurées (SEO)
    'phone_patterns' => [
        '/^0[567]\d{8}$/',   // mobiles : 05/06/07 + 8 chiffres
        '/^0[2-4]\d{7,8}$/', // fixes : 02x/03x/04x
    ],
];
