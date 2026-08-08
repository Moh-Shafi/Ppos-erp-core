<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(
        private InventoryService $inventoryService,
    ) {}

    /**
     * Create a purchase with items in a transaction.
     * Totals are calculated by the service, never from request.
     *
     * @param  array  $data  supplier_id, store_id, purchase_date, expected_date, notes, items[]
     * @return Purchase
     */
    public function create(array $data): Purchase
    {
        return DB::transaction(function () use ($data) {
            $tenantId = Auth::user()->tenant_id;

            $this->validateOwnership($data['supplier_id'], $data['store_id'], $data['items'], $tenantId);

            $subtotal = 0;
            $totalDiscount = 0;
            $totalTax = 0;

            foreach ($data['items'] as $item) {
                $lineTotal = ($item['quantity'] * $item['unit_cost']) - ($item['discount'] ?? 0) + ($item['tax'] ?? 0);
                $subtotal += $item['quantity'] * $item['unit_cost'];
                $totalDiscount += $item['discount'] ?? 0;
                $totalTax += $item['tax'] ?? 0;
            }

            $purchase = new Purchase;
            $purchase->tenant_id = $tenantId;
            $purchase->supplier_id = $data['supplier_id'];
            $purchase->store_id = $data['store_id'];
            $purchase->created_by = Auth::id();
            $purchase->purchase_number = $this->generatePurchaseNumber($tenantId);
            $purchase->status = 'draft';
            $purchase->purchase_date = $data['purchase_date'];
            $purchase->expected_date = $data['expected_date'] ?? null;
            $purchase->subtotal = $subtotal;
            $purchase->discount = $totalDiscount;
            $purchase->tax = $totalTax;
            $purchase->total = $subtotal - $totalDiscount + $totalTax;
            $purchase->notes = $data['notes'] ?? null;
            $purchase->save();

            foreach ($data['items'] as $item) {
                $lineTotal = ($item['quantity'] * $item['unit_cost']) - ($item['discount'] ?? 0) + ($item['tax'] ?? 0);
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'discount' => $item['discount'] ?? 0,
                    'tax' => $item['tax'] ?? 0,
                    'total' => $lineTotal,
                ]);
            }

            return $purchase->fresh(['items.product', 'supplier', 'store']);
        });
    }

    /**
     * Update a draft purchase's items and recalculate totals.
     */
    public function update(Purchase $purchase, array $data): Purchase
    {
        if ($purchase->status !== 'draft') {
            throw new \DomainException('Only draft purchases can be edited');
        }

        return DB::transaction(function () use ($purchase, $data) {
            $tenantId = Auth::user()->tenant_id;

            if (isset($data['supplier_id']) || isset($data['store_id']) || isset($data['items'])) {
                $this->validateOwnership(
                    $data['supplier_id'] ?? $purchase->supplier_id,
                    $data['store_id'] ?? $purchase->store_id,
                    $data['items'] ?? [],
                    $tenantId,
                );
            }

            if (isset($data['supplier_id'])) $purchase->supplier_id = $data['supplier_id'];
            if (isset($data['store_id'])) $purchase->store_id = $data['store_id'];
            if (isset($data['expected_date'])) $purchase->expected_date = $data['expected_date'];
            if (isset($data['notes'])) $purchase->notes = $data['notes'];

            if (isset($data['items'])) {
                $purchase->items()->delete();

                $subtotal = 0;
                $totalDiscount = 0;
                $totalTax = 0;

                foreach ($data['items'] as $item) {
                    $lineTotal = ($item['quantity'] * $item['unit_cost']) - ($item['discount'] ?? 0) + ($item['tax'] ?? 0);
                    $subtotal += $item['quantity'] * $item['unit_cost'];
                    $totalDiscount += $item['discount'] ?? 0;
                    $totalTax += $item['tax'] ?? 0;

                    PurchaseItem::create([
                        'purchase_id' => $purchase->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_cost' => $item['unit_cost'],
                        'discount' => $item['discount'] ?? 0,
                        'tax' => $item['tax'] ?? 0,
                        'total' => $lineTotal,
                    ]);
                }

                $purchase->subtotal = $subtotal;
                $purchase->discount = $totalDiscount;
                $purchase->tax = $totalTax;
                $purchase->total = $subtotal - $totalDiscount + $totalTax;
            }

            $purchase->save();
            return $purchase->fresh(['items.product', 'supplier', 'store']);
        });
    }

    /**
     * Transition draft → ordered.
     */
    public function order(Purchase $purchase): Purchase
    {
        if ($purchase->status !== 'draft') {
            throw new \DomainException('Only draft purchases can be ordered');
        }

        $purchase->status = 'ordered';
        $purchase->save();

        return $purchase->fresh(['items.product', 'supplier', 'store']);
    }

    /**
     * Receive an ordered purchase — increases inventory via InventoryService.
     * This is the ONLY path that increases stock.
     * Receiving is idempotent: cannot receive twice.
     */
    public function receive(Purchase $purchase): Purchase
    {
        if ($purchase->status === 'received') {
            throw new \DomainException('Purchase has already been received');
        }

        if ($purchase->status === 'cancelled') {
            throw new \DomainException('Cannot receive a cancelled purchase');
        }

        if ($purchase->status === 'draft') {
            throw new \DomainException('Purchase must be ordered before receiving');
        }

        if ($purchase->status !== 'ordered') {
            throw new \DomainException('Invalid purchase status for receiving');
        }

        return DB::transaction(function () use ($purchase) {
            $store = $purchase->store;
            $purchase->load('items.product');

            foreach ($purchase->items as $item) {
                $product = $item->product;
                $this->inventoryService->increase(
                    $store,
                    $product,
                    $item->quantity,
                    'purchase',
                    $purchase,
                    "PO {$purchase->purchase_number}",
                );
            }

            $purchase->status = 'received';
            $purchase->save();

            return $purchase->fresh(['items.product', 'supplier', 'store']);
        });
    }

    /**
     * Cancel a draft or ordered purchase.
     */
    public function cancel(Purchase $purchase): Purchase
    {
        if (!in_array($purchase->status, ['draft', 'ordered'])) {
            throw new \DomainException('Only draft or ordered purchases can be cancelled');
        }

        $purchase->status = 'cancelled';
        $purchase->save();

        return $purchase->fresh(['items.product', 'supplier', 'store']);
    }

    /**
     * Validate that supplier, store, and all products belong to the tenant.
     */
    private function validateOwnership(int $supplierId, int $storeId, array $items, int $tenantId): void
    {
        $supplier = Supplier::withoutTenantScope()->find($supplierId);
        if (!$supplier || $supplier->tenant_id !== $tenantId) {
            throw new \DomainException('Supplier does not belong to your tenant');
        }

        $store = Store::withoutTenantScope()->find($storeId);
        if (!$store || $store->tenant_id !== $tenantId) {
            throw new \DomainException('Store does not belong to your tenant');
        }

        $productIds = array_column($items, 'product_id');
        $products = Product::withoutTenantScope()->whereIn('id', $productIds)->get();
        foreach ($products as $product) {
            if ($product->tenant_id !== $tenantId) {
                throw new \DomainException("Product {$product->id} does not belong to your tenant");
            }
        }

        if ($products->count() !== count($productIds)) {
            throw new \DomainException('One or more products not found');
        }

        foreach ($items as $item) {
            if ($item['quantity'] <= 0) {
                throw new \DomainException('Quantity must be greater than 0');
            }
            if ($item['unit_cost'] < 0) {
                throw new \DomainException('Unit cost cannot be negative');
            }
        }
    }

    /**
     * Generate a unique purchase number: PO-YYYYMMDD-XXXX
     */
    private function generatePurchaseNumber(int $tenantId): string
    {
        $date = now()->format('Ymd');
        $prefix = "PO-{$date}-";

        $last = Purchase::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('purchase_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last->purchase_number);
            $seq = (int) end($parts) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
