<?php

namespace App\Http\Controllers;

use App\Services\StockValuationService;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\StockBatch;
use Illuminate\Http\Request;

class InventoryReportController extends Controller
{
    public function __construct(
        private readonly StockValuationService $valuationService = new StockValuationService(),
    ) {}

    public function summary(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $query = Inventory::where('tenant_id', $tenantId)->with('product', 'store');

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->get('store_id'));
        }

        $inventories = $query->get();

        $grouped = $inventories->groupBy('product_id')->map(function ($items, $productId) {
            $product = $items->first()->product;
            $totalQuantity = $items->sum('quantity');
            $totalValue = $totalQuantity * (float) ($product->cost_price ?? 0);

            return [
                'product_id' => $productId,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                ],
                'total_quantity' => $totalQuantity,
                'total_value' => number_format($totalValue, 2, '.', ''),
                'stores' => $items->map(fn ($inv) => [
                    'store_id' => $inv->store_id,
                    'store_name' => $inv->store?->name,
                    'quantity' => $inv->quantity,
                ]),
            ];
        })->values();

        return response()->json(['data' => $grouped]);
    }

    public function valuation(Request $request)
    {
        $method = $request->get('method', 'average');
        if (!in_array($method, ['fifo', 'lifo', 'average'])) {
            return response()->json(['message' => 'Invalid valuation method'], 422);
        }

        $result = $this->valuationService->calculate(
            $request->user()->tenant_id,
            $method,
            $request->filled('store_id') ? (int) $request->get('store_id') : null,
        );

        return response()->json($result);
    }

    public function lowStock(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $query = Inventory::where('tenant_id', $tenantId)
            ->with('product', 'store')
            ->whereColumn('quantity', '<=', 'minimum_quantity');

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->get('store_id'));
        }

        $items = $query->get();

        $data = $items->map(function ($inv) {
            $suggestedReorder = $inv->maximum_quantity ? max(0, $inv->maximum_quantity - $inv->quantity) : null;
            $status = $inv->quantity <= 0 ? 'out_of_stock' : 'low_stock';

            return [
                'product_id' => $inv->product_id,
                'product' => [
                    'id' => $inv->product->id,
                    'name' => $inv->product->name,
                    'sku' => $inv->product->sku,
                ],
                'store_id' => $inv->store_id,
                'store' => [
                    'id' => $inv->store->id,
                    'name' => $inv->store->name,
                ],
                'current_qty' => $inv->quantity,
                'min_qty' => $inv->minimum_quantity,
                'max_qty' => $inv->maximum_quantity,
                'status' => $status,
                'suggested_reorder' => $suggestedReorder,
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function expiry(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $days = (int) $request->get('days', 30);

        $query = Inventory::where('tenant_id', $tenantId)
            ->with('product', 'batch')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays($days)->toDateString())
            ->where('quantity', '>', 0);

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->get('store_id'));
        }

        $items = $query->orderBy('expiry_date')->get();

        $data = $items->map(function ($inv) {
            $daysUntilExpiry = now()->diffInDays($inv->expiry_date, false);

            return [
                'product_id' => $inv->product_id,
                'product' => [
                    'id' => $inv->product->id,
                    'name' => $inv->product->name,
                    'sku' => $inv->product->sku,
                ],
                'batch_id' => $inv->batch_id,
                'batch_number' => $inv->batch?->batch_number,
                'expiry_date' => $inv->expiry_date->format('Y-m-d'),
                'quantity' => $inv->quantity,
                'days_until_expiry' => (int) $daysUntilExpiry,
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function movements(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $query = InventoryMovement::where('tenant_id', $tenantId)
            ->with('product', 'store', 'user', 'batch', 'reason');

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->get('store_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->get('product_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }

        if ($request->filled('batch_id')) {
            $query->where('batch_id', (int) $request->get('batch_id'));
        }

        if ($request->filled('reason_id')) {
            $query->where('reason_id', (int) $request->get('reason_id'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->get('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->get('to'));
        }

        $perPage = min((int) $request->get('per_page', 20), 100);

        return response()->json($query->orderBy('created_at', 'desc')->paginate($perPage));
    }
}
