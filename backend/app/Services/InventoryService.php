<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Increase stock for a product in a store.
     *
     * @param  Store  $store  Must belong to the same tenant as the authenticated user
     * @param  Product  $product  Must belong to the same tenant
     * @param  int  $quantity  Must be > 0
     * @param  string  $type  Movement type (purchase, sale_return, transfer_in, initial, adjustment)
     * @param  Model|null  $reference  Polymorphic reference (e.g. Purchase model)
     * @param  string|null  $note  Optional note
     * @return InventoryMovement
     * @throws \InvalidArgumentException  When store/product cross-tenant or quantity invalid
     * @throws \Illuminate\Database\QueryException  On DB errors
     */
    public function increase(
        Store $store,
        Product $product,
        int $quantity,
        string $type = 'purchase',
        ?Model $reference = null,
        ?string $note = null,
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be greater than 0');
        }

        return $this->applyMovement($store, $product, $quantity, $type, $reference, $note);
    }

    /**
     * Decrease stock for a product in a store.
     *
     * @param  Store  $store  Must belong to the same tenant
     * @param  Product  $product  Must belong to the same tenant
     * @param  int  $quantity  Must be > 0
     * @param  string  $type  Movement type (sale, purchase_return, transfer_out, adjustment)
     * @param  Model|null  $reference  Polymorphic reference
     * @param  string|null  $note  Optional note
     * @return InventoryMovement
     * @throws \InvalidArgumentException  When insufficient stock, cross-tenant, or quantity invalid
     * @throws \Illuminate\Database\QueryException  On DB errors
     */
    public function decrease(
        Store $store,
        Product $product,
        int $quantity,
        string $type = 'sale',
        ?Model $reference = null,
        ?string $note = null,
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be greater than 0');
        }

        return $this->applyMovement($store, $product, -$quantity, $type, $reference, $note);
    }

    /**
     * Adjust stock by a delta (positive or negative).
     *
     * @param  Store  $store  Must belong to the same tenant
     * @param  Product  $product  Must belong to the same tenant
     * @param  int  $delta  Can be positive or negative (but not 0)
     * @param  Model|null  $reference  Polymorphic reference
     * @param  string|null  $note  Optional note
     * @return InventoryMovement
     * @throws \InvalidArgumentException  When delta is 0, insufficient stock, or cross-tenant
     * @throws \Illuminate\Database\QueryException  On DB errors
     */
    public function adjust(
        Store $store,
        Product $product,
        int $delta,
        ?Model $reference = null,
        ?string $note = null,
    ): InventoryMovement {
        if ($delta === 0) {
            throw new \InvalidArgumentException('Delta cannot be 0');
        }

        return $this->applyMovement($store, $product, $delta, 'adjustment', $reference, $note);
    }

    /**
     * Core method that applies a stock movement within a DB transaction.
     * Uses lockForUpdate to prevent race conditions.
     *
     * @param  int  $signedQuantity  Positive for increase, negative for decrease
     * @param  string  $type  Movement type
     * @param  Model|null  $reference  Polymorphic reference
     * @param  string|null  $note  Optional note
     * @return InventoryMovement
     * @throws \InvalidArgumentException  When cross-tenant, insufficient stock
     * @throws \Illuminate\Database\QueryException  On DB errors
     */
    private function applyMovement(
        Store $store,
        Product $product,
        int $signedQuantity,
        string $type,
        ?Model $reference,
        ?string $note,
    ): InventoryMovement {
        $this->validateOwnership($store, $product);

        $user = Auth::user();
        $tenantId = $user->tenant_id;

        return DB::transaction(function () use ($store, $product, $signedQuantity, $type, $reference, $note, $user, $tenantId) {
            // Lock the inventory row for update to prevent race conditions
            $inventory = Inventory::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->where('store_id', $store->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if (!$inventory) {
                // Create new inventory record
                $inventory = new Inventory;
                $inventory->tenant_id = $tenantId;
                $inventory->store_id = $store->id;
                $inventory->product_id = $product->id;
                $inventory->quantity = 0;
                $inventory->minimum_quantity = 0;
                $inventory->save();
            }

            $beforeQuantity = $inventory->quantity;
            $afterQuantity = $beforeQuantity + $signedQuantity;

            // Prevent negative stock (unless allow_negative_stock is set on store in future)
            if ($afterQuantity < 0) {
                throw new \InvalidArgumentException(
                    "Insufficient stock. Current: {$beforeQuantity}, Requested change: {$signedQuantity}"
                );
            }

            // Update inventory quantity
            $inventory->quantity = $afterQuantity;
            $inventory->save();

            // Create movement record
            $movement = new InventoryMovement;
            $movement->tenant_id = $tenantId;
            $movement->store_id = $store->id;
            $movement->product_id = $product->id;
            $movement->user_id = $user->id;
            $movement->type = $type;
            $movement->quantity = $signedQuantity;
            $movement->before_quantity = $beforeQuantity;
            $movement->after_quantity = $afterQuantity;
            $movement->note = $note;

            if ($reference) {
                $movement->reference_type = get_class($reference);
                $movement->reference_id = $reference->id;
            }

            $movement->save();

            return $movement;
        });
    }

    /**
     * Validate that both store and product belong to the authenticated user's tenant.
     *
     * @throws \InvalidArgumentException  When store or product belongs to a different tenant
     */
    private function validateOwnership(Store $store, Product $product): void
    {
        $user = Auth::user();

        if (!$user) {
            throw new \InvalidArgumentException('Unauthenticated');
        }

        $tenantId = $user->tenant_id;

        // Check store belongs to user's tenant
        $storeBelongsToTenant = Store::withoutTenantScope()
            ->where('id', $store->id)
            ->where('tenant_id', $tenantId)
            ->exists();

        if (!$storeBelongsToTenant) {
            throw new \InvalidArgumentException('Store does not belong to your tenant');
        }

        // Check product belongs to user's tenant
        $productBelongsToTenant = Product::withoutTenantScope()
            ->where('id', $product->id)
            ->where('tenant_id', $tenantId)
            ->exists();

        if (!$productBelongsToTenant) {
            throw new \InvalidArgumentException('Product does not belong to your tenant');
        }
    }
}
