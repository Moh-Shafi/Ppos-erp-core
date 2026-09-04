<?php

namespace App\Http\Controllers;

use App\Services\HoldSaleService;
use Illuminate\Http\Request;

class HeldSaleController extends Controller
{
    public function __construct(
        private HoldSaleService $holdSaleService,
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|integer',
            'status' => 'nullable|string|in:held,recalled,expired',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $tenantId = $request->user()->tenant_id;
        $status = $validated['status'] ?? 'held';
        $perPage = $validated['per_page'] ?? 20;

        return response()->json(
            $this->holdSaleService->list($tenantId, $validated['store_id'], $status, $perPage)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'cart_data' => 'required|array',
            'cart_data.items' => 'required|array|min:1|max:100',
            'cart_data.items.*.product_id' => 'required|integer|exists:products,id',
            'cart_data.items.*.variant_id' => 'nullable|integer|exists:product_variants,id',
            'cart_data.items.*.quantity' => 'required|integer|min:1',
            'cart_data.customer_id' => 'nullable|integer',
            'cart_data.discount' => 'nullable|numeric|min:0',
            'cart_data.tax' => 'nullable|numeric|min:0',
            'cart_data.notes' => 'nullable|string|max:2000',
        ]);

        try {
            $heldSale = $this->holdSaleService->hold($validated);
            return response()->json($heldSale, 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function recall(int $id)
    {
        try {
            $heldSale = $this->holdSaleService->recall($id);
            return response()->json($heldSale);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(int $id)
    {
        try {
            $this->holdSaleService->delete($id);
            return response()->json(null, 204);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }
}
