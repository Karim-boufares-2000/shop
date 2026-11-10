<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ListingFilters;
use PHPUnit\Framework\TestCase;

final class ListingFiltersTest extends TestCase
{
    public function testDefaults(): void
    {
        $filters = ListingFilters::fromQuery([]);

        $this->assertSame('', $filters->q);
        $this->assertNull($filters->category);
        $this->assertFalse($filters->isFiltered());
        $this->assertSame('newest', $filters->effectiveSort());
        $this->assertSame([], $filters->toQuery());
    }

    public function testQueryIsTrimmedBoundedAndArraysAreIgnored(): void
    {
        $this->assertSame('robe', ListingFilters::fromQuery(['q' => '  robe  '])->q);
        $this->assertSame(100, mb_strlen(ListingFilters::fromQuery(['q' => str_repeat('é', 500)])->q));
        $this->assertSame('', ListingFilters::fromQuery(['q' => ['a']])->q);
    }

    public function testCategorySlugIsValidatedAndFixedCategoryWins(): void
    {
        $this->assertSame('robes-ete', ListingFilters::fromQuery(['category' => 'robes-ete'])->category);
        foreach (['Robes', 'a b', "x'; DROP", '../etc', str_repeat('a', 151)] as $bad) {
            $this->assertNull(ListingFilters::fromQuery(['category' => $bad])->category, $bad);
        }
        $this->assertSame('femme', ListingFilters::fromQuery(['category' => 'hommes'], 'femme')->category);
    }

    public function testPricesAreNormalizedInvalidOnesIgnoredAndSwapped(): void
    {
        $filters = ListingFilters::fromQuery(['min_price' => '1 000,5', 'max_price' => '2500']);
        $this->assertSame('1000.50', $filters->minPrice);
        $this->assertSame('2500.00', $filters->maxPrice);

        $swapped = ListingFilters::fromQuery(['min_price' => '3000', 'max_price' => '1000']);
        $this->assertSame('1000.00', $swapped->minPrice);
        $this->assertSame('3000.00', $swapped->maxPrice);

        $invalid = ListingFilters::fromQuery(['min_price' => 'abc', 'max_price' => '-5']);
        $this->assertNull($invalid->minPrice);
        $this->assertNull($invalid->maxPrice);
    }

    public function testSortIsWhitelistedAndRelevanceNeedsASearch(): void
    {
        $this->assertSame('price_asc', ListingFilters::fromQuery(['sort' => 'price_asc'])->sort);
        $this->assertSame('', ListingFilters::fromQuery(['sort' => 'p.id; DROP TABLE products'])->sort);

        $this->assertSame('newest', ListingFilters::fromQuery(['sort' => 'relevance'])->effectiveSort());
        $this->assertSame('relevance', ListingFilters::fromQuery(['q' => 'robe'])->effectiveSort());
        $this->assertSame('price_desc', ListingFilters::fromQuery(['q' => 'robe', 'sort' => 'price_desc'])->effectiveSort());
    }

    public function testCheckboxesAndFilteredFlag(): void
    {
        $this->assertTrue(ListingFilters::fromQuery(['in_stock' => '1'])->inStock);
        $this->assertFalse(ListingFilters::fromQuery(['in_stock' => 'yes'])->inStock);
        $this->assertTrue(ListingFilters::fromQuery(['promo' => '1'])->onlyPromo);

        foreach ([['q' => 'a'], ['min_price' => '1'], ['max_price' => '1'], ['in_stock' => '1'], ['promo' => '1'], ['sort' => 'name']] as $query) {
            $this->assertTrue(ListingFilters::fromQuery($query)->isFiltered(), json_encode($query));
        }
        $this->assertFalse(ListingFilters::fromQuery(['category' => 'femme'])->isFiltered()); // une catégorie seule reste indexable
    }

    public function testToQueryOmitsEmptyValuesAndCanDropCategory(): void
    {
        $filters = ListingFilters::fromQuery(['q' => 'robe', 'category' => 'femme', 'in_stock' => '1', 'max_price' => '2000']);

        $this->assertSame(['q' => 'robe', 'category' => 'femme', 'max_price' => '2000.00', 'in_stock' => '1'], $filters->toQuery());
        $this->assertArrayNotHasKey('category', $filters->toQuery(false));
    }
}
