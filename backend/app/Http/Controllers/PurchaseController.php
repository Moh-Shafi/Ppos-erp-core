<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Services\PurchaseService;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(
        private PurchaseService $purchaseService,
    ) {}

    public function index(Request $request)
    {
        $query = Purchase::with(['supplier', 'store', 'items.product']);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('purchase_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->get('supplier_id'));
        }

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->get('store_id'));
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $purchases = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($purchases);
    }

    public function show(Request $request, int $id)
    {
        $purchase = Purchase::with(['supplier', 'store', 'items.product', 'createdBy'])
            ->findOrFail($id);

        return response()->json($purchase);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'store_id' => 'required|integer|exists:stores,id',
            'purchase_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:purchase_date',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
        ]);

        try {
            $purchase = $this->purchaseService->create($validated);
            return response()->json($purchase, 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, int $id)
    {
        $purchase = Purchase::findOrFail($id);

        $validated = $request->validate([
            'supplier_id' => 'sometimes|integer|exists:suppliers,id',
            'store_id' => 'sometimes|integer|exists:stores,id',
            'expected_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
            'items' => 'sometimes|array|min:1',
            'items.*.product_id' => 'required_with:items|integer|exists:products,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.unit_cost' => 'required_with:items|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
        ]);

        try {
            $purchase = $this->purchaseService->update($purchase, $validated);
            return response()->json($purchase);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function order(int $id)
    {
        $purchase = Purchase::findOrFail($id);

        try {
            $purchase = $this->purchaseService->order($purchase);
            return response()->json($purchase);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function receive(int $id)
    {
        $purchase = Purchase::findOrFail($id);

        try {
            $purchase = $this->purchaseService->receive($purchase);
            return response()->json($purchase);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function cancel(int $id)
    {
        $purchase = Purchase::findOrFail($id);

        try {
            $purchase = $this->purchaseService->cancel($purchase);
            return response()->json($purchase);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, int $id)
    {
        $purchase = Purchase::findOrFail($id);

        if ($purchase->status !== 'draft') {
            return response()->json(['message' => 'Only draft purchases can be deleted'], 422);
        }

        $purchase->delete();

        return response()->json(['message' => 'Purchase deleted'], 200);
    }
}
