<?php

namespace App\Http\Controllers;

use App\Models\KotHeader;
use App\Services\KotService;
use Illuminate\Http\Request;

class KotController extends Controller
{
    public function __construct(
        private readonly KotService $kotService = new KotService(),
    ) {}

    public function index(Request $request)
    {
        $query = KotHeader::query()->with(['items.product', 'table']);

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->get('store_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->get('date'));
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        return response()->json($query->orderBy('created_at', 'desc')->paginate($perPage));
    }

    public function show(int $id)
    {
        $kot = KotHeader::with(['items.product', 'table', 'sale', 'createdBy'])->findOrFail($id);
        return response()->json(['data' => $kot]);
    }

    public function generate(int $saleId)
    {
        $sale = \App\Models\Sale::findOrFail($saleId);
        $kot = $this->kotService->generateFromSale($sale);
        return response()->json(['data' => $kot], 201);
    }

    public function updateStatus(Request $request, int $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:preparing,ready,served,cancelled',
        ]);

        return response()->json(['data' => $this->kotService->updateStatus($id, $validated['status'])]);
    }

    public function updateItemStatus(Request $request, int $itemId)
    {
        $validated = $request->validate([
            'status' => 'required|in:queued,preparing,ready,served',
        ]);

        return response()->json(['data' => $this->kotService->updateItemStatus($itemId, $validated['status'])]);
    }

    public function kdsQueue(Request $request)
    {
        $storeId = (int) $request->get('store_id', $request->header('X-Store-Id'));
        if (!$storeId) {
            return response()->json(['message' => 'store_id is required'], 422);
        }

        $queue = $this->kotService->getKdsQueue($storeId);
        return response()->json(['data' => $queue]);
    }
}
