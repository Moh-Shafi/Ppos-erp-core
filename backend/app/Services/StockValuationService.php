<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class StockValuationService
{
    public function calculate(int $tenantId, string $method = 'average', ?int $storeId = null): array
    {
        $query = InventoryMovement::where('tenant_id', $tenantId)
            ->whereIn('type', ['purchase', 'sale', 'sale_return', 'purchase_return', 'adjustment', 'initial']);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $movements = $query->orderBy('created_at')->orderBy('id')->get();

        $products = Product::where('tenant_id', $tenantId)->get()->keyBy('id');

        $result = [];
        $grandTotal = 0;

        foreach ($products as $product) {
            $valuation = $this->calculateProduct($product->id, $movements->where('product_id', $product->id), $method);
            if ($valuation['quantity'] > 0) {
                $result[] = [
                    'product_id' => $product->id,
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                    ],
                    'quantity' => $valuation['quantity'],
                    'unit_cost' => number_format($valuation['unit_cost'], 2, '.', ''),
                    'total_value' => number_format($valuation['total_value'], 2, '.', ''),
                ];
                $grandTotal += $valuation['total_value'];
            }
        }

        return [
            'method' => $method,
            'data' => $result,
            'grand_total' => number_format($grandTotal, 2, '.', ''),
        ];
    }

    private function calculateProduct(int $productId, $movements, string $method): array
    {
        if ($method === 'fifo') {
            return $this->calculateFifo($movements);
        } elseif ($method === 'lifo') {
            return $this->calculateLifo($movements);
        } else {
            return $this->calculateAverage($movements);
        }
    }

    private function calculateAverage($movements): array
    {
        $totalCost = 0;
        $totalQty = 0;

        foreach ($movements as $movement) {
            if ($movement->quantity > 0) {
                $cost = $this->getMovementCost($movement);
                $totalCost += $cost * $movement->quantity;
                $totalQty += $movement->quantity;
            } else {
                $qty = abs($movement->quantity);
                if ($totalQty > 0) {
                    $avgCost = $totalCost / $totalQty;
                    $totalCost -= $avgCost * $qty;
                    $totalQty -= $qty;
                }
            }
        }

        $unitCost = $totalQty > 0 ? $totalCost / $totalQty : 0;

        return [
            'quantity' => $totalQty,
            'unit_cost' => $unitCost,
            'total_value' => $totalCost,
        ];
    }

    private function calculateFifo($movements): array
    {
        $layers = [];

        foreach ($movements as $movement) {
            if ($movement->quantity > 0) {
                $cost = $this->getMovementCost($movement);
                $layers[] = ['qty' => $movement->quantity, 'cost' => $cost];
            } else {
                $qty = abs($movement->quantity);
                while ($qty > 0 && !empty($layers)) {
                    $consume = min($qty, $layers[0]['qty']);
                    $layers[0]['qty'] -= $consume;
                    $qty -= $consume;
                    if ($layers[0]['qty'] <= 0) {
                        array_shift($layers);
                    }
                }
            }
        }

        $totalQty = 0;
        $totalValue = 0;
        foreach ($layers as $layer) {
            $totalQty += $layer['qty'];
            $totalValue += $layer['qty'] * $layer['cost'];
        }

        $unitCost = $totalQty > 0 ? $totalValue / $totalQty : 0;

        return [
            'quantity' => $totalQty,
            'unit_cost' => $unitCost,
            'total_value' => $totalValue,
        ];
    }

    private function calculateLifo($movements): array
    {
        $layers = [];

        foreach ($movements as $movement) {
            if ($movement->quantity > 0) {
                $cost = $this->getMovementCost($movement);
                $layers[] = ['qty' => $movement->quantity, 'cost' => $cost];
            } else {
                $qty = abs($movement->quantity);
                while ($qty > 0 && !empty($layers)) {
                    $lastIndex = count($layers) - 1;
                    $consume = min($qty, $layers[$lastIndex]['qty']);
                    $layers[$lastIndex]['qty'] -= $consume;
                    $qty -= $consume;
                    if ($layers[$lastIndex]['qty'] <= 0) {
                        array_pop($layers);
                    }
                }
            }
        }

        $totalQty = 0;
        $totalValue = 0;
        foreach ($layers as $layer) {
            $totalQty += $layer['qty'];
            $totalValue += $layer['qty'] * $layer['cost'];
        }

        $unitCost = $totalQty > 0 ? $totalValue / $totalQty : 0;

        return [
            'quantity' => $totalQty,
            'unit_cost' => $unitCost,
            'total_value' => $totalValue,
        ];
    }

    private function getMovementCost(InventoryMovement $movement): float
    {
        if ($movement->batch_id) {
            $batch = $movement->batch;
            if ($batch && $batch->cost_price) {
                return (float) $batch->cost_price;
            }
        }

        $product = Product::find($movement->product_id);
        return $product ? (float) $product->cost_price : 0;
    }
}
