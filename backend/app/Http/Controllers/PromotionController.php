<?php

namespace App\Http\Controllers;

use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function __construct(
        private readonly PromotionService $promotionService = new PromotionService(),
    ) {}

    public function index(Request $request)
    {
        $query = Promotion::query()->with('rules');

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->get('is_active'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        return response()->json($query->orderBy('id', 'desc')->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'type' => 'required|in:percentage,fixed,buy_x_get_y,tiered',
            'value' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'usage_limit' => 'nullable|integer|min:1',
            'conditions' => 'nullable|array',
            'rules' => 'nullable|array',
            'rules.*.rule_type' => 'required|in:min_purchase,min_quantity,category,product,customer_group',
            'rules.*.rule_value' => 'required|array',
        ]);

        $promotion = Promotion::create(collect($validated)->except('rules')->toArray());

        if (!empty($validated['rules'])) {
            foreach ($validated['rules'] as $rule) {
                $promotion->rules()->create([
                    'rule_type' => $rule['rule_type'],
                    'rule_value' => $rule['rule_value'],
                ]);
            }
        }

        return response()->json(['data' => $promotion->load('rules')], 201);
    }

    public function show(int $id)
    {
        return response()->json(['data' => Promotion::with('rules')->findOrFail($id)]);
    }

    public function update(Request $request, int $id)
    {
        $promotion = Promotion::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'description' => 'sometimes|nullable|string',
            'value' => 'sometimes|numeric|min:0',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'usage_limit' => 'sometimes|nullable|integer|min:1',
            'conditions' => 'sometimes|nullable|array',
        ]);

        $promotion->update($validated);
        return response()->json(['data' => $promotion->fresh(['rules'])]);
    }

    public function destroy(int $id)
    {
        Promotion::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    public function activate(int $id)
    {
        $promotion = Promotion::findOrFail($id);
        $promotion->is_active = true;
        $promotion->save();
        return response()->json(['data' => $promotion]);
    }

    public function deactivate(int $id)
    {
        $promotion = Promotion::findOrFail($id);
        $promotion->is_active = false;
        $promotion->save();
        return response()->json(['data' => $promotion]);
    }

    public function validateCart(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|integer',
            'customer_id' => 'nullable|integer',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $tenantId = $request->user()->tenant_id;
        $applicable = $this->promotionService->validate($validated['items'], $tenantId, $validated['customer_id'] ?? null);

        return response()->json(['data' => $applicable]);
    }
}
