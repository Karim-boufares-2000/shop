<?php

declare(strict_types=1);

namespace App\Support;

/** Filtres de la liste publique des produits : toute valeur de l'URL est nettoyée et bornée ici. */
final class ListingFilters
{
    public const SORTS = ['relevance', 'newest', 'price_asc', 'price_desc', 'name'];

    public function __construct(
        public readonly string $q = '',
        public readonly ?string $category = null,
        public readonly ?string $minPrice = null,
        public readonly ?string $maxPrice = null,
        public readonly bool $inStock = false,
        public readonly bool $onlyPromo = false,
        public readonly string $sort = '',
    ) {
    }

    /** @param array<string,mixed> $query */
    public static function fromQuery(array $query, ?string $fixedCategory = null): self
    {
        $min = self::money($query['min_price'] ?? null);
        $max = self::money($query['max_price'] ?? null);
        if ($min !== null && $max !== null && Money::toCents($min) > Money::toCents($max)) {
            [$min, $max] = [$max, $min];
        }
        $sort = self::text($query['sort'] ?? null, 20);

        return new self(
            self::text($query['q'] ?? null, 100),
            $fixedCategory ?? self::slug($query['category'] ?? null),
            $min,
            $max,
            ($query['in_stock'] ?? '') === '1',
            ($query['promo'] ?? '') === '1',
            in_array($sort, self::SORTS, true) ? $sort : '',
        );
    }

    /** Tri réellement appliqué : "pertinence" seulement s'il y a une recherche. */
    public function effectiveSort(): string
    {
        if ($this->sort === 'relevance' && $this->q === '') {
            return 'newest';
        }

        return $this->sort !== '' ? $this->sort : ($this->q !== '' ? 'relevance' : 'newest');
    }

    /** Vrai si l'utilisateur a affiné la liste : ces pages ne doivent pas être indexées (contenu dupliqué). */
    public function isFiltered(): bool
    {
        return $this->q !== '' || $this->minPrice !== null || $this->maxPrice !== null
            || $this->inStock || $this->onlyPromo || $this->sort !== '';
    }

    /** @return array<string,scalar> paramètres d'URL non vides (pour la pagination) */
    public function toQuery(bool $includeCategory = true): array
    {
        $query = [
            'q' => $this->q,
            'category' => $includeCategory ? (string) $this->category : '',
            'min_price' => (string) $this->minPrice,
            'max_price' => (string) $this->maxPrice,
            'in_stock' => $this->inStock ? '1' : '',
            'promo' => $this->onlyPromo ? '1' : '',
            'sort' => $this->sort,
        ];

        return array_filter($query, static fn (string $value): bool => $value !== '');
    }

    private static function text(mixed $value, int $maxLength): string
    {
        return is_string($value) ? mb_substr(trim($value), 0, $maxLength) : '';
    }

    private static function slug(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[a-z0-9-]{1,150}$/', $value) === 1 ? $value : null;
    }

    private static function money(mixed $value): ?string
    {
        return is_string($value) ? Money::parse($value) : null;
    }
}
