<?php

namespace App\Http\Controllers;

use App\Models\PurchaseRequisition;
use App\Services\RequisitionService;
use Illuminate\Http\Request;

class RequisitionController extends Controller
{
    public function __construct(
        private readonly RequisitionService $requisitionService,
    ) {}

    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 20), 100);
        $filters = $request->only(['status', 'store_id']);

        return response()->json($this->requisitionService->list($filters, $perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
            'note' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.estimated_cost' => 'nullable|numeric|min:0',
            'items.*.note' => 'nullable|string|max:500',
        ]);

        try {
            $requisition = $this->requisitionService->create($validated);
            return response()->json($requisition, 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(int $id)
    {
        return response()->json($this->requisitionService->find($id));
    }

    public function update(Request $request, int $id)
    {
        $requisition = PurchaseRequisition::findOrFail($id);

        $validated = $request->validate([
            'note' => 'nullable|string|max:2000',
            'items' => 'sometimes|array|min:1',
            'items.*.product_id' => 'required_with:items|integer|exists:products,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.estimated_cost' => 'nullable|numeric|min:0',
            'items.*.note' => 'nullable|string|max:500',
        ]);

        try {
            $requisition = $this->requisitionService->update($requisition, $validated);
            return response()->json($requisition);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(int $id)
    {
        $requisition = PurchaseRequisition::findOrFail($id);

        try {
            $this->requisitionService->delete($requisition);
            return response()->json(['message' => 'Requisition deleted']);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function submit(int $id)
    {
        $requisition = PurchaseRequisition::findOrFail($id);

        try {
            return response()->json($this->requisitionService->submit($requisition));
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function approve(int $id)
    {
        $requisition = PurchaseRequisition::findOrFail($id);

        try {
            return response()->json($this->requisitionService->approve($requisition));
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function reject(Request $request, int $id)
    {
        $requisition = PurchaseRequisition::findOrFail($id);

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:2000',
        ]);

        try {
            return response()->json($this->requisitionService->reject($requisition, $validated['rejection_reason']));
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function cancel(int $id)
    {
        $requisition = PurchaseRequisition::findOrFail($id);

        try {
            return response()->json($this->requisitionService->cancel($requisition));
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function convert(Request $request, int $id)
    {
        $requisition = PurchaseRequisition::findOrFail($id);

        $validated = $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        try {
            $purchase = $this->requisitionService->convertToPo($requisition, $validated);
            return response()->json($purchase, 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
