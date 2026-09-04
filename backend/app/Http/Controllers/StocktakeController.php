<?php

namespace App\Http\Controllers;

use App\Services\StocktakeService;
use Illuminate\Http\Request;

class StocktakeController extends Controller
{
    public function __construct(
        private readonly StocktakeService $stocktakeService = new StocktakeService(),
    ) {}

    public function index(Request $request)
    {
        $query = \App\Models\StocktakeSession::query()
            ->with('store', 'createdBy')
            ->withCount('items');

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->get('store_id'));
        }

        $perPage = min((int) $request->get('per_page', 20), 100);

        return response()->json($query->orderBy('created_at', 'desc')->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|integer',
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            $session = $this->stocktakeService->createSession($validated['store_id'], $request->user()->tenant_id, $validated['note'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Stocktake session created successfully',
            'stocktake' => $session,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $session = \App\Models\StocktakeSession::with('items.product', 'store', 'createdBy')
            ->findOrFail($id);

        return response()->json(['stocktake' => $session]);
    }

    public function start(Request $request, $id)
    {
        try {
            $session = $this->stocktakeService->startCounting($id, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Stocktake counting started',
            'stocktake' => $session,
        ]);
    }

    public function updateItem(Request $request, $id, $itemId)
    {
        $validated = $request->validate([
            'counted_quantity' => 'required|integer|min:0',
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            $item = $this->stocktakeService->updateItem($id, $itemId, $validated['counted_quantity'], $validated['note'] ?? null, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Item updated successfully',
            'item' => $item,
        ]);
    }

    public function reconcile(Request $request, $id)
    {
        try {
            $session = $this->stocktakeService->reconcile($id, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Stocktake reconciled',
            'stocktake' => $session,
        ]);
    }

    public function post(Request $request, $id)
    {
        $validated = $request->validate([
            'reason_id' => 'required|integer',
        ]);

        try {
            $session = $this->stocktakeService->post($id, $validated['reason_id'], $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Stocktake posted successfully',
            'stocktake' => $session,
        ]);
    }

    public function cancel(Request $request, $id)
    {
        try {
            $session = $this->stocktakeService->cancel($id, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Stocktake cancelled',
            'stocktake' => $session,
        ]);
    }
}
