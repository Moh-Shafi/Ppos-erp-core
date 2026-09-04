<?php

namespace App\Http\Controllers;

use App\Services\AdjustmentReasonService;
use Illuminate\Http\Request;

class AdjustmentReasonController extends Controller
{
    public function __construct(
        private readonly AdjustmentReasonService $reasonService = new AdjustmentReasonService(),
    ) {}

    public function index(Request $request)
    {
        $reasons = $this->reasonService->listReasons($request->user()->tenant_id, $request->only(['is_active', 'category']));

        return response()->json(['data' => $reasons]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|in:damaged,lost,found,recount,initial,other',
            'is_active' => 'boolean',
        ]);

        try {
            $reason = $this->reasonService->createReason($validated, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Adjustment reason created successfully',
            'reason' => $reason,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $reason = $this->reasonService->updateReason($id, $validated, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Adjustment reason updated successfully',
            'reason' => $reason,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        try {
            $this->reasonService->deleteReason($id, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json(['message' => 'Adjustment reason deleted successfully']);
    }
}
