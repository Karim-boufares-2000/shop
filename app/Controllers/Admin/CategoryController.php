<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CategoryService;

final class CategoryController
{
    private const FIELDS = ['name_fr', 'name_ar', 'parent_id', 'sort_order', 'is_active'];

    public function __construct(private AdminPage $page, private CategoryService $categories)
    {
    }

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        return $this->page->render('admin/categories/index', [
            'title' => $this->page->t('category.title'),
            'tree' => $this->categories->tree(),
        ]);
    }

    /** @param array<string,string> $params */
    public function create(Request $request, array $params): Response
    {
        return $this->form(null, ['name_fr' => '', 'name_ar' => '', 'parent_id' => '', 'sort_order' => '0', 'is_active' => '1'], [], 200);
    }

    /** @param array<string,string> $params */
    public function store(Request $request, array $params): Response
    {
        $result = $this->categories->save(null, $request->postAll(), $this->page->adminId(), $request->ip());
        if ($result['errors'] !== []) {
            return $this->form(null, $this->page->formValues($request, self::FIELDS), $result['errors'], 422);
        }
        $this->page->flash('category.saved');

        return $this->page->redirect('/admin/categories');
    }

    /** @param array<string,string> $params */
    public function edit(Request $request, array $params): Response
    {
        $category = $this->findOrFail($params);

        return $this->form($category, [
            'name_fr' => (string) $category['name_fr'],
            'name_ar' => (string) ($category['name_ar'] ?? ''),
            'parent_id' => (string) ($category['parent_id'] ?? ''),
            'sort_order' => (string) $category['sort_order'],
            'is_active' => (string) $category['is_active'],
        ], [], 200);
    }

    /** @param array<string,string> $params */
    public function update(Request $request, array $params): Response
    {
        $category = $this->findOrFail($params);
        $result = $this->categories->save((int) $category['id'], $request->postAll(), $this->page->adminId(), $request->ip());
        if ($result['errors'] !== []) {
            return $this->form($category, $this->page->formValues($request, self::FIELDS), $result['errors'], 422);
        }
        $this->page->flash('category.saved');

        return $this->page->redirect('/admin/categories');
    }

    /** @param array<string,string> $params */
    public function delete(Request $request, array $params): Response
    {
        $category = $this->findOrFail($params);
        $errorKey = $this->categories->delete((int) $category['id'], $this->page->adminId(), $request->ip());
        if ($errorKey !== null) {
            $this->page->flashError($this->page->t($errorKey));
        } else {
            $this->page->flash('category.deleted');
        }

        return $this->page->redirect('/admin/categories');
    }

    /**
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    private function findOrFail(array $params): array
    {
        $category = $this->categories->find((int) ($params['id'] ?? 0));
        if ($category === null) {
            throw new HttpException(404, 'not_found');
        }

        return $category;
    }

    /**
     * @param array<string,mixed>|null $category
     * @param array<string,string> $values
     * @param array<string,string> $errors
     */
    private function form(?array $category, array $values, array $errors, int $status): Response
    {
        return $this->page->render('admin/categories/form', [
            'title' => $this->page->t($category === null ? 'category.new' : 'category.edit'),
            'category' => $category,
            'values' => $values,
            'errors' => $errors,
            'parents' => $this->categories->parentOptions($category === null ? null : (int) $category['id']),
        ], $status);
    }
}
