<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Services\RecipeService;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    public function __construct(
        private readonly RecipeService $recipeService = new RecipeService(),
    ) {}

    public function index(Request $request)
    {
        $query = Recipe::query()->with(['product', 'ingredients.ingredientProduct']);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        return response()->json($query->orderBy('id', 'desc')->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'yield_quantity' => 'nullable|numeric|min:0.001',
            'yield_unit_id' => 'nullable|integer',
            'ingredients' => 'required|array|min:1',
            'ingredients.*.ingredient_product_id' => 'required|integer|exists:products,id',
            'ingredients.*.quantity' => 'required|numeric|min:0.001',
            'ingredients.*.unit_id' => 'nullable|integer',
        ]);

        $recipe = $this->recipeService->create(
            $validated['product_id'],
            $validated['ingredients'],
            $validated['yield_quantity'] ?? 1,
            $validated['yield_unit_id'] ?? null,
        );

        return response()->json(['data' => $recipe->load(['product', 'ingredients.ingredientProduct'])], 201);
    }

    public function show(int $id)
    {
        return response()->json(['data' => Recipe::with(['product', 'ingredients.ingredientProduct'])->findOrFail($id)]);
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'yield_quantity' => 'sometimes|numeric|min:0.001',
            'yield_unit_id' => 'sometimes|nullable|integer',
            'ingredients' => 'sometimes|array|min:1',
            'ingredients.*.ingredient_product_id' => 'required_with:ingredients|integer|exists:products,id',
            'ingredients.*.quantity' => 'required_with:ingredients|numeric|min:0.001',
            'ingredients.*.unit_id' => 'nullable|integer',
        ]);

        $recipe = $this->recipeService->update($id, $validated);
        return response()->json(['data' => $recipe->load(['product', 'ingredients.ingredientProduct'])]);
    }

    public function destroy(int $id)
    {
        Recipe::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
