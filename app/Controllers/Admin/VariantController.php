<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ProductService;
use App\Services\VariantService;

final class VariantController
{
    public function __construct(private AdminPage $page, private ProductService $products, private VariantService $variants)
    {
    }

    /** @param array<string,string> $params */
    public function store(Request $request, array $params): Response
    {
        $productId = $this->productId($params);

        return $this->save($request, $productId, null);
    }

    /** @param array<string,string> $params */
    public function update(Request $request, array $params): Response
    {
        $productId = $this->productId($params);

        return $this->save($request, $productId, (int) ($params['variant'] ?? 0));
    }

    /** @param array<string,string> $params */
    public function delete(Request $request, array $params): Response
    {
        $productId = $this->productId($params);
        if (!$this->variants->delete($productId, (int) ($params['variant'] ?? 0), $this->page->adminId(), $request->ip())) {
            throw new HttpException(404, 'not_found');
        }
        $this->page->flash('variant.deleted');

        return $this->back($productId);
    }

    private function save(Request $request, int $productId, ?int $variantId): Response
    {
        $result = $this->variants->save($productId, $variantId, $request->postAll(), $this->page->adminId(), $request->ip());
        if ($result['errors'] !== []) {
            $this->page->flashError(implode(' ', $result['errors']));
        } else {
            $this->page->flash('variant.saved');
        }

        return $this->back($productId);
    }

    /** @param array<string,string> $params */
    private function productId(array $params): int
    {
        $id = (int) ($params['id'] ?? 0);
        if ($this->products->find($id) === null) {
            throw new HttpException(404, 'not_found');
        }

        return $id;
    }

    private function back(int $productId): Response
    {
        return $this->page->redirect('/admin/products/' . $productId . '/edit#variants');
    }
}
