<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseReturnService
{
    public function __construct(
        private InventoryService $inventoryService,
    ) {}

    /**
     * Create a purchase return with items.
     * Only received purchases can be returned.
     * Return quantity per item cannot exceed purchased quantity.
     * Totals calculated by backend, never from request.
     */
    public function create(array $data): PurchaseReturn
    {
        return DB::transaction(function () use ($data) {
            $tenantId = Auth::user()->tenant_id;

            $purchase = Purchase::withoutTenantScope()->findOrFail($data['purchase_id']);
            if ($purchase->tenant_id !== $tenantId) {
                throw new \DomainException('Purchase does not belong to your tenant');
            }

            if ($purchase->status !== 'received') {
                throw new \DomainException('Only received purchases can be returned');
            }

            $store = Store::withoutTenantScope()->findOrFail($data['store_id'] ?? $purchase->store_id);
            if ($store->tenant_id !== $tenantId) {
                throw new \DomainException('Store does not belong to your tenant');
            }

            $purchase->load('items');

            $subtotal = 0;
            $totalDiscount = 0;
            $totalTax = 0;

            foreach ($data['items'] as $item) {
                $purchaseItem = $purchase->items->firstWhere('product_id', $item['product_id']);
                if (!$purchaseItem) {
                    throw new \DomainException("Product {$item['product_id']} is not in this purchase");
                }

                if ($item['quantity'] <= 0) {
                    throw new \DomainException('Return quantity must be greater than 0');
                }

                if ($item['unit_cost'] < 0) {
                    throw new \DomainException('Unit cost cannot be negative');
                }

                // Check total returned quantity for this purchase item doesn't exceed purchased
                $alreadyReturned = PurchaseReturnItem::whereIn('purchase_return_id', function ($q) use ($purchase, $tenantId) {
                    $q->select('id')
                        ->from('purchase_returns')
                        ->where('tenant_id', $tenantId)
                        ->where('purchase_id', $purchase->id)
                        ->where('status', '!=', 'cancelled');
                })->where('purchase_item_id', $purchaseItem->id)->sum('quantity');

                if ($alreadyReturned + $item['quantity'] > $purchaseItem->quantity) {
                    throw new \DomainException(
                        "Return quantity ({$alreadyReturned} + {$item['quantity']}) exceeds purchased quantity ({$purchaseItem->quantity})"
                    );
                }

                $lineTotal = ($item['quantity'] * $item['unit_cost']) - ($item['discount'] ?? 0) + ($item['tax'] ?? 0);
                $subtotal += $item['quantity'] * $item['unit_cost'];
                $totalDiscount += $item['discount'] ?? 0;
                $totalTax += $item['tax'] ?? 0;
            }

            $return = new PurchaseReturn;
            $return->tenant_id = $tenantId;
            $return->purchase_id = $purchase->id;
            $return->store_id = $store->id;
            $return->created_by = Auth::id();
            $return->return_number = $this->generateReturnNumber($tenantId);
            $return->status = 'draft';
            $return->return_date = $data['return_date'];
            $return->subtotal = $subtotal;
            $return->discount = $totalDiscount;
            $return->tax = $totalTax;
            $return->total = $subtotal - $totalDiscount + $totalTax;
            $return->notes = $data['notes'] ?? null;
            $return->save();

            foreach ($data['items'] as $item) {
                $purchaseItem = $purchase->items->firstWhere('product_id', $item['product_id']);
                $lineTotal = ($item['quantity'] * $item['unit_cost']) - ($item['discount'] ?? 0) + ($item['tax'] ?? 0);

                PurchaseReturnItem::create([
                    'purchase_return_id' => $return->id,
                    'product_id' => $item['product_id'],
                    'purchase_item_id' => $purchaseItem->id,
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'discount' => $item['discount'] ?? 0,
                    'tax' => $item['tax'] ?? 0,
                    'total' => $lineTotal,
                ]);
            }

            return $return->fresh(['items.product', 'purchase', 'store']);
        });
    }

    /**
     * Complete a draft return — decreases inventory via InventoryService.
     * This is the ONLY path that decreases stock for returns.
     * Completing is idempotent: cannot complete twice.
     */
    public function complete(PurchaseReturn $return): PurchaseReturn
    {
        if ($return->status === 'completed') {
            throw new \DomainException('Return has already been completed');
        }

        if ($return->status === 'cancelled') {
            throw new \DomainException('Cannot complete a cancelled return');
        }

        if ($return->status !== 'draft') {
            throw new \DomainException('Invalid return status for completing');
        }

        return DB::transaction(function () use ($return) {
            $store = $return->store;
            $return->load('items.product', 'purchase');

            foreach ($return->items as $item) {
                $product = $item->product;
                $this->inventoryService->decrease(
                    $store,
                    $product,
                    $item->quantity,
                    'purchase_return',
                    $return,
                    "Return {$return->return_number}",
                );
            }

            $return->status = 'completed';
            $return->save();

            return $return->fresh(['items.product', 'purchase', 'store']);
        });
    }

    /**
     * Cancel a draft return.
     */
    public function cancel(PurchaseReturn $return): PurchaseReturn
    {
        if ($return->status !== 'draft') {
            throw new \DomainException('Only draft returns can be cancelled');
        }

        $return->status = 'cancelled';
        $return->save();

        return $return->fresh(['items.product', 'purchase', 'store']);
    }

    /**
     * Delete a draft return.
     */
    public function delete(PurchaseReturn $return): void
    {
        if ($return->status !== 'draft') {
            throw new \DomainException('Only draft returns can be deleted');
        }

        $return->delete();
    }

    private function generateReturnNumber(int $tenantId): string
    {
        $date = now()->format('Ymd');
        $prefix = "PR-{$date}-";

        $last = PurchaseReturn::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('return_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last->return_number);
            $seq = (int) end($parts) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
