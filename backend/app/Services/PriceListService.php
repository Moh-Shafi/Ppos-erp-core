<?php

namespace App\Services;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class PriceListService
{
    public function __construct(
        private readonly AuditService $auditService = new AuditService(),
    ) {}

    public function createPriceList(array $data, int $tenantId): PriceList
    {
        return DB::transaction(function () use ($data, $tenantId) {
            $slug = str()->slug($data['name']);
            $existing = PriceList::where('tenant_id', $tenantId)->where('slug', $slug)->exists();
            if ($existing) {
                $slug = $slug . '-' . time();
            }

            $isDefault = $data['is_default'] ?? false;

            if ($isDefault) {
                PriceList::where('tenant_id', $tenantId)->update(['is_default' => false]);
            }

            $priceList = PriceList::create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'is_default' => $isDefault,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->auditService->log('price_list.created', 'price_list', $priceList->id, null, $priceList->toArray());

            return $priceList;
        });
    }

    public function updatePriceList(int $id, array $data, int $tenantId): PriceList
    {
        return DB::transaction(function () use ($id, $data, $tenantId) {
            $priceList = PriceList::where('tenant_id', $tenantId)->findOrFail($id);
            $oldValues = $priceList->toArray();

            if (isset($data['is_default']) && $data['is_default'] && !$priceList->is_default) {
                PriceList::where('tenant_id', $tenantId)
                    ->where('id', '!=', $id)
                    ->update(['is_default' => false]);
            }

            if (isset($data['is_active']) && !$data['is_active'] && $priceList->is_default) {
                throw new \InvalidArgumentException('Cannot deactivate the default price list', 422);
            }

            if (isset($data['name'])) {
                $data['slug'] = str()->slug($data['name']);
            }

            $priceList->update($data);
            $priceList->refresh();

            $this->auditService->log('price_list.updated', 'price_list', $priceList->id, $oldValues, $priceList->toArray());

            return $priceList;
        });
    }

    public function deletePriceList(int $id, int $tenantId): void
    {
        DB::transaction(function () use ($id, $tenantId) {
            $priceList = PriceList::where('tenant_id', $tenantId)->findOrFail($id);

            if ($priceList->is_default) {
                throw new \InvalidArgumentException('Cannot delete the default price list', 422);
            }

            $oldValues = $priceList->toArray();
            $priceList->delete();

            $this->auditService->log('price_list.deleted', 'price_list', $priceList->id, $oldValues, null);
        });
    }

    public function addItem(int $priceListId, array $data, int $tenantId): PriceListItem
    {
        return DB::transaction(function () use ($priceListId, $data, $tenantId) {
            $priceList = PriceList::where('tenant_id', $tenantId)->findOrFail($priceListId);

            $product = Product::where('tenant_id', $tenantId)->findOrFail($data['product_id']);

            $variantId = $data['variant_id'] ?? null;

            $existing = PriceListItem::where('price_list_id', $priceListId)
                ->where('product_id', $data['product_id'])
                ->where('variant_id', $variantId)
                ->exists();

            if ($existing) {
                throw new \InvalidArgumentException('Price list item already exists for this product/variant', 422);
            }

            return PriceListItem::create([
                'price_list_id' => $priceListId,
                'product_id' => $data['product_id'],
                'variant_id' => $variantId,
                'price' => $data['price'],
            ]);
        });
    }

    public function updateItem(int $priceListId, int $itemId, array $data, int $tenantId): PriceListItem
    {
        $item = PriceListItem::whereHas('priceList', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->findOrFail($itemId);

        $item->update($data);
        $item->refresh();

        return $item;
    }

    public function deleteItem(int $priceListId, int $itemId, int $tenantId): void
    {
        $item = PriceListItem::whereHas('priceList', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->findOrFail($itemId);

        $item->delete();
    }

    public function resolvePrice(int $productId, ?int $variantId, ?int $priceListId): string
    {
        if ($priceListId) {
            $item = PriceListItem::where('price_list_id', $priceListId)
                ->where('product_id', $productId)
                ->where('variant_id', $variantId)
                ->first();

            if ($item) {
                return $item->price;
            }
        }

        if ($variantId) {
            $variant = \App\Models\ProductVariant::find($variantId);
            if ($variant && $variant->price_override !== null) {
                return $variant->price_override;
            }
        }

        $product = Product::find($productId);
        return $product ? $product->selling_price : '0';
    }
}
