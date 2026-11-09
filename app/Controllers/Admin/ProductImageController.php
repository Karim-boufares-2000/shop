<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ProductImageService;
use App\Services\ProductService;

final class ProductImageController
{
    public function __construct(private AdminPage $page, private ProductService $products, private ProductImageService $images)
    {
    }

    /** @param array<string,string> $params */
    public function upload(Request $request, array $params): Response
    {
        $productId = $this->productId($params);
        $result = $this->images->upload($productId, $request->uploadedFiles('images'), $this->page->adminId(), $request->ip());

        if ($result['errors'] !== []) {
            $this->page->flashError(implode(' ', array_map(fn (string $key): string => $this->page->t($key), $result['errors'])));
        } else {
            $this->page->flash('image.uploaded', ['count' => $result['uploaded']]);
        }

        return $this->back($productId);
    }

    /** @param array<string,string> $params */
    public function delete(Request $request, array $params): Response
    {
        $productId = $this->productId($params);
        if (!$this->images->delete($productId, (int) ($params['image'] ?? 0), $this->page->adminId(), $request->ip())) {
            throw new HttpException(404, 'not_found');
        }
        $this->page->flash('image.deleted');

        return $this->back($productId);
    }

    /** @param array<string,string> $params */
    public function setMain(Request $request, array $params): Response
    {
        $productId = $this->productId($params);
        if (!$this->images->setMain($productId, (int) ($params['image'] ?? 0), $this->page->adminId(), $request->ip())) {
            throw new HttpException(404, 'not_found');
        }
        $this->page->flash('image.main_set');

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
        return $this->page->redirect('/admin/products/' . $productId . '/edit#images');
    }
}
