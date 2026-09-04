<?php

namespace App\Services;

use App\Models\Category;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    public function __construct(
        private readonly AuditService $auditService = new AuditService(),
    ) {}

    public function createCategory(array $data, int $tenantId): Category
    {
        return DB::transaction(function () use ($data, $tenantId) {
            $parentId = $data['parent_id'] ?? null;

            if ($parentId) {
                $parent = Category::where('tenant_id', $tenantId)->find($parentId);
                if (!$parent) {
                    throw new ModelNotFoundException('Parent category not found');
                }
            }

            $slug = str()->slug($data['name']);
            $existing = Category::where('tenant_id', $tenantId)->where('slug', $slug)->exists();
            if ($existing) {
                $slug = $slug . '-' . time();
            }

            $category = Category::create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'parent_id' => $parentId,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $this->auditService->log('category.created', 'category', $category->id, null, $category->toArray());

            return $category;
        });
    }

    public function updateCategory(int $id, array $data, int $tenantId): Category
    {
        return DB::transaction(function () use ($id, $data, $tenantId) {
            $category = Category::where('tenant_id', $tenantId)->findOrFail($id);
            $oldValues = $category->toArray();

            if (isset($data['parent_id']) && $data['parent_id'] !== null) {
                if ($data['parent_id'] == $id) {
                    throw new \InvalidArgumentException('Category cannot be its own parent', 422);
                }
                $this->validateNoCycle($id, $data['parent_id'], $tenantId);
            }

            if (isset($data['name'])) {
                $data['slug'] = str()->slug($data['name']);
                $existing = Category::where('tenant_id', $tenantId)
                    ->where('slug', $data['slug'])
                    ->where('id', '!=', $id)
                    ->exists();
                if ($existing) {
                    $data['slug'] = $data['slug'] . '-' . time();
                }
            }

            $category->update($data);
            $category->refresh();

            $this->auditService->log('category.updated', 'category', $category->id, $oldValues, $category->toArray());

            return $category;
        });
    }

    public function deleteCategory(int $id, int $tenantId): void
    {
        DB::transaction(function () use ($id, $tenantId) {
            $category = Category::where('tenant_id', $tenantId)->findOrFail($id);

            if ($category->children()->exists()) {
                throw new \InvalidArgumentException('Cannot delete category with sub-categories', 422);
            }

            if ($category->products()->exists()) {
                throw new \InvalidArgumentException('Cannot delete category with existing products', 422);
            }

            $oldValues = $category->toArray();
            $category->delete();

            $this->auditService->log('category.deleted', 'category', $category->id, $oldValues, null);
        });
    }

    public function getTree(int $tenantId): array
    {
        $categories = Category::where('tenant_id', $tenantId)
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->buildTree($categories, null);
    }

    private function buildTree($categories, ?int $parentId): array
    {
        $tree = [];
        foreach ($categories as $category) {
            if ($category->parent_id === $parentId) {
                $node = $category->toArray();
                $node['product_count'] = $category->products_count;
                $node['children'] = $this->buildTree($categories, $category->id);
                $tree[] = $node;
            }
        }
        return $tree;
    }

    public function validateNoCycle(int $categoryId, int $parentId, int $tenantId): void
    {
        $descendantIds = $this->getAllDescendantIds($categoryId, $tenantId);
        if (in_array($parentId, $descendantIds)) {
            throw new \InvalidArgumentException('Cannot move category under its own descendant', 422);
        }
    }

    private function getAllDescendantIds(int $categoryId, int $tenantId): array
    {
        $ids = [];
        $children = Category::where('tenant_id', $tenantId)
            ->where('parent_id', $categoryId)
            ->pluck('id')
            ->toArray();

        foreach ($children as $childId) {
            $ids[] = $childId;
            $ids = array_merge($ids, $this->getAllDescendantIds($childId, $tenantId));
        }

        return $ids;
    }
}
