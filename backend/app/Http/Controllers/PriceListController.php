<?php

namespace App\Http\Controllers;

use App\Services\PriceListService;
use Illuminate\Http\Request;

class PriceListController extends Controller
{
    public function __construct(
        private readonly PriceListService $priceListService = new PriceListService(),
    ) {}

    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 20);
        $priceLists = \App\Models\PriceList::query()
            ->withCount('items')
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json($priceLists);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        try {
            $priceList = $this->priceListService->createPriceList($validated, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Price list created successfully',
            'price_list' => $priceList,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $priceList = \App\Models\PriceList::with('items.product', 'items.variant')->findOrFail($id);

        return response()->json(['price_list' => $priceList]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $priceList = $this->priceListService->updatePriceList($id, $validated, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Price list updated successfully',
            'price_list' => $priceList,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        try {
            $this->priceListService->deletePriceList($id, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json(['message' => 'Price list deleted successfully']);
    }

    public function storeItem(Request $request, $id)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer',
            'variant_id' => 'nullable|integer',
            'price' => 'required|numeric|min:0',
        ]);

        try {
            $item = $this->priceListService->addItem($id, $validated, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Price list item added successfully',
            'item' => $item,
        ], 201);
    }

    public function updateItem(Request $request, $id, $itemId)
    {
        $validated = $request->validate([
            'price' => 'sometimes|numeric|min:0',
        ]);

        try {
            $item = $this->priceListService->updateItem($id, $itemId, $validated, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Price list item updated successfully',
            'item' => $item,
        ]);
    }

    public function destroyItem(Request $request, $id, $itemId)
    {
        try {
            $this->priceListService->deleteItem($id, $itemId, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json(['message' => 'Price list item removed successfully']);
    }
}
