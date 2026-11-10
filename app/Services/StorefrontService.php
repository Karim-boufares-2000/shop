<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\StorefrontRepository;
use App\Support\ListingFilters;

/** Cas d'usage de la boutique publique (lecture seule). */
final class StorefrontService
{
    public const PER_PAGE = 12;

    public function __construct(private StorefrontRepository $repository)
    {
    }

    /** @return array{featured:list<array<string,mixed>>,latest:list<array<string,mixed>>,promo:list<array<string,mixed>>,categories:list<array<string,mixed>>} */
    public function home(): array
    {
        return [
            'featured' => $this->repository->featured(8),
            'latest' => $this->repository->latest(8),
            'promo' => $this->repository->onSale(8),
            'categories' => $this->categoryTree(),
        ];
    }

    /**
     * Liste paginée ; la page demandée est ramenée à la dernière page existante.
     *
     * @return array{items:list<array<string,mixed>>,total:int,page:int,pages:int}
     */
    public function listing(ListingFilters $filters, int $page): array
    {
        $page = max(1, $page);
        $result = $this->repository->listing($filters, $page, self::PER_PAGE);
        $pages = max(1, (int) ceil($result['total'] / self::PER_PAGE));
        if ($page > $pages) {
            $page = $pages;
            $result = $this->repository->listing($filters, $page, self::PER_PAGE);
        }

        return ['items' => $result['items'], 'total' => $result['total'], 'page' => $page, 'pages' => $pages];
    }

    /** @return array<string,mixed>|null */
    public function category(string $slug): ?array
    {
        return preg_match('/^[a-z0-9-]{1,150}$/', $slug) === 1 ? $this->repository->findCategoryBySlug($slug) : null;
    }

    /**
     * @return array{product:array<string,mixed>,images:list<array<string,mixed>>,variants:list<array<string,mixed>>,similar:list<array<string,mixed>>}|null
     */
    public function productPage(string $slug): ?array
    {
        $product = preg_match('/^[a-z0-9-]{1,220}$/', $slug) === 1 ? $this->repository->findBySlug($slug) : null;
        if ($product === null) {
            return null;
        }
        $id = (int) $product['id'];

        return [
            'product' => $product,
            'images' => $this->repository->images($id),
            'variants' => $this->repository->variants($id),
            'similar' => $this->repository->similar($id, (int) $product['category_id'], 4),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function categoryTree(): array
    {
        return $this->repository->categoryTree();
    }

    /** @return array{products:list<array<string,mixed>>,categories:list<array<string,mixed>>} */
    public function sitemap(): array
    {
        return $this->repository->sitemapEntries();
    }
}
