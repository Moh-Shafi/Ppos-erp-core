<?php

namespace App\Http\Controllers;

use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Models\Product;
use App\Services\ModifierService;
use Illuminate\Http\Request;

class ModifierController extends Controller
{
    public function __construct(
        private readonly ModifierService $modifierService = new ModifierService(),
    ) {}

    public function index(Request $request)
    {
        $query = Modifier::query()->with('options');

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        return response()->json($query->orderBy('name')->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:single,multiple',
            'is_required' => 'boolean',
        ]);

        $modifier = $this->modifierService->create(
            $validated['name'],
            $validated['type'],
            $validated['is_required'] ?? false,
        );

        return response()->json(['data' => $modifier], 201);
    }

    public function show(int $id)
    {
        return response()->json(['data' => Modifier::with('options')->findOrFail($id)]);
    }

    public function update(Request $request, int $id)
    {
        $modifier = Modifier::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'type' => 'sometimes|in:single,multiple',
            'is_required' => 'sometimes|boolean',
        ]);

        $modifier->update($validated);
        return response()->json(['data' => $modifier->fresh(['options'])]);
    }

    public function destroy(int $id)
    {
        Modifier::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    public function storeOption(Request $request, int $modifierId)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'price_delta' => 'required|numeric',
            'sort_order' => 'nullable|integer',
        ]);

        $option = $this->modifierService->addOption(
            $modifierId,
            $validated['name'],
            (float) $validated['price_delta'],
            $validated['sort_order'] ?? 0,
        );

        return response()->json(['data' => $option], 201);
    }

    public function updateOption(Request $request, int $modifierId, int $optionId)
    {
        $option = ModifierOption::where('modifier_id', $modifierId)->where('id', $optionId)->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'price_delta' => 'sometimes|numeric',
            'sort_order' => 'sometimes|integer',
        ]);

        $option->update($validated);
        return response()->json(['data' => $option->fresh()]);
    }

    public function destroyOption(int $modifierId, int $optionId)
    {
        ModifierOption::where('modifier_id', $modifierId)->where('id', $optionId)->firstOrFail()->delete();
        return response()->json(null, 204);
    }

    public function productModifiers(int $productId)
    {
        $product = Product::findOrFail($productId);
        return response()->json(['data' => $product->modifiers()->with('options')->get()]);
    }

    public function attachToProduct(Request $request, int $productId)
    {
        $validated = $request->validate(['modifier_id' => 'required|integer']);
        $this->modifierService->attachToProduct($productId, $validated['modifier_id']);
        return response()->json(['message' => 'Modifier attached'], 201);
    }

    public function detachFromProduct(int $productId, int $modifierId)
    {
        $this->modifierService->detachFromProduct($productId, $modifierId);
        return response()->json(null, 204);
    }
}
