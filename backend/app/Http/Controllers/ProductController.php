<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Services\CatalogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalogService = new CatalogService(),
    ) {}

    public function index(Request $request)
    {
        $query = Product::query()->with('category');

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->get('category_id'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->get('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('has_variants')) {
            $query->where('has_variants', filter_var($request->get('has_variants'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('is_trackable')) {
            $query->where('is_trackable', filter_var($request->get('is_trackable'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $products = $query->orderBy('name')->paginate($perPage);

        // Append inventory stock for current store
        $storeId = $request->header('X-Store-Id');
        if ($storeId) {
            $productIds = $products->getCollection()->pluck('id')->toArray();
            $inventories = Inventory::where('store_id', $storeId)
                ->whereIn('product_id', $productIds)
                ->pluck('quantity', 'product_id');
            $products->getCollection()->each(function ($product) use ($inventories) {
                $product->stock_quantity = $inventories->get($product->id);
            });
        }

        return response()->json($products);
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png,webp,gif|max:5120',
        ]);

        try {
            $file = $request->file('image');
            $tenantId = $request->user()->tenant_id;
            $path = $file->store("products/{$tenantId}", 'public');

            $url = '/storage/' . $path;

            return response()->json([
                'message' => 'Image uploaded successfully',
                'url' => $url,
                'path' => $path,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('tenant_id', $tenantId),
            ],
            'name' => 'required|string|max:255',
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId)->whereNotNull('sku');
                }),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId)->whereNotNull('barcode');
                }),
            ],
            'description' => 'nullable|string',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'unit' => 'required|string|max:50',
            'image' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'has_variants' => 'boolean',
            'is_trackable' => 'boolean',
            'min_stock' => 'nullable|integer|min:0',
            'base_unit_id' => 'nullable|integer',
            'purchase_unit_id' => 'nullable|integer',
            'images' => 'nullable|array',
            'images.*.url' => 'required|string|max:500',
            'images.*.sort_order' => 'nullable|integer',
            'barcodes' => 'nullable|array',
            'barcodes.*' => 'string|max:100',
            'variant_options' => 'nullable|array',
            'variant_options.*.name' => 'required|string|max:100',
            'variant_options.*.sort_order' => 'nullable|integer',
            'variant_options.*.values' => 'required|array|min:1',
            'variant_options.*.values.*.value' => 'required|string|max:100',
            'variant_options.*.values.*.sort_order' => 'nullable|integer',
            'variants' => 'nullable|array',
            'variants.*.option_value_ids' => 'required|array|min:1',
            'variants.*.option_value_ids.*' => 'required',
            'variants.*.sku' => 'nullable|string|max:100',
            'variants.*.barcode' => 'nullable|string|max:100',
            'variants.*.price_override' => 'nullable|numeric|min:0',
            'variants.*.cost_price_override' => 'nullable|numeric|min:0',
            'variants.*.is_active' => 'boolean',
        ]);

        try {
            $product = $this->catalogService->createProduct($validated, $tenantId);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $product = Product::with(['category', 'variants.optionValues.option', 'images', 'barcodes', 'variantOptions.values'])->findOrFail($id);

        return response()->json([
            'product' => $product,
        ]);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'category_id' => [
                'sometimes',
                'integer',
                Rule::exists('categories', 'id')->where('tenant_id', $tenantId),
            ],
            'name' => 'sometimes|string|max:255',
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId)->whereNotNull('sku');
                })->ignore($product->id),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId)->whereNotNull('barcode');
                })->ignore($product->id),
            ],
            'description' => 'nullable|string',
            'cost_price' => 'sometimes|numeric|min:0',
            'selling_price' => 'sometimes|numeric|min:0',
            'unit' => 'sometimes|string|max:50',
            'image' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'has_variants' => 'sometimes|boolean',
            'is_trackable' => 'sometimes|boolean',
            'min_stock' => 'nullable|integer|min:0',
            'base_unit_id' => 'nullable|integer',
            'purchase_unit_id' => 'nullable|integer',
        ]);

        try {
            $product = $this->catalogService->updateProduct($id, $validated, $tenantId);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        try {
            $this->catalogService->deleteProduct($id, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }
}
