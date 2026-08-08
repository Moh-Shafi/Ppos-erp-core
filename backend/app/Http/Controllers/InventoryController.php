<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Store;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {}

    public function index(Request $request)
    {
        $query = Inventory::query()->with(['store', 'product']);

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->get('store_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->get('product_id'));
        }

        if ($request->has('low_stock')) {
            $query->whereColumn('quantity', '<=', 'minimum_quantity');
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $inventories = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($inventories);
    }

    public function show(Request $request, int $productId)
    {
        $query = Inventory::query()->with(['store', 'product'])
            ->where('product_id', $productId);

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->get('store_id'));
        }

        $inventories = $query->get();

        if ($inventories->isEmpty()) {
            return response()->json(['message' => 'Inventory not found'], 404);
        }

        return response()->json([
            'inventories' => $inventories,
        ]);
    }

    public function adjust(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'store_id' => [
                'required',
                'integer',
                Rule::exists('stores', 'id')->where('tenant_id', $tenantId),
            ],
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('tenant_id', $tenantId),
            ],
            'delta' => 'required|integer|not_in:0',
            'note' => 'nullable|string|max:500',
        ]);

        $store = Store::findOrFail($validated['store_id']);
        $product = Product::findOrFail($validated['product_id']);

        try {
            $movement = $this->inventoryService->adjust(
                $store,
                $product,
                $validated['delta'],
                null,
                $validated['note'] ?? null,
            );

            return response()->json([
                'message' => 'Stock adjusted successfully',
                'movement' => $movement->load(['product', 'store']),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function movements(Request $request)
    {
        $query = InventoryMovement::query()->with(['product', 'store', 'user']);

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->get('store_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->get('product_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $movements = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($movements);
    }

    public function transfer(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'from_store_id' => [
                'required',
                'integer',
                'different:to_store_id',
                Rule::exists('stores', 'id')->where('tenant_id', $tenantId),
            ],
            'to_store_id' => [
                'required',
                'integer',
                'different:from_store_id',
                Rule::exists('stores', 'id')->where('tenant_id', $tenantId),
            ],
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('tenant_id', $tenantId),
            ],
            'quantity' => 'required|integer|min:1',
            'note' => 'nullable|string|max:500',
        ]);

        $fromStore = Store::findOrFail($validated['from_store_id']);
        $toStore = Store::findOrFail($validated['to_store_id']);
        $product = Product::findOrFail($validated['product_id']);

        try {
            $result = $this->inventoryService->transfer(
                $fromStore,
                $toStore,
                $product,
                $validated['quantity'],
                $validated['note'] ?? null,
            );

            return response()->json([
                'message' => 'Transfer completed successfully',
                'out_movement' => $result['out']->load(['product', 'store']),
                'in_movement' => $result['in']->load(['product', 'store']),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
