<?php

namespace App\Http\Controllers;

use App\Models\ServiceCatalog;
use App\Services\ServiceCatalogService;
use Illuminate\Http\Request;

class ServiceCatalogController extends Controller
{
    public function __construct(
        private readonly ServiceCatalogService $serviceCatalogService = new ServiceCatalogService(),
    ) {}

    public function index(Request $request)
    {
        $query = ServiceCatalog::query()->with('product');

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        return response()->json($query->orderBy('id', 'desc')->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|integer',
            'selling_price' => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:1',
            'is_recurring' => 'boolean',
            'recurring_interval' => 'nullable|in:daily,weekly,monthly',
            'buffer_time_minutes' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
        ]);

        $service = $this->serviceCatalogService->create($validated);
        return response()->json(['data' => $service->load('product')], 201);
    }

    public function show(int $id)
    {
        return response()->json(['data' => ServiceCatalog::with('product')->findOrFail($id)]);
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'selling_price' => 'sometimes|numeric|min:0',
            'duration_minutes' => 'sometimes|integer|min:1',
            'buffer_time_minutes' => 'sometimes|integer|min:0',
        ]);

        $service = $this->serviceCatalogService->update($id, $validated);
        return response()->json(['data' => $service]);
    }

    public function destroy(int $id)
    {
        $this->serviceCatalogService->delete($id);
        return response()->json(null, 204);
    }
}
