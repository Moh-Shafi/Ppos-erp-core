<?php

namespace App\Http\Controllers;

use App\Services\WarehouseService;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function __construct(
        private readonly WarehouseService $warehouseService = new WarehouseService(),
    ) {}

    public function index(Request $request)
    {
        $query = \App\Models\Warehouse::query();

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->get('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = min((int) $request->get('per_page', 20), 100);

        return response()->json($query->orderBy('name')->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        try {
            $warehouse = $this->warehouseService->createWarehouse($validated, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Warehouse created successfully',
            'warehouse' => $warehouse,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $warehouse = \App\Models\Warehouse::findOrFail($id);

        return response()->json(['warehouse' => $warehouse]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $warehouse = $this->warehouseService->updateWarehouse($id, $validated, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Warehouse updated successfully',
            'warehouse' => $warehouse,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        try {
            $this->warehouseService->deleteWarehouse($id, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json(['message' => 'Warehouse deleted successfully']);
    }

    public function stock(Request $request, $id)
    {
        $stock = $this->warehouseService->getStock($id, $request->user()->tenant_id, $request->only(['search', 'batch_id', 'low_stock', 'per_page']));

        return response()->json($stock);
    }

    public function adjustStock(Request $request, $id)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer',
            'delta' => 'required|integer|not_in:0',
            'reason_id' => 'nullable|integer',
            'batch_id' => 'nullable|integer',
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            $stock = $this->warehouseService->adjustStock(
                $id,
                $validated['product_id'],
                $validated['delta'],
                $request->user()->tenant_id,
                $validated['batch_id'] ?? null,
                $validated['reason_id'] ?? null,
                $validated['note'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Stock adjusted successfully',
            'stock' => $stock,
        ]);
    }
}
