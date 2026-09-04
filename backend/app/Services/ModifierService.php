<?php

namespace App\Services;

use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Models\Product;
use Illuminate\Support\Collection;

class ModifierService
{
    public function create(string $name, string $type, bool $isRequired = false): Modifier
    {
        return Modifier::create([
            'name' => $name,
            'type' => $type,
            'is_required' => $isRequired,
        ]);
    }

    public function addOption(int $modifierId, string $name, float $priceDelta, int $sortOrder = 0): ModifierOption
    {
        return ModifierOption::create([
            'modifier_id' => $modifierId,
            'name' => $name,
            'price_delta' => $priceDelta,
            'sort_order' => $sortOrder,
        ]);
    }

    public function attachToProduct(int $productId, int $modifierId): void
    {
        $product = Product::findOrFail($productId);
        $modifier = Modifier::findOrFail($modifierId);

        if ($product->modifiers()->where('modifier_id', $modifierId)->exists()) {
            return;
        }

        $product->modifiers()->attach($modifierId);
    }

    public function detachFromProduct(int $productId, int $modifierId): void
    {
        Product::findOrFail($productId)->modifiers()->detach($modifierId);
    }

    public function resolveModifiers(array $selections, Product $product): array
    {
        $totalDelta = 0;
        $modifierData = [];

        $attachedModifiers = $product->modifiers()->with('options')->get()->keyBy('id');

        foreach ($selections as $selection) {
            $modifier = $attachedModifiers->get($selection['modifier_id']);
            if (!$modifier) {
                throw new \DomainException("Modifier {$selection['modifier_id']} is not attached to product {$product->name}");
            }

            $optionIds = $selection['option_ids'] ?? [];
            if ($modifier->type === 'single' && count($optionIds) > 1) {
                throw new \DomainException("Modifier '{$modifier->name}' allows only one option");
            }

            $options = [];
            foreach ($optionIds as $optionId) {
                $option = $modifier->options->firstWhere('id', $optionId);
                if (!$option) {
                    throw new \DomainException("Option {$optionId} does not belong to modifier '{$modifier->name}'");
                }
                $totalDelta += (float) $option->price_delta;
                $options[] = [
                    'id' => $option->id,
                    'name' => $option->name,
                    'price_delta' => (float) $option->price_delta,
                ];
            }

            $modifierData[] = [
                'modifier_id' => $modifier->id,
                'modifier_name' => $modifier->name,
                'options' => $options,
            ];
        }

        foreach ($attachedModifiers as $modifier) {
            if ($modifier->is_required) {
                $hasSelection = collect($selections)->contains('modifier_id', $modifier->id);
                if (!$hasSelection) {
                    throw new \DomainException("Modifier '{$modifier->name}' is required for product {$product->name}");
                }
            }
        }

        return [$totalDelta, $modifierData];
    }
}
