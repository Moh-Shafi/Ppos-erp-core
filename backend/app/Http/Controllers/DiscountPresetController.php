<?php

namespace App\Http\Controllers;

use App\Services\DiscountPresetService;
use Illuminate\Http\Request;

class DiscountPresetController extends Controller
{
    public function __construct(
        private DiscountPresetService $discountPresetService,
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'is_active' => 'nullable|boolean',
        ]);

        $tenantId = $request->user()->tenant_id;
        $isActiveOnly = $validated['is_active'] ?? null;

        return response()->json(
            $this->discountPresetService->list($tenantId, $isActiveOnly)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|string|in:percentage,fixed',
            'value' => 'required|numeric|min:0.01',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validated['type'] === 'percentage' && $validated['value'] > 100) {
            return response()->json(['message' => 'Percentage value cannot exceed 100'], 422);
        }

        try {
            $preset = $this->discountPresetService->create($validated);
            return response()->json($preset, 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'type' => 'nullable|string|in:percentage,fixed',
            'value' => 'nullable|numeric|min:0.01',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if (isset($validated['type']) && isset($validated['value'])) {
            if ($validated['type'] === 'percentage' && $validated['value'] > 100) {
                return response()->json(['message' => 'Percentage value cannot exceed 100'], 422);
            }
        }

        try {
            $preset = $this->discountPresetService->update($id, $validated);
            return response()->json($preset);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function destroy(int $id)
    {
        try {
            $this->discountPresetService->delete($id);
            return response()->json(null, 204);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }
}
