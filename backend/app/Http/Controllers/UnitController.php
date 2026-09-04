<?php

namespace App\Http\Controllers;

use App\Services\UnitService;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function __construct(
        private readonly UnitService $unitService = new UnitService(),
    ) {}

    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 20);
        $units = \App\Models\Unit::query()
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json($units);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'symbol' => 'required|string|max:20',
            'is_base_unit' => 'boolean',
        ]);

        try {
            $unit = $this->unitService->createUnit($validated, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Unit created successfully',
            'unit' => $unit,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $unit = \App\Models\Unit::findOrFail($id);

        return response()->json(['unit' => $unit]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:50',
            'symbol' => 'sometimes|string|max:20',
            'is_base_unit' => 'sometimes|boolean',
        ]);

        try {
            $unit = $this->unitService->updateUnit($id, $validated, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Unit updated successfully',
            'unit' => $unit,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        try {
            $this->unitService->deleteUnit($id, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json(['message' => 'Unit deleted successfully']);
    }

    public function storeConversion(Request $request)
    {
        $validated = $request->validate([
            'from_unit_id' => 'required|integer',
            'to_unit_id' => 'required|integer',
            'factor' => 'required|numeric|min:0',
        ]);

        try {
            $conversion = $this->unitService->addConversion(
                $validated['from_unit_id'],
                $validated['to_unit_id'],
                $validated['factor'],
                $request->user()->tenant_id
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Conversion created successfully',
            'conversion' => $conversion,
        ], 201);
    }

    public function destroyConversion(Request $request, $id)
    {
        try {
            $this->unitService->deleteConversion($id, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json(['message' => 'Conversion deleted successfully']);
    }
}
