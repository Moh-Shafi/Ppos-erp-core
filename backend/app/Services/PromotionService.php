<?php

namespace App\Services;

use App\Models\Promotion;
use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PromotionService
{
    public function validate(array $items, int $tenantId, ?int $customerId = null): array
    {
        $promotions = Promotion::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('start_date', '<=', today())
            ->where('end_date', '>=', today())
            ->with('rules')
            ->get();

        $subtotal = collect($items)->sum(function ($item) {
            return (float) $item['unit_price'] * $item['quantity'];
        });

        $totalQuantity = collect($items)->sum('quantity');
        $productIds = collect($items)->pluck('product_id')->toArray();
        $categoryIds = collect($items)->pluck('category_id')->filter()->toArray();

        $applicable = [];

        foreach ($promotions as $promotion) {
            if ($promotion->usage_limit !== null && $promotion->usage_count >= $promotion->usage_limit) {
                continue;
            }

            $allRulesPass = true;
            foreach ($promotion->rules as $rule) {
                $value = $rule->rule_value;
                $pass = match ($rule->rule_type) {
                    'min_purchase' => $subtotal >= (float) $value['amount'],
                    'min_quantity' => $totalQuantity >= (int) $value['quantity'],
                    'category' => in_array($value['category_id'], $categoryIds),
                    'product' => in_array($value['product_id'], $productIds),
                    'customer_group' => $customerId !== null,
                    default => false,
                };

                if (!$pass) {
                    $allRulesPass = false;
                    break;
                }
            }

            if (!$allRulesPass) {
                continue;
            }

            $discount = $this->calculateDiscount($promotion, $subtotal, $items);

            $applicable[] = [
                'promotion_id' => $promotion->id,
                'name' => $promotion->name,
                'type' => $promotion->type,
                'discount_amount' => $discount,
                'description' => $promotion->description,
            ];
        }

        return $applicable;
    }

    protected function calculateDiscount(Promotion $promotion, float $subtotal, array $items): float
    {
        return match ($promotion->type) {
            'percentage' => round($subtotal * ((float) $promotion->value / 100), 2),
            'fixed' => min((float) $promotion->value, $subtotal),
            'buy_x_get_y' => $this->calculateBuyXGetY($promotion, $items),
            'tiered' => $this->calculateTiered($promotion, $subtotal),
            default => 0,
        };
    }

    protected function calculateBuyXGetY(Promotion $promotion, array $items): float
    {
        $conditions = $promotion->conditions ?? [];
        $buyQty = $conditions['buy_quantity'] ?? 0;
        $getQty = $conditions['get_quantity'] ?? 0;
        $productId = $conditions['product_id'] ?? null;

        if (!$productId || !$buyQty || !$getQty) {
            return 0;
        }

        $productItem = collect($items)->firstWhere('product_id', $productId);
        if (!$productItem) {
            return 0;
        }

        $totalQty = $productItem['quantity'];
        $sets = intdiv($totalQty, $buyQty + $getQty);
        $freeQty = $sets * $getQty;

        return round($freeQty * (float) $productItem['unit_price'], 2);
    }

    protected function calculateTiered(Promotion $promotion, float $subtotal): float
    {
        $tiers = $promotion->conditions['tiers'] ?? [];
        $applicableTier = null;

        foreach ($tiers as $tier) {
            if ($subtotal >= (float) $tier['min_purchase']) {
                $applicableTier = $tier;
            }
        }

        if (!$applicableTier) {
            return 0;
        }

        return round($subtotal * ((float) $applicableTier['discount_percent'] / 100), 2);
    }

    public function recordUsage(Sale $sale, array $promotionIds): void
    {
        foreach ($promotionIds as $promotionId) {
            $promotion = Promotion::withoutTenantScope()
                ->where('tenant_id', $sale->tenant_id)
                ->where('id', $promotionId)
                ->first();

            if ($promotion && $promotion->is_active && $promotion->end_date >= today()) {
                $promotion->usage_count++;
                $promotion->save();
            }
        }
    }
}
