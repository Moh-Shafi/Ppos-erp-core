<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Recipe;
use App\Models\Sale;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

class RecipeService
{
    public function create(int $productId, array $ingredients, float $yieldQuantity = 1, ?int $yieldUnitId = null): Recipe
    {
        $existing = Recipe::withoutTenantScope()
            ->where('product_id', $productId)
            ->first();

        if ($existing) {
            throw new \DomainException('Product already has a recipe');
        }

        return DB::transaction(function () use ($productId, $ingredients, $yieldQuantity, $yieldUnitId) {
            $recipe = Recipe::create([
                'product_id' => $productId,
                'yield_quantity' => $yieldQuantity,
                'yield_unit_id' => $yieldUnitId,
            ]);

            foreach ($ingredients as $ingredient) {
                $recipe->ingredients()->create([
                    'ingredient_product_id' => $ingredient['ingredient_product_id'],
                    'quantity' => $ingredient['quantity'],
                    'unit_id' => $ingredient['unit_id'] ?? null,
                ]);
            }

            return $recipe->fresh(['ingredients']);
        });
    }

    public function update(int $recipeId, array $data): Recipe
    {
        $recipe = Recipe::findOrFail($recipeId);

        DB::transaction(function () use ($recipe, $data) {
            if (isset($data['yield_quantity'])) {
                $recipe->yield_quantity = $data['yield_quantity'];
            }
            if (isset($data['yield_unit_id'])) {
                $recipe->yield_unit_id = $data['yield_unit_id'];
            }
            $recipe->save();

            if (isset($data['ingredients'])) {
                $recipe->ingredients()->delete();
                foreach ($data['ingredients'] as $ingredient) {
                    $recipe->ingredients()->create([
                        'ingredient_product_id' => $ingredient['ingredient_product_id'],
                        'quantity' => $ingredient['quantity'],
                        'unit_id' => $ingredient['unit_id'] ?? null,
                    ]);
                }
            }
        });

        return $recipe->fresh(['ingredients']);
    }

    public function deductIngredientsForSale(Sale $sale, Store $store): void
    {
        $inventoryService = app(InventoryService::class);

        foreach ($sale->items as $item) {
            $recipe = Recipe::withoutTenantScope()
                ->where('tenant_id', $sale->tenant_id)
                ->where('product_id', $item->product_id)
                ->with('ingredients.ingredientProduct')
                ->first();

            if (!$recipe) {
                continue;
            }

            $ingredientProductIds = $recipe->ingredients->pluck('ingredient_product_id')->toArray();

            $inventories = Inventory::withoutTenantScope()
                ->where('tenant_id', $sale->tenant_id)
                ->where('store_id', $store->id)
                ->whereIn('product_id', $ingredientProductIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            foreach ($recipe->ingredients as $ingredient) {
                $requiredQty = $ingredient->quantity * $item->quantity;
                $inv = $inventories->get($ingredient->ingredient_product_id);
                $currentQty = $inv ? $inv->quantity : 0;

                if ($currentQty < $requiredQty) {
                    $ingredientName = $ingredient->ingredientProduct?->name ?? "Product #{$ingredient->ingredient_product_id}";
                    throw new \DomainException(
                        "Insufficient ingredient: {$ingredientName}. Available: {$currentQty}, Required: {$requiredQty}"
                    );
                }
            }

            foreach ($recipe->ingredients as $ingredient) {
                $requiredQty = $ingredient->quantity * $item->quantity;
                $ingredientProduct = $ingredient->ingredientProduct;

                $inventoryService->decrease(
                    $store,
                    $ingredientProduct,
                    $requiredQty,
                    'adjustment',
                    $sale,
                    "Recipe deduction for sale {$sale->sale_number}",
                );
            }
        }
    }

    public function hasRecipe(int $productId): bool
    {
        return Recipe::withoutTenantScope()
            ->where('product_id', $productId)
            ->exists();
    }
}
