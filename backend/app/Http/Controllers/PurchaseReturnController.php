<?php

namespace App\Http\Controllers;

use App\Models\PurchaseReturn;
use App\Services\PurchaseReturnService;
use Illuminate\Http\Request;

class PurchaseReturnController extends Controller
{
    public function __construct(
        private PurchaseReturnService $returnService,
    ) {}

    public function index(Request $request)
    {
        $query = PurchaseReturn::with(['purchase', 'store', 'items.product']);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('purchase_id')) {
            $query->where('purchase_id', $request->get('purchase_id'));
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $returns = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($returns);
    }

    public function show(int $id)
    {
        $return = PurchaseReturn::with(['purchase', 'store', 'items.product', 'createdBy'])
            ->findOrFail($id);

        return response()->json($return);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'purchase_id' => 'required|integer|exists:purchases,id',
            'store_id' => 'nullable|integer|exists:stores,id',
            'return_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
        ]);

        try {
            $return = $this->returnService->create($validated);
            return response()->json($return, 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function complete(int $id)
    {
        $return = PurchaseReturn::findOrFail($id);

        try {
            $return = $this->returnService->complete($return);
            return response()->json($return);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function cancel(int $id)
    {
        $return = PurchaseReturn::findOrFail($id);

        try {
            $return = $this->returnService->cancel($return);
            return response()->json($return);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(int $id)
    {
        $return = PurchaseReturn::findOrFail($id);

        try {
            $this->returnService->delete($return);
            return response()->json(['message' => 'Return deleted'], 200);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
