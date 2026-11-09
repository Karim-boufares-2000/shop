<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ProductRepository;
use App\Services\CategoryService;
use App\Services\ProductImageService;
use App\Services\ProductService;
use App\Services\VariantService;

final class ProductController
{
    private const PER_PAGE = 20;
    private const FIELDS = ['name_fr', 'name_ar', 'description_fr', 'description_ar', 'category_id', 'price', 'promo_price', 'stock', 'is_active', 'is_featured'];

    public function __construct(
        private AdminPage $page,
        private ProductService $products,
        private CategoryService $categories,
        private ProductImageService $images,
        private VariantService $variants,
    ) {
    }

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $q = $this->page->queryString($request, 'q');
        $categoryId = max(0, (int) $this->page->queryString($request, 'category', 10));
        $status = $this->page->queryString($request, 'status', 10);
        $status = in_array($status, ['active', 'inactive'], true) ? $status : '';
        $sort = $this->page->queryString($request, 'sort', 20);
        $sort = isset(ProductRepository::SORTS[$sort]) ? $sort : 'newest';
        $page = max(1, (int) $this->page->queryString($request, 'page', 10));

        $filters = ['q' => $q, 'category_id' => $categoryId, 'status' => $status];
        $result = $this->products->search($filters, $sort, $page, self::PER_PAGE);
        $pages = max(1, (int) ceil($result['total'] / self::PER_PAGE));
        if ($page > $pages) {
            $page = $pages;
            $result = $this->products->search($filters, $sort, $page, self::PER_PAGE);
        }

        // Paramètres conservés par la pagination (hors "page")
        $query = array_filter(
            ['q' => $q, 'category' => $categoryId > 0 ? $categoryId : '', 'status' => $status, 'sort' => $sort !== 'newest' ? $sort : ''],
            static fn (string|int $v): bool => $v !== '',
        );

        return $this->page->render('admin/products/index', [
            'title' => $this->page->t('product.title'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'pages' => $pages,
            'query' => $query,
            'filters' => ['q' => $q, 'category' => $categoryId > 0 ? (string) $categoryId : '', 'status' => $status, 'sort' => $sort],
            'sorts' => array_keys(ProductRepository::SORTS),
            'categories' => $this->categories->options(),
        ]);
    }

    /** @param array<string,string> $params */
    public function create(Request $request, array $params): Response
    {
        return $this->form(null, [
            'name_fr' => '', 'name_ar' => '', 'description_fr' => '', 'description_ar' => '', 'category_id' => '',
            'price' => '', 'promo_price' => '', 'stock' => '0', 'is_active' => '1', 'is_featured' => '',
        ], [], 200);
    }

    /** @param array<string,string> $params */
    public function store(Request $request, array $params): Response
    {
        $result = $this->products->save(null, $request->postAll(), $this->page->adminId(), $request->ip());
        if ($result['errors'] !== [] || $result['id'] === null) {
            return $this->form(null, $this->page->formValues($request, self::FIELDS), $result['errors'], 422);
        }
        $this->page->flash('product.saved');

        return $this->page->redirect('/admin/products/' . $result['id'] . '/edit'); // images et variantes ensuite
    }

    /** @param array<string,string> $params */
    public function edit(Request $request, array $params): Response
    {
        $product = $this->findOrFail($params);

        return $this->form($product, [
            'name_fr' => (string) $product['name_fr'],
            'name_ar' => (string) ($product['name_ar'] ?? ''),
            'description_fr' => (string) ($product['description_fr'] ?? ''),
            'description_ar' => (string) ($product['description_ar'] ?? ''),
            'category_id' => (string) $product['category_id'],
            'price' => (string) $product['price'],
            'promo_price' => (string) ($product['promo_price'] ?? ''),
            'stock' => (string) $product['stock'],
            'is_active' => (string) $product['is_active'],
            'is_featured' => (string) $product['is_featured'],
        ], [], 200);
    }

    /** @param array<string,string> $params */
    public function update(Request $request, array $params): Response
    {
        $product = $this->findOrFail($params);
        $result = $this->products->save((int) $product['id'], $request->postAll(), $this->page->adminId(), $request->ip());
        if ($result['errors'] !== []) {
            return $this->form($product, $this->page->formValues($request, self::FIELDS), $result['errors'], 422);
        }
        $this->page->flash('product.saved');

        return $this->page->redirect('/admin/products/' . (int) $product['id'] . '/edit');
    }

    /** @param array<string,string> $params */
    public function toggle(Request $request, array $params): Response
    {
        $this->findOrFail($params);
        $this->products->toggleActive((int) $params['id'], $this->page->adminId(), $request->ip());
        $this->page->flash('product.toggled');

        return $this->page->redirect('/admin/products');
    }

    /** @param array<string,string> $params */
    public function delete(Request $request, array $params): Response
    {
        $this->findOrFail($params);
        $this->products->delete((int) $params['id'], $this->page->adminId(), $request->ip());
        $this->page->flash('product.deleted');

        return $this->page->redirect('/admin/products');
    }

    /**
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    private function findOrFail(array $params): array
    {
        $product = $this->products->find((int) ($params['id'] ?? 0));
        if ($product === null) {
            throw new HttpException(404, 'not_found');
        }

        return $product;
    }

    /**
     * @param array<string,mixed>|null $product
     * @param array<string,string> $values
     * @param array<string,string> $errors
     */
    private function form(?array $product, array $values, array $errors, int $status): Response
    {
        $id = $product === null ? null : (int) $product['id'];

        return $this->page->render('admin/products/form', [
            'title' => $this->page->t($product === null ? 'product.new' : 'product.edit'),
            'product' => $product,
            'values' => $values,
            'errors' => $errors,
            'categories' => $this->categories->options(),
            'images' => $id === null ? [] : $this->images->list($id),
            'variants' => $id === null ? [] : $this->variants->list($id),
        ], $status);
    }
}
