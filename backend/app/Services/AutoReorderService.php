<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AutoReorderService
{
    public function report(int $storeId): array
    {
        $tenantId = Auth::user()->tenant_id;

        $store = Store::where('tenant_id', $tenantId)->findOrFail($storeId);

        $lowStockItems = Inventory::where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->whereColumn('quantity', '<=', 'minimum_quantity')
            ->with('product:id,name,cost_price')
            ->get();

        $data = [];
        foreach ($lowStockItems as $item) {
            $suggestedQty = $this->calculateSuggestedQty($item);
            $estimatedCost = $suggestedQty * (float) ($item->product->cost_price ?? 0);

            $data[] = [
                'product_id' => $item->product_id,
                'product_name' => $item->product->name ?? 'Unknown',
                'current_stock' => $item->quantity,
                'minimum_quantity' => $item->minimum_quantity,
                'maximum_quantity' => $item->maximum_quantity,
                'suggested_qty' => $suggestedQty,
                'estimated_cost' => $estimatedCost,
            ];
        }

        return [
            'data' => $data,
            'store_id' => $storeId,
            'count' => count($data),
        ];
    }

    public function generateRequisition(int $storeId, array $productIds): array
    {
        $report = $this->report($storeId);

        $filteredItems = array_filter($report['data'], function ($item) use ($productIds) {
            return in_array($item['product_id'], $productIds);
        });

        if (empty($filteredItems)) {
            throw new \DomainException('No matching low-stock products found');
        }

        $requisitionService = new RequisitionService();
        $items = array_map(function ($item) {
            return [
                'product_id' => $item['product_id'],
                'quantity' => $item['suggested_qty'],
                'estimated_cost' => $item['estimated_cost'] / $item['suggested_qty'],
            ];
        }, array_values($filteredItems));

        $requisition = $requisitionService->create([
            'store_id' => $storeId,
            'items' => $items,
        ]);

        return ['requisition' => $requisition];
    }

    private function calculateSuggestedQty(Inventory $item): int
    {
        $currentQty = $item->quantity;
        $minQty = $item->minimum_quantity ?? 0;
        $maxQty = $item->maximum_quantity;

        if ($maxQty && $maxQty > 0) {
            return max(0, $maxQty - $currentQty);
        }

        return max(0, $minQty * 2);
    }
}
