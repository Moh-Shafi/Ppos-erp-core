<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceiptNote;
use App\Models\Purchase;
use App\Services\GrnService;
use Illuminate\Http\Request;

class GrnController extends Controller
{
    public function __construct(
        private readonly GrnService $grnService,
    ) {}

    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 20), 100);
        $filters = $request->only(['status', 'supplier_id', 'store_id']);

        return response()->json($this->grnService->list($filters, $perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'note' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity_ordered' => 'nullable|integer|min:0',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        try {
            $grn = $this->grnService->create($validated);
            return response()->json($grn, 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function createFromPo(int $poId, Request $request)
    {
        $purchase = Purchase::findOrFail($poId);

        $validated = $request->validate([
            'note' => 'nullable|string|max:2000',
        ]);

        try {
            $grn = $this->grnService->createFromPo($purchase, $validated['note'] ?? null);
            return response()->json($grn, 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(int $id)
    {
        return response()->json($this->grnService->find($id));
    }

    public function receive(Request $request, int $id)
    {
        $grn = GoodsReceiptNote::findOrFail($id);

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.quantity_received' => 'required|integer|min:0',
            'items.*.quantity_rejected' => 'nullable|integer|min:0',
            'items.*.rejection_reason' => 'nullable|string|max:500',
            'items.*.batch_id' => 'nullable|integer|exists:stock_batches,id',
            'items.*.expiry_date' => 'nullable|date',
            'items.*.note' => 'nullable|string|max:500',
        ]);

        try {
            $grn = $this->grnService->receive($grn, $validated['items']);
            return response()->json($grn);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function cancel(int $id)
    {
        $grn = GoodsReceiptNote::findOrFail($id);

        try {
            return response()->json($this->grnService->cancel($grn));
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
