<?php

namespace App\Http\Controllers;

use App\Models\RestaurantTable;
use App\Models\TableArea;
use App\Services\TableService;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function __construct(
        private readonly TableService $tableService = new TableService(),
    ) {}

    public function index(Request $request)
    {
        $query = RestaurantTable::query()->with(['area']);

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->get('store_id'));
        }
        if ($request->filled('area_id')) {
            $query->where('table_area_id', (int) $request->get('area_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        return response()->json($query->orderBy('name')->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|integer',
            'table_area_id' => 'required|integer',
            'name' => 'required|string|max:50',
            'code' => 'required|string|max:20',
            'capacity' => 'nullable|integer|min:1',
        ]);

        $table = $this->tableService->createTable(
            $validated['store_id'],
            $validated['table_area_id'],
            $validated
        );

        return response()->json(['data' => $table], 201);
    }

    public function show(int $id)
    {
        $table = RestaurantTable::with(['area', 'currentSale'])->findOrFail($id);
        return response()->json(['data' => $table]);
    }

    public function update(Request $request, int $id)
    {
        $table = RestaurantTable::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:50',
            'code' => 'sometimes|string|max:20',
            'capacity' => 'sometimes|integer|min:1',
            'table_area_id' => 'sometimes|integer',
        ]);

        $table->update($validated);
        return response()->json(['data' => $table->fresh(['area'])]);
    }

    public function destroy(int $id)
    {
        RestaurantTable::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    public function updateStatus(Request $request, int $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:available,occupied,reserved,cleaning',
        ]);

        $table = $this->tableService->updateTableStatus($id, $validated['status']);
        return response()->json(['data' => $table]);
    }

    public function generateQrCode(int $id)
    {
        $table = $this->tableService->generateQrCode($id);
        return response()->json(['data' => ['qr_code' => $table->qr_code]]);
    }

    public function areasIndex(Request $request)
    {
        $query = TableArea::query()->withCount('tables');

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->get('store_id'));
        }

        return response()->json(['data' => $query->orderBy('sort_order')->get()]);
    }

    public function areasStore(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|integer',
            'name' => 'required|string|max:100',
            'sort_order' => 'nullable|integer',
        ]);

        $area = $this->tableService->createArea(
            $validated['store_id'],
            $validated['name'],
            $validated['sort_order'] ?? 0,
        );

        return response()->json(['data' => $area], 201);
    }

    public function areasUpdate(Request $request, int $id)
    {
        $area = TableArea::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'sort_order' => 'sometimes|integer',
        ]);

        $area->update($validated);
        return response()->json(['data' => $area->fresh()]);
    }

    public function areasDestroy(int $id)
    {
        TableArea::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
