<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\UrlGenerator;
use App\Core\View;
use App\Services\SeoService;
use App\Services\StorefrontService;
use App\Support\ListingFilters;

/** Liste des produits (/produits) et pages de catégorie (/categorie/{slug}). */
final class CatalogController
{
    public function __construct(
        private View $view,
        private StorefrontService $storefront,
        private SeoService $seo,
        private UrlGenerator $urls,
    ) {
    }

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        return $this->render($request, null);
    }

    /** @param array<string,string> $params */
    public function category(Request $request, array $params): Response
    {
        $category = $this->storefront->category((string) ($params['slug'] ?? ''));
        if ($category === null) {
            throw new HttpException(404, 'not_found');
        }

        return $this->render($request, $category);
    }

    /** @param array<string,mixed>|null $category */
    private function render(Request $request, ?array $category): Response
    {
        $filters = ListingFilters::fromQuery($request->queryAll(), $category === null ? null : (string) $category['slug']);
        $pageParam = $request->query('page');
        $result = $this->storefront->listing($filters, is_string($pageParam) ? max(1, (int) $pageParam) : 1);

        $path = $category === null ? '/produits' : '/categorie/' . $category['slug'];
        $heading = $category === null
            ? ($filters->q !== '' ? $this->view->t('catalog.search_results_for', ['q' => $filters->q]) : $this->view->t('catalog.title'))
            : $this->view->localized($category, 'name');

        $canonicalQuery = $result['page'] > 1 ? ['page' => $result['page']] : [];
        $title = $result['page'] > 1 ? $heading . ' — ' . $this->view->t('common.page', ['page' => $result['page'], 'pages' => $result['pages']]) : $heading;

        return Response::html($this->view->render('public/catalog', [
            'title' => $title,
            'heading' => $heading,
            'breadcrumbs' => $this->breadcrumbs($category, $heading),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'filters' => $filters,
            'basePath' => $path,
            'query' => $filters->toQuery($category === null),
            'showCategoryFilter' => $category === null,
            'categoryOptions' => $this->categoryOptions(),
            'seo' => [
                'description' => $category === null
                    ? $this->view->t('seo.catalog_description')
                    : $this->view->t('seo.category_description', ['name' => $heading]),
                'canonical' => $this->urls->absolute($path, $canonicalQuery),
                // Pages filtrées/triées : suivies mais non indexées (évite le contenu dupliqué).
                'robots' => $filters->isFiltered() ? 'noindex,follow' : null,
                'type' => 'website',
            ],
        ]));
    }

    /**
     * @param array<string,mixed>|null $category
     * @return list<array{label:string,path:?string}>
     */
    private function breadcrumbs(?array $category, string $heading): array
    {
        $items = [['label' => $this->view->t('nav.home'), 'path' => '/']];
        if ($category === null) {
            $items[] = ['label' => $this->view->t('catalog.title'), 'path' => null];

            return $items;
        }
        $items[] = ['label' => $this->view->t('catalog.title'), 'path' => '/produits'];
        if (!empty($category['parent_slug'])) {
            $items[] = [
                'label' => $this->view->localized(['name_fr' => $category['parent_name_fr'], 'name_ar' => $category['parent_name_ar']], 'name'),
                'path' => '/categorie/' . $category['parent_slug'],
            ];
        }
        $items[] = ['label' => $heading, 'path' => null];

        return $items;
    }

    /** @return list<array{slug:string,label:string}> */
    private function categoryOptions(): array
    {
        $options = [];
        foreach ($this->storefront->categoryTree() as $top) {
            $options[] = ['slug' => (string) $top['slug'], 'label' => $this->view->localized($top, 'name')];
            foreach ($top['children'] as $child) {
                $options[] = ['slug' => (string) $child['slug'], 'label' => '— ' . $this->view->localized($child, 'name')];
            }
        }

        return $options;
    }
}
