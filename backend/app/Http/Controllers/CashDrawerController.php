<?php

namespace App\Http\Controllers;

use App\Models\CashDrawerSession;
use App\Services\CashDrawerService;
use Illuminate\Http\Request;

class CashDrawerController extends Controller
{
    public function __construct(
        private CashDrawerService $cashDrawerService,
    ) {}

    /**
     * List cash drawer sessions.
     */
    public function index(Request $request)
    {
        $query = CashDrawerSession::query();

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->get('store_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $sessions = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($sessions);
    }

    /**
     * Open a new cash drawer session.
     */
    public function open(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
            'opening_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);

        $tenantId = $request->user()->tenant_id;

        try {
            $session = $this->cashDrawerService->open($tenantId, $validated);
            return response()->json(['data' => $session], 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Close a cash drawer session.
     */
    public function close(Request $request, int $id)
    {
        $session = CashDrawerSession::findOrFail($id);

        $validated = $request->validate([
            'closing_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            $session = $this->cashDrawerService->close($session, $validated);
            return response()->json(['data' => $session]);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Show cash drawer session details.
     */
    public function show(int $id)
    {
        $session = CashDrawerSession::findOrFail($id);

        return response()->json(['data' => $session]);
    }

    /**
     * Reconcile a closed session.
     */
    public function reconcile(Request $request, int $id)
    {
        $session = CashDrawerSession::findOrFail($id);

        $validated = $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            $session = $this->cashDrawerService->reconcile($session, $validated['notes'] ?? null);
            return response()->json(['data' => $session]);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
