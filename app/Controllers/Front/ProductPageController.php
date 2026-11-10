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

final class ProductPageController
{
    public function __construct(
        private View $view,
        private StorefrontService $storefront,
        private SeoService $seo,
        private UrlGenerator $urls,
    ) {
    }

    /** @param array<string,string> $params */
    public function show(Request $request, array $params): Response
    {
        $page = $this->storefront->productPage((string) ($params['slug'] ?? ''));
        if ($page === null) {
            throw new HttpException(404, 'not_found'); // inconnu, inactif ou catégorie masquée : même réponse
        }
        $product = $page['product'];
        $name = $this->view->localized($product, 'name');
        $description = $this->view->localized($product, 'description');
        $url = $this->urls->absolute('/produit/' . $product['slug']);
        $imageUrl = $page['images'] === [] ? null : $this->urls->absolute('media/products/' . $page['images'][0]['file_id'] . '.jpg');
        $summary = $description !== '' ? $description : $name . ' — ' . $this->view->localized(['name_fr' => $product['category_name_fr'], 'name_ar' => $product['category_name_ar']], 'name');

        return Response::html($this->view->render('public/product', $page + [
            'title' => $name,
            'breadcrumbs' => $this->breadcrumbs($product, $name),
            'seo' => [
                'description' => $this->seo->excerpt($summary),
                'canonical' => $url,
                'image' => $imageUrl,
                'type' => 'product',
                'jsonld' => $this->seo->productJsonLd($product, $name, $this->seo->excerpt($description, 500), $url, $imageUrl),
            ],
        ]));
    }

    /**
     * @param array<string,mixed> $product
     * @return list<array{label:string,path:?string}>
     */
    private function breadcrumbs(array $product, string $name): array
    {
        $items = [
            ['label' => $this->view->t('nav.home'), 'path' => '/'],
            ['label' => $this->view->t('catalog.title'), 'path' => '/produits'],
        ];
        if (!empty($product['parent_slug'])) {
            $items[] = [
                'label' => $this->view->localized(['name_fr' => $product['parent_name_fr'], 'name_ar' => $product['parent_name_ar']], 'name'),
                'path' => '/categorie/' . $product['parent_slug'],
            ];
        }
        $items[] = [
            'label' => $this->view->localized(['name_fr' => $product['category_name_fr'], 'name_ar' => $product['category_name_ar']], 'name'),
            'path' => '/categorie/' . $product['category_slug'],
        ];
        $items[] = ['label' => $name, 'path' => null];

        return $items;
    }
}
