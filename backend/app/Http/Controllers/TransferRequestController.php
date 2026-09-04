<?php

namespace App\Http\Controllers;

use App\Services\TransferRequestService;
use Illuminate\Http\Request;

class TransferRequestController extends Controller
{
    public function __construct(
        private readonly TransferRequestService $transferRequestService = new TransferRequestService(),
    ) {}

    public function index(Request $request)
    {
        $query = \App\Models\TransferRequest::query()
            ->with('items.product', 'fromStore', 'fromWarehouse', 'toStore', 'toWarehouse', 'requestedBy', 'approvedBy');

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('from_store_id')) {
            $query->where('from_store_id', (int) $request->get('from_store_id'));
        }

        if ($request->filled('to_store_id')) {
            $query->where('to_store_id', (int) $request->get('to_store_id'));
        }

        $perPage = min((int) $request->get('per_page', 20), 100);

        return response()->json($query->orderBy('created_at', 'desc')->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_store_id' => 'nullable|integer',
            'from_warehouse_id' => 'nullable|integer',
            'to_store_id' => 'nullable|integer',
            'to_warehouse_id' => 'nullable|integer',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.batch_id' => 'nullable|integer',
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            $request_model = $this->transferRequestService->createRequest($validated, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Transfer request created successfully',
            'transfer_request' => $request_model,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $transferRequest = \App\Models\TransferRequest::with('items.product', 'fromStore', 'fromWarehouse', 'toStore', 'toWarehouse', 'requestedBy', 'approvedBy')
            ->findOrFail($id);

        return response()->json(['transfer_request' => $transferRequest]);
    }

    public function submit(Request $request, $id)
    {
        try {
            $request_model = $this->transferRequestService->submit($id, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Transfer request submitted',
            'transfer_request' => $request_model,
        ]);
    }

    public function approve(Request $request, $id)
    {
        try {
            $request_model = $this->transferRequestService->approve($id, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Transfer request approved',
            'transfer_request' => $request_model,
        ]);
    }

    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $request_model = $this->transferRequestService->reject($id, $request->user()->tenant_id, $validated['reason'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Transfer request rejected',
            'transfer_request' => $request_model,
        ]);
    }

    public function transit(Request $request, $id)
    {
        try {
            $request_model = $this->transferRequestService->startTransit($id, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Transfer in transit started',
            'transfer_request' => $request_model,
        ]);
    }

    public function complete(Request $request, $id)
    {
        try {
            $request_model = $this->transferRequestService->complete($id, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Transfer completed successfully',
            'transfer_request' => $request_model,
        ]);
    }

    public function cancel(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $request_model = $this->transferRequestService->cancel($id, $request->user()->tenant_id, $validated['reason'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Transfer request cancelled',
            'transfer_request' => $request_model,
        ]);
    }
}
