<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Translator;
use App\Repositories\CategoryRepository;
use App\Support\Slugger;
use App\Validation\Validator;

/** Catégories sur deux niveaux maximum (catégorie principale > sous-catégorie). */
final class CategoryService
{
    public function __construct(
        private CategoryRepository $categories,
        private Validator $validator,
        private Translator $translator,
        private AuditLoggerInterface $audit,
    ) {
    }

    /** @return list<array<string,mixed>> catégories principales, chacune avec la clé 'children' */
    public function tree(): array
    {
        $byParent = [];
        foreach ($this->categories->all() as $row) {
            $byParent[(int) ($row['parent_id'] ?? 0)][] = $row;
        }
        $tree = [];
        foreach ($byParent[0] ?? [] as $top) {
            $top['children'] = $byParent[(int) $top['id']] ?? [];
            $tree[] = $top;
        }

        return $tree;
    }

    /** @return list<array{id:int,label:string}> toutes les catégories, sous-catégories indentées (pour un <select>) */
    public function options(): array
    {
        $options = [];
        foreach ($this->tree() as $top) {
            $options[] = ['id' => (int) $top['id'], 'label' => (string) $top['name_fr']];
            foreach ($top['children'] as $child) {
                $options[] = ['id' => (int) $child['id'], 'label' => '— ' . (string) $child['name_fr']];
            }
        }

        return $options;
    }

    /** @return list<array{id:int,label:string}> parents possibles : catégories principales, hors la catégorie éditée */
    public function parentOptions(?int $excludeId): array
    {
        $options = [];
        foreach ($this->tree() as $top) {
            if ($excludeId === null || (int) $top['id'] !== $excludeId) {
                $options[] = ['id' => (int) $top['id'], 'label' => (string) $top['name_fr']];
            }
        }

        return $options;
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->categories->find($id);
    }

    public function hasChildren(int $id): bool
    {
        return $this->categories->countChildren($id) > 0;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{id:?int,errors:array<string,string>}
     */
    public function save(?int $id, array $input, int $adminId, string $ip): array
    {
        $result = $this->validator->validate($input, [
            'name_fr' => 'required|string|min:2|max:120',
            'name_ar' => 'nullable|string|max:120',
            'parent_id' => 'nullable|int|min_value:1',
            'sort_order' => 'nullable|int|min_value:0|max_value:100000',
        ]);
        $errors = $result->firstErrors();
        $data = $result->validated();

        $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;
        if (!isset($errors['parent_id']) && $parentId !== null && $this->isInvalidParent($id, $parentId)) {
            $errors['parent_id'] = $this->translator->get('category.error.invalid_parent');
        }
        if ($errors !== []) {
            return ['id' => null, 'errors' => $errors];
        }

        $fields = [
            'parent_id' => $parentId,
            'name_fr' => (string) $data['name_fr'],
            'name_ar' => $data['name_ar'] ?? null,
            'is_active' => ($input['is_active'] ?? '') === '1' ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];

        if ($id === null) {
            $slug = Slugger::unique(Slugger::slugify($fields['name_fr']), fn (string $s): bool => $this->categories->slugExists($s));
            $id = $this->categories->create($fields + ['slug' => $slug]);
            $this->audit->log('category.created', $adminId, 'category', (string) $id, [], $ip);
        } else {
            $this->categories->update($id, $fields);
            $this->audit->log('category.updated', $adminId, 'category', (string) $id, [], $ip);
        }

        return ['id' => $id, 'errors' => []];
    }

    /** @return string|null clé de traduction de l'erreur, null si supprimée */
    public function delete(int $id, int $adminId, string $ip): ?string
    {
        if ($this->categories->countChildren($id) > 0) {
            return 'category.error.has_children';
        }
        if ($this->categories->countProducts($id) > 0) {
            return 'category.error.has_products';
        }
        $this->categories->delete($id);
        $this->audit->log('category.deleted', $adminId, 'category', (string) $id, [], $ip);

        return null;
    }

    private function isInvalidParent(?int $id, int $parentId): bool
    {
        if ($id !== null && ($parentId === $id || $this->categories->countChildren($id) > 0)) {
            return true; // soi-même, ou une catégorie qui a déjà des enfants ne peut pas devenir sous-catégorie
        }
        $parent = $this->categories->find($parentId);

        return $parent === null || $parent['parent_id'] !== null; // le parent doit exister et être principal
    }
}
