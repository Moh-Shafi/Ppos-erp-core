<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function __construct(
        private InventoryService $inventoryService,
        private PaymentService $paymentService,
    ) {}

    /**
     * Atomic checkout: Sale + SaleItems + Inventory decrease + Movements + Payment.
     * All in a single DB::transaction — if any step fails, everything rolls back.
     *
     * @param  array  $data  store_id, customer_id?, items[], payments[], notes?, discount?, tax?
     *   items[]: product_id, quantity (unit_price from Product.selling_price, NOT from request)
     *   payments[]: payment_method, amount (change auto-calculated for cash)
     * @return Sale
     * @throws \DomainException  For validation failures (cross-tenant, insufficient stock, etc.)
     * @throws \InvalidArgumentException  For invalid quantities or amounts
     */
    public function checkout(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            // --- Validate store belongs to tenant ---
            $store = Store::withoutTenantScope()->find($data['store_id']);
            if (!$store || $store->tenant_id !== $tenantId) {
                throw new \DomainException('Store does not belong to your tenant');
            }

            // --- Validate customer (if provided) belongs to tenant ---
            $customerId = null;
            if (!empty($data['customer_id'])) {
                $customer = Customer::withoutTenantScope()->find($data['customer_id']);
                if (!$customer || $customer->tenant_id !== $tenantId) {
                    throw new \DomainException('Customer does not belong to your tenant');
                }
                $customerId = $customer->id;
            }

            // --- Validate items ---
            if (empty($data['items'])) {
                throw new \DomainException('Sale must have at least one item');
            }

            $productIds = array_column($data['items'], 'product_id');
            $products = Product::withoutTenantScope()
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            // Check all products exist and belong to tenant
            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                if (!$product) {
                    throw new \DomainException("Product {$item['product_id']} not found");
                }
                if ($product->tenant_id !== $tenantId) {
                    throw new \DomainException("Product {$product->id} does not belong to your tenant");
                }
                if (!$product->is_active) {
                    throw new \DomainException("Product {$product->name} is not active");
                }
                if ($item['quantity'] <= 0) {
                    throw new \DomainException('Quantity must be greater than 0');
                }
            }

            // Check for duplicate products in cart
            $uniqueProductIds = array_unique($productIds);
            if (count($uniqueProductIds) !== count($productIds)) {
                throw new \DomainException('Duplicate products in cart are not allowed');
            }

            // --- Lock inventory rows for all products in this store ---
            $inventories = Inventory::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->where('store_id', $store->id)
                ->whereIn('product_id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            // Check sufficient stock for all items BEFORE creating anything
            foreach ($data['items'] as $item) {
                $inv = $inventories->get($item['product_id']);
                $currentQty = $inv ? $inv->quantity : 0;
                if ($currentQty < $item['quantity']) {
                    $product = $products->get($item['product_id']);
                    throw new \InvalidArgumentException(
                        "Insufficient stock for {$product->name}. Available: {$currentQty}, Requested: {$item['quantity']}"
                    );
                }
            }

            // --- Calculate totals (backend, never from request) ---
            $subtotal = 0;
            $totalItemDiscount = 0;
            $totalItemTax = 0;

            $itemData = [];
            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                $unitPrice = (float) $product->selling_price; // SNAPSHOT from product
                $quantity = $item['quantity'];
                $lineSubtotal = $unitPrice * $quantity;
                $lineDiscount = 0; // line-level discount not supported from request
                $lineTax = 0; // line-level tax not supported from request
                $lineTotal = $lineSubtotal - $lineDiscount + $lineTax;

                $subtotal += $lineSubtotal;
                $totalItemDiscount += $lineDiscount;
                $totalItemTax += $lineTax;

                $itemData[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name, // SNAPSHOT
                    'sku' => $product->sku, // SNAPSHOT
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice, // SNAPSHOT
                    'discount' => $lineDiscount,
                    'tax' => $lineTax,
                    'subtotal' => $lineSubtotal,
                    'total' => $lineTotal,
                ];
            }

            // Sale-level discount and tax (from request but validated)
            $saleDiscount = (float) ($data['discount'] ?? 0);
            $saleTax = (float) ($data['tax'] ?? 0);

            if ($saleDiscount < 0) {
                throw new \DomainException('Discount cannot be negative');
            }
            if ($saleDiscount > $subtotal) {
                throw new \DomainException('Discount cannot exceed subtotal');
            }
            if ($saleTax < 0) {
                throw new \DomainException('Tax cannot be negative');
            }

            $total = $subtotal - $saleDiscount + $saleTax;

            // --- Validate payments ---
            if (empty($data['payments'])) {
                throw new \DomainException('At least one payment is required');
            }

            $totalPaid = 0;
            foreach ($data['payments'] as $pay) {
                $amount = (float) $pay['amount'];
                if ($amount <= 0) {
                    throw new \DomainException('Payment amount must be greater than 0');
                }
                $validMethods = ['cash', 'qris', 'card', 'bank_transfer'];
                if (!in_array($pay['payment_method'], $validMethods)) {
                    throw new \DomainException("Invalid payment method: {$pay['payment_method']}");
                }
                $totalPaid += $amount;
            }

            // Determine payment status
            $changeAmount = 0;
            if ($totalPaid >= $total) {
                $paymentStatus = 'paid';
                $changeAmount = $totalPaid - $total;
            } elseif ($totalPaid > 0) {
                $paymentStatus = 'partial';
            } else {
                $paymentStatus = 'unpaid';
            }

            // --- Create Sale ---
            $sale = new Sale;
            $sale->tenant_id = $tenantId;
            $sale->store_id = $store->id;
            $sale->cashier_id = $user->id;
            $sale->customer_id = $customerId;
            $sale->sale_number = $this->generateSaleNumber($tenantId);
            $sale->status = 'completed';
            $sale->payment_status = $paymentStatus;
            $sale->sale_date = now();
            $sale->subtotal = $subtotal;
            $sale->discount = $saleDiscount;
            $sale->tax = $saleTax;
            $sale->total = $total;
            $sale->paid_amount = $totalPaid;
            $sale->change_amount = $changeAmount;
            $sale->notes = $data['notes'] ?? null;
            $sale->save();

            // --- Create SaleItems (with snapshot) ---
            foreach ($itemData as $item) {
                $saleItem = new SaleItem;
                $saleItem->sale_id = $sale->id;
                $saleItem->product_id = $item['product_id'];
                $saleItem->product_name = $item['product_name'];
                $saleItem->sku = $item['sku'];
                $saleItem->quantity = $item['quantity'];
                $saleItem->unit_price = $item['unit_price'];
                $saleItem->discount = $item['discount'];
                $saleItem->tax = $item['tax'];
                $saleItem->subtotal = $item['subtotal'];
                $saleItem->total = $item['total'];
                $saleItem->save();
            }

            // --- Decrease inventory + create movements ---
            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                $this->inventoryService->decrease(
                    $store,
                    $product,
                    $item['quantity'],
                    'sale',
                    $sale,
                    "Sale {$sale->sale_number}",
                );
            }

            // --- Create Payments (via PaymentService for idempotency) ---
            $this->paymentService->createForCheckout($sale, $data['payments'], $tenantId);

            return $sale->fresh(['items.product', 'payments', 'store', 'cashier', 'customer']);
        });
    }

    /**
     * Cancel a completed sale — restores inventory via InventoryService::increase().
     * Creates sale_return movements. Sale status → cancelled.
     */
    public function cancel(Sale $sale): Sale
    {
        if ($sale->status !== 'completed') {
            throw new \DomainException('Only completed sales can be cancelled');
        }

        return DB::transaction(function () use ($sale) {
            $sale->load(['items.product', 'store']);

            // Refund all successful payments
            $this->paymentService->refundPayments($sale, $sale->tenant_id);

            // Restore inventory for each item
            foreach ($sale->items as $item) {
                $this->inventoryService->increase(
                    $sale->store,
                    $item->product,
                    $item->quantity,
                    'sale_return',
                    $sale,
                    "Cancel sale {$sale->sale_number}",
                );
            }

            $sale->status = 'cancelled';
            $sale->save();

            return $sale->fresh(['items.product', 'payments', 'store', 'cashier', 'customer']);
        });
    }

    /**
     * Generate a unique sale number: INV-YYYYMMDD-XXXX
     */
    private function generateSaleNumber(int $tenantId): string
    {
        $date = now()->format('Ymd');
        $prefix = "INV-{$date}-";

        $last = Sale::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('sale_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last->sale_number);
            $seq = (int) end($parts) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
